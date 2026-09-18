<?php

namespace App\Enums;

use App\Models\Device;

enum DeviceTransport: string
{
    case Ebio = 'ebio';
    case Direct = 'direct';
    case Adms = 'adms';

    public static function forDevice(Device $device): self
    {
        $configured = data_get($device->options, 'connection_mode');
        if (is_string($configured) && ($transport = self::tryFrom($configured))) {
            return $transport;
        }

        return data_get($device->options, 'adms_enabled', false) ? self::Direct : self::Ebio;
    }
}
