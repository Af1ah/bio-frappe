<?php

namespace App\Services\DeviceGateway;

use App\Models\Device;
use App\Models\DeviceBinding;
use App\Models\DeviceCommand;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoAdmsGateway implements DeviceGateway
{
    public function queueCommand(Device $device, DeviceCommand $command): void
    {
        $binding = DeviceBinding::query()
            ->where('serial_number', $device->serial_number)
            ->where('is_active', true)
            ->first();

        if (! $binding) {
            throw new RuntimeException('The device is not registered with the ADMS gateway.');
        }

        $response = Http::baseUrl((string) config('services.device_gateway.url'))
            ->withToken((string) config('services.device_gateway.token'))
            ->post('/internal/v1/commands', [
                'serial_number' => $device->serial_number,
                'command_id' => (string) $command->id,
                'command' => $command->command_content,
            ]);

        if (! $response->accepted()) {
            throw new RuntimeException('The ADMS gateway did not durably accept the command.');
        }
    }
}
