<?php

namespace App\Jobs;

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

        $command = \App\Models\DeviceCommand::findOrFail($this->commandId);
        $device = \App\Models\Device::findOrFail($this->deviceId);
        $command->markAsSent();

        try {
            $result = $service->execute($device, $command->command_type);

            if ($result['status']) {
                $command->markAsAcknowledged($result['message']);

                return;
            }

            $command->markAsFailed($result['message']);
        } catch (Throwable $exception) {
            $command->markAsFailed('Direct command failed.');

            throw $exception;
        }
    }
}
