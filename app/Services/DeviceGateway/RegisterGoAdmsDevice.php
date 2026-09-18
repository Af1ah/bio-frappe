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
            throw new RuntimeException('This device is already registered to a different company.');
        }

        $binding->fill([
            'tenant_id' => $tenantId,
            'protocol' => 'adms',
            'timezone' => $timezone,
            'source_cidr' => data_get($device->options, 'source_cidr') ?: $device->ip_address ?: 'auto',
            'capability_profile' => data_get($device->options, 'capabilities', []),
            'is_active' => true,
        ])->save();

        $response = Http::baseUrl((string) config('services.device_gateway.url'))
            ->withToken((string) config('services.device_gateway.token'))
            ->post('/internal/v1/devices', [
                'binding_id' => $binding->id,
                'tenant_id' => $binding->tenant_id,
                'serial_number' => $binding->serial_number,
                'timezone' => $binding->timezone,
                'source_cidr' => $binding->source_cidr,
                'ownership_version' => $binding->ownership_version,
                'capabilities' => (object) ($binding->capability_profile ?? []),
            ]);

        if (! $response->noContent()) {
            throw new RuntimeException('The ADMS gateway did not accept this device registration.');
        }

        return $binding;
    }
}
