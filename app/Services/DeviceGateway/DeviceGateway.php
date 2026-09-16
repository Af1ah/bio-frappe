<?php

namespace App\Services\DeviceGateway;

use App\Models\Device;
use App\Models\DeviceCommand;

interface DeviceGateway
{
    /** Queue a command; a successful return only confirms durable acceptance. */
    public function queueCommand(Device $device, DeviceCommand $command): void;
}
