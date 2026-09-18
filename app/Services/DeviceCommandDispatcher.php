<?php

namespace App\Services;

use App\Enums\DeviceTransport;
use App\Jobs\DirectDeviceCommandJob;
use App\Jobs\EbioDeviceCommandJob;
use App\Jobs\GatewayDeviceCommandJob;
use App\Models\Device;
use App\Models\DeviceCommand;

class DeviceCommandDispatcher
{
    public function dispatch(Device $device, DeviceCommand $command): void
    {
        $transport = DeviceTransport::forDevice($device);
        $command->update(['transport' => $transport->value]);

        match ($transport) {
            DeviceTransport::Ebio => EbioDeviceCommandJob::dispatch(tenant(), $device->serial_number, $command->command_type, $command->id),
            DeviceTransport::Direct => DirectDeviceCommandJob::dispatch(tenant(), $device->id, $command->id),
            DeviceTransport::Adms => GatewayDeviceCommandJob::dispatch(tenant(), $device->id, $command->id),
        };
    }
}
