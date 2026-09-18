<?php

namespace App\Services\DeviceGateway;

use App\Models\DeviceBinding;

class DeviceSourceVerifier
{
    public function matches(DeviceBinding $binding, string $sourceIp): bool
    {
        $sourceIp = trim($sourceIp);
        if (! filter_var($sourceIp, FILTER_VALIDATE_IP)) {
            return false;
        }

        if ($binding->source_cidr === 'auto') {
            return true;
        }

        if (blank($binding->source_cidr)) {
            return false;
        }

        [$network, $prefix] = array_pad(explode('/', trim((string) $binding->source_cidr), 2), 2, null);
        $network = trim($network);
        $ipBinary = @inet_pton(trim($sourceIp));
        $networkBinary = @inet_pton($network);
        if ($ipBinary === false || $networkBinary === false || strlen($ipBinary) !== strlen($networkBinary)) {
            return false;
        }

        $maxBits = strlen($ipBinary) * 8;
        $prefix ??= str_contains($network, ':') ? '128' : '32';
        $prefix = trim((string) $prefix);
        if (! ctype_digit($prefix) || (int) $prefix < 0 || (int) $prefix > $maxBits) {
            return false;
        }

        $bits = (int) $prefix;
        $bytes = intdiv($bits, 8);
        if (substr($ipBinary, 0, $bytes) !== substr($networkBinary, 0, $bytes)) {
            return false;
        }
        $remainder = $bits % 8;
        if ($remainder === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainder)) & 0xFF;

        return (ord($ipBinary[$bytes]) & $mask) === (ord($networkBinary[$bytes]) & $mask);
    }
}
