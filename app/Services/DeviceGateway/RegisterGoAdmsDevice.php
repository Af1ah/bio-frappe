<?php

namespace App\Services\DeviceGateway;

use App\Models\Device;
use App\Models\DeviceBinding;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class RegisterGoAdmsDevice
{
    public function register(Device $device, string $tenantId, string $timezone = 'UTC'): DeviceBinding
    {
        $binding = DeviceBinding::query()->firstOrNew(['serial_number' => $device->serial_number]);

        if ($binding->exists && $binding->tenant_id !== $tenantId) {
            throw new RuntimeException('This serial number is already bound to another tenant.');
        }

        $binding->fill([
            'tenant_id' => $tenantId,
            'protocol' => 'adms',
            'timezone' => $timezone,
            'is_active' => true,
        ])->save();

        $response = Http::baseUrl((string) config('services.device_gateway.url'))
            ->withToken((string) config('services.device_gateway.token'))
            ->post('/internal/v1/devices', [
                'serial_number' => $binding->serial_number,
                'timezone' => $binding->timezone,
            ]);

        if (! $response->noContent()) {
            throw new RuntimeException('The ADMS gateway did not accept this device registration.');
        }

        return $binding;
    }
}
