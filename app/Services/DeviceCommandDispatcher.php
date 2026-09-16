<?php

namespace App\Services;

use App\Jobs\DirectDeviceCommandJob;
use App\Jobs\EbioDeviceCommandJob;
use App\Models\Device;
use App\Models\DeviceCommand;

class DeviceCommandDispatcher
{
    public function dispatch(Device $device, DeviceCommand $command): void
    {
        if ((bool) data_get($device->options, 'adms_enabled', false)) {
            DirectDeviceCommandJob::dispatch(tenant(), $device->id, $command->id);

            return;
        }

        EbioDeviceCommandJob::dispatch(tenant(), $device->serial_number, $command->command_type, $command->id);
    }
}
