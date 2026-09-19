<?php

namespace App\Console\Commands;

use App\Models\AttendanceLog;
use App\Models\Device;
use App\Models\Organisation;
use App\Services\FrappeHrService;
use App\Services\ZktecoService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncAttendanceToFrappeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:auto-sync 
                            {--fetch-devices : Fetch new punches from connected biometric devices first} 
                            {--force : Retry even already synced attendance logs}
                            {--trigger-attendance : Trigger Frappe Auto Attendance calculation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically fetch biometric device logs, push check-ins to Frappe HR, and trigger auto-attendance';

    /**
     * Execute the console command.
     */
    public function handle(FrappeHrService $frappe, ZktecoService $zk): int
    {
        \Illuminate\Support\Facades\DB::disableQueryLog();
        $this->info('[' . now()->toDateTimeString() . '] Starting Attendance Auto-Sync...');

        $organisations = Organisation::all();
        if ($organisations->isEmpty()) {
            $this->comment('No organisations found.');
            return Command::SUCCESS;
        }

        $shouldFetchDevices = $this->option('fetch-devices');
        $force = $this->option('force');
        $triggerAttendance = $this->option('trigger-attendance') !== false;

        foreach ($organisations as $org) {
            $this->info("Processing organisation: {$org->name} ({$org->id})");

            try {
                tenancy()->initialize($org);

                // 1. Fetch from biometric devices if requested
                if ($shouldFetchDevices) {
                    $this->fetchFromDevices($org, $zk);
                }

                // 2. Sync punches to Frappe HR in high-throughput concurrent batches
                $query = AttendanceLog::query()->with('device');
                if (!$force) {
                    $query->whereNull('frappe_synced_at');
                }

                $totalSynced = 0;
                $totalFailed = 0;
                $batchCount = 0;

                $query->orderBy('id', 'asc')->chunkById(50, function ($chunk) use ($frappe, $org, &$totalSynced, &$totalFailed, &$batchCount) {
                    $batchCount++;
                    $this->comment("Syncing batch #{$batchCount} ({$chunk->count()} punches) via concurrent pool...");
                    $res = $frappe->syncAttendanceBatch($chunk, $org, 10);
                    $totalSynced += $res['synced'];
                    $totalFailed += $res['failed'];
                    unset($chunk);
                });

                if ($totalSynced > 0 || $totalFailed > 0) {
                    $this->info("Organisation {$org->name}: {$totalSynced} synced, {$totalFailed} failed across {$batchCount} batches.");
                } else {
                    $this->line("Organisation {$org->name}: All punches already synced.");
                }

                // 3. Trigger Frappe HR Auto Attendance calculation for shifts
                if ($triggerAttendance) {
                    $shifts = $frappe->getShiftTypes($org);
                    foreach ($shifts as $shift) {
                        if (!empty($shift['enable_auto_attendance'])) {
                            $shiftName = $shift['name'];
                            $this->comment("Triggering Auto Attendance for shift: {$shiftName}...");
                            $attResult = $frappe->triggerAutoAttendance($shiftName, $org);
                            if (!empty($attResult['success'])) {
                                $this->info("Auto Attendance successfully processed for {$shiftName}.");
                            } else {
                                $this->warn("Auto Attendance skipped/failed for {$shiftName}: " . ($attResult['error'] ?? 'Unknown'));
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::error("Failed to process auto-sync for organisation {$org->name}: " . $e->getMessage(), [
                    'exception' => $e,
                ]);
                $this->error("Failed to process organisation {$org->name}: " . $e->getMessage());
            } finally {
                if (tenancy()->initialized) {
                    tenancy()->end();
                }
                gc_collect_cycles();
            }
        }

        $this->info('[' . now()->toDateTimeString() . '] Attendance Auto-Sync completed.');
        return Command::SUCCESS;
    }

    /**
     * Poll online biometric devices for new punches.
     */
    protected function fetchFromDevices(Organisation $org, ZktecoService $zk): void
    {
        $devices = Device::whereNotNull('ip_address')->get();
        foreach ($devices as $device) {
            $ip = $device->ip_address;
            $port = 4370;

            $this->line("Polling device {$device->name} ({$ip}:{$port})...");
            try {
                if (!$zk->testConnection($ip, $port)) {
                    $this->warn("Device {$device->name} at {$ip} is not responding.");
                    continue;
                }

                $logs = $zk->getAttendanceLogs($ip, $port);
                $imported = 0;

                // Find latest punch timestamp in DB for this device to avoid scanning thousands of old logs
                $latestPunchedAt = AttendanceLog::where('device_id', $device->id)->max('punched_at');
                $cutoffDate = $latestPunchedAt ? Carbon::parse($latestPunchedAt)->subHours(24) : null;

                foreach ($logs as $log) {
                    if (empty($log['id']) || empty($log['timestamp'])) {
                        continue;
                    }

                    try {
                        $punchTime = Carbon::parse($log['timestamp']);
                    } catch (\Throwable $e) {
                        continue; // skip corrupt timestamp from hardware
                    }

                    // If we have a cutoff date and punch is older than 24h before last known sync, skip DB hit
                    if ($cutoffDate && $punchTime->lessThan($cutoffDate)) {
                        continue;
                    }

                    $status = (int) ($log['state'] ?? 0);
                    if ($device->punch_behavior === 'always_in') {
                        $status = 0;
                    } elseif ($device->punch_behavior === 'always_out') {
                        $status = 1;
                    } elseif ($device->punch_behavior === 'auto') {
                        $lastLog = AttendanceLog::where('pin', (string)$log['id'])
                            ->whereDate('punched_at', $punchTime->toDateString())
                            ->orderBy('punched_at', 'desc')
                            ->first();
                        $status = ($lastLog && $lastLog->status === 0) ? 1 : 0;
                    }

                    $record = AttendanceLog::firstOrCreate([
                        'pin' => (string) $log['id'],
                        'punched_at' => $punchTime,
                    ], [
                        'device_id' => $device->id,
                        'status' => $status,
                        'verify_type' => (int) ($log['type'] ?? 1),
                        'raw_data' => $log,
                    ]);

                    if ($record->wasRecentlyCreated) {
                        $imported++;
                    }
                }

                $device->update([
                    'status' => 'online',
                    'last_activity_at' => now(),
                    'last_sync_at' => now(),
                    'att_stamp' => (int) $device->att_stamp + $imported,
                ]);

                $this->info("Device {$device->name}: Imported {$imported} new punches from " . count($logs) . " records.");
            } catch (\Throwable $e) {
                Log::error("Error polling device {$device->name}: " . $e->getMessage());
                $this->error("Failed to poll device {$device->name}: " . $e->getMessage());
            }
        }
    }
}
