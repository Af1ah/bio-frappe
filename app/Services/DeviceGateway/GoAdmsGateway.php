<?php

namespace App\Services\DeviceGateway;

use App\Models\Device;
use App\Models\DeviceBinding;
use App\Models\DeviceCommand;
use App\Models\GatewayCommand;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class GoAdmsGateway implements DeviceGateway
{
    public function __construct(private readonly AdmsCommandPayloadBuilder $payloadBuilder) {}

    public function queueCommand(Device $device, DeviceCommand $command): void
    {
        $tenantId = (string) tenant('id');
        $binding = DeviceBinding::query()
            ->where('serial_number', $device->serial_number)
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->first();

        if (! $binding) {
            throw new RuntimeException('The device is not registered with the gateway for this company.');
        }

        $externalId = $command->external_id ?: (string) Str::uuid();
        $payload = $this->payloadBuilder->build($command);

        DB::connection(config('tenancy.database.central_connection'))->transaction(function () use ($binding, $command, $device, $externalId, $payload, $tenantId): void {
            GatewayCommand::query()->firstOrCreate(['id' => $externalId], [
                'device_binding_id' => $binding->id,
                'tenant_id' => $tenantId,
                'tenant_device_id' => $device->id,
                'tenant_command_id' => $command->id,
                'command_type' => $command->command_type,
                'command' => $payload,
                'state' => 'requested',
                'expires_at' => now()->addDay(),
            ]);
        }, 3);

        $command->update(['external_id' => $externalId, 'delivery_status' => 'accepting']);
        $response = Http::baseUrl((string) config('services.device_gateway.url'))
            ->withToken((string) config('services.device_gateway.token'))
            ->timeout(10)
            ->retry(2, 250)
            ->post('/internal/v1/commands', [
                'binding_id' => $binding->id,
                'serial_number' => $device->serial_number,
                'tenant_id' => $tenantId,
                'tenant_device_id' => $device->id,
                'tenant_command_id' => $command->id,
                'command_id' => $externalId,
                'command' => $payload,
            ]);

        if (! $response->accepted()) {
            throw new RuntimeException('The ADMS gateway did not durably accept the command.');
        }

        $protocolId = (int) $response->json('protocol_command_id');
        GatewayCommand::query()->whereKey($externalId)->update(['protocol_command_id' => $protocolId, 'state' => 'queued']);
        $command->update([
            'status' => 'sent',
            'delivery_status' => 'queued',
            'protocol_command_id' => $protocolId,
            'sent_at' => now(),
        ]);
    }
}
