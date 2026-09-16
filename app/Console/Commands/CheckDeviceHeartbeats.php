<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\Organisation;
use App\Services\Attendance\DirectDeviceService;
use Illuminate\Console\Command;
use Throwable;

class CheckDeviceHeartbeats extends Command
{
    protected $signature = 'devices:heartbeat';

    protected $description = 'Check configured biometric devices and refresh their online status.';

    public function handle(DirectDeviceService $devices): int
    {
        $checked = 0;
        $online = 0;

        Organisation::query()->cursor()->each(function (Organisation $organisation) use ($devices, &$checked, &$online): void {
            try {
                $organisation->run(function () use ($devices, &$checked, &$online): void {
                    Device::query()->whereNotNull('ip_address')->each(function (Device $device) use ($devices, &$checked, &$online): void {
                        $checked++;
                        $result = $devices->testConnection($device);

                        if ($result['status']) {
                            $device->update(['status' => 'online', 'last_activity_at' => now()]);
                            $online++;

                            return;
                        }

                        $device->update(['status' => 'offline']);
                    });
                });
            } catch (Throwable $exception) {
                $this->warn("Skipped {$organisation->shortname}: {$exception->getMessage()}");
            }
        });

        $this->info("Heartbeat complete: {$online}/{$checked} device(s) online.");

        return self::SUCCESS;
    }
}
