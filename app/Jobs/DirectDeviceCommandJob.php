<?php

namespace App\Jobs;

use App\Models\Device;
use App\Models\DeviceCommand;
use App\Models\Organisation;
use App\Services\Attendance\DirectDeviceCommandService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class DirectDeviceCommandJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Organisation $organisation,
        public int $deviceId,
        public int $commandId,
    ) {}

    public function handle(DirectDeviceCommandService $service): void
    {
        tenancy()->initialize($this->organisation);
        try {
            $command = DeviceCommand::findOrFail($this->commandId);
            $device = Device::findOrFail($this->deviceId);
            $command->markAsSent();
            $result = $service->execute($device, $command->command_type);

            if ($result['status']) {
                $command->markAsAcknowledged($result['message']);

                return;
            }

            $command->markAsFailed($result['message']);
        } catch (Throwable $exception) {
            DeviceCommand::find($this->commandId)?->markAsFailed('Direct command failed.');

            throw $exception;
        } finally {
            tenancy()->end();
        }
    }
}
