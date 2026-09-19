<?php

namespace App\Jobs;

use App\Models\AttendanceLog;
use App\Models\Device;
use App\Models\DeviceCommand;
use App\Models\Organisation;
use App\Services\EbioSoapService;
use App\Services\ZktecoService;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EbioDeviceCommandJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $organisation;
    public $serialNumber;
    public $commandType;
    public $commandId;

    public function __construct(Organisation $organisation, string $serialNumber, string $commandType, ?int $commandId = null)
    {
        $this->organisation = $organisation;
        $this->serialNumber = $serialNumber;
        $this->commandType = $commandType;
        $this->commandId = $commandId;
    }

    public function handle(EbioSoapService $service, ZktecoService $zkService): void
    {
        tenancy()->initialize($this->organisation);
        $commandModel = null;
        if ($this->commandId) {
            $commandModel = DeviceCommand::find($this->commandId);
            if ($commandModel) $commandModel->markAsSent();
        }

        $device = Device::where('serial_number', $this->serialNumber)->first();

        try {
            $success = false;
            $responseMessage = null;

            // Step 1: If device has an IP address, attempt direct ZKTeco execution
            if ($device && $device->ip_address) {
                $port = $device->port ?: 4370;

                switch ($this->commandType) {
                    case 'test_connection':
                    case 'ping':
                        $success = $zkService->testConnection($device->ip_address, $port);
                        if ($success) {
                            $zkService->syncDeviceState($device);
                            $responseMessage = "Device online at {$device->ip_address}:{$port}. State synchronized.";
                        } else {
                            $device->update(['status' => 'offline']);
                            $responseMessage = "Device unreachable at {$device->ip_address}:{$port}";
                        }
                        break;

                    case 'fetch_attendance':
                        $logs = $zkService->getAttendanceLogs($device->ip_address, $port);
                        $imported = 0;
                        foreach ($logs as $log) {
                            if (empty($log['id']) || empty($log['timestamp'])) {
                                continue;
                            }

                            $status = (int) ($log['state'] ?? 0);
                            if ($device->punch_behavior === 'always_in') {
                                $status = 0;
                            } elseif ($device->punch_behavior === 'always_out') {
                                $status = 1;
                            } elseif ($device->punch_behavior === 'auto') {
                                $lastLog = AttendanceLog::where('pin', (string)$log['id'])
                                    ->whereDate('punched_at', Carbon::parse($log['timestamp'])->toDateString())
                                    ->orderBy('punched_at', 'desc')
                                    ->first();
                                $status = ($lastLog && $lastLog->status === 0) ? 1 : 0;
                            }

                            try {
                                $record = AttendanceLog::firstOrCreate([
                                    'pin' => (string) $log['id'],
                                    'punched_at' => Carbon::parse($log['timestamp']),
                                ], [
                                    'device_id' => $device->id,
                                    'status' => $status,
                                    'verify_type' => 1,
                                    'raw_data' => $log,
                                ]);

                                if ($record->wasRecentlyCreated) {
                                    $imported++;
                                }
                            } catch (\Exception $e) {
                                Log::warning("Duplicate/error storing punch: " . $e->getMessage());
                            }
                        }

                        $device->update([
                            'status' => 'online',
                            'last_activity_at' => now(),
                            'last_sync_at' => now(),
                            'att_stamp' => (int) $device->att_stamp + $imported,
                        ]);

                        $success = true;
                        $responseMessage = "Fetched " . count($logs) . " records ({$imported} new).";
                        break;

                    case 'reboot':
                        $success = $zkService->rebootDevice($device->ip_address, $port);
                        $responseMessage = $success ? 'Device reboot signal sent' : 'Reboot failed';
                        break;

                    case 'clear_logs':
                        $success = $zkService->clearAttendanceLogs($device->ip_address, $port);
                        $responseMessage = $success ? 'Device attendance logs cleared' : 'Clear logs failed';
                        break;

                    case 'unlock_door':
                        $success = $zkService->unlockDoor($device->ip_address, $port);
                        $responseMessage = $success ? 'Door unlocked successfully' : 'Unlock door failed';
                        break;

                    case 'reset_transaction_stamp':
                        $device->update(['att_stamp' => 0]);
                        $success = true;
                        $responseMessage = 'Transaction stamp reset to 0';
                        break;

                    case 'reset_op_stamp':
                        $device->update(['op_stamp' => 0]);
                        $success = true;
                        $responseMessage = 'OP stamp reset to 0';
                        break;

                    case 'test_voice':
                        $success = $zkService->testVoice($device->ip_address, $port);
                        $responseMessage = $success ? 'Voice test triggered' : 'Voice test failed';
                        break;

                    case 'sync_time':
                        $success = $zkService->setDeviceTime($device->ip_address, $port);
                        $responseMessage = $success ? 'Device time synchronized' : 'Time sync failed';
                        break;

                    case 'shutdown':
                        $success = $zkService->shutdownDevice($device->ip_address, $port);
                        $responseMessage = $success ? 'Device shutdown signal sent' : 'Shutdown failed';
                        break;
                }

                if ($success) {
                    if ($commandModel) {
                        $commandModel->markAsAcknowledged($responseMessage ?: 'Executed via direct ZKTeco library');
                    }
                    return;
                }
            }

            // Step 2: Fallback to eBioServer SOAP if direct command wasn't executed or device has no IP
            if ($this->organisation->ebio_url) {
                switch ($this->commandType) {
                    case 'reboot':
                        $success = $service->rebootDevice($this->organisation, $this->serialNumber);
                        break;
                    case 'clear_logs':
                        $success = $service->clearDeviceLogs($this->organisation, $this->serialNumber);
                        break;
                    case 'reset_transaction_stamp':
                        $success = $service->resetTransactionStamp($this->organisation, $this->serialNumber);
                        break;
                    case 'reset_op_stamp':
                        $success = $service->resetOPStamp($this->organisation, $this->serialNumber);
                        break;
                    case 'unlock_door':
                        $success = $service->unlockDoor($this->organisation, $this->serialNumber);
                        break;
                    default:
                        Log::warning("Unknown device command type for eBio: {$this->commandType}");
                        break;
                }

                if ($commandModel) {
                    if ($success) {
                        $commandModel->markAsAcknowledged($responseMessage ?: 'Executed via eBioServer SOAP');
                    } else {
                        $commandModel->markAsFailed('eBioServer API returned false');
                    }
                }
            } else {
                if ($commandModel) {
                    $commandModel->markAsFailed($responseMessage ?: ($device && $device->ip_address ? 'Direct ZKTeco command failed' : 'No reachable IP or eBio URL'));
                }
            }
        } catch (Exception $e) {
            Log::error("Failed to execute device command {$this->commandType} on {$this->serialNumber}: " . $e->getMessage());
            if ($commandModel) {
                $commandModel->markAsFailed($e->getMessage());
            }
            $this->fail($e);
        }
    }
}
