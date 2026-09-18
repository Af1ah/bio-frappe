<?php

namespace App\Jobs;

use App\Models\Device;
use App\Models\DeviceCommand;
use App\Models\Organisation;
use App\Services\DeviceGateway\GoAdmsGateway;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GatewayDeviceCommandJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [5, 30, 120];

    public function __construct(
        public Organisation $organisation,
        public int $deviceId,
        public int $commandId,
    ) {}

    public function handle(GoAdmsGateway $gateway): void
    {
        tenancy()->initialize($this->organisation);

        try {
            $device = Device::findOrFail($this->deviceId);
            $command = DeviceCommand::findOrFail($this->commandId);
            $gateway->queueCommand($device, $command);

            if ($command->command_type === 'reset_transaction_stamp') {
                $device->update(['att_stamp' => 0]);
            } elseif ($command->command_type === 'reset_op_stamp') {
                $device->update(['op_stamp' => 0]);
            }
        } catch (Throwable $exception) {
            DeviceCommand::find($this->commandId)?->markAsFailed('ADMS gateway did not accept the command.');
            throw $exception;
        } finally {
            tenancy()->end();
        }
    }
}
