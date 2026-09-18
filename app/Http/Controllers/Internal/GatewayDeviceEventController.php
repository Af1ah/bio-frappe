<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\DeviceBinding;
use App\Models\GatewayReceipt;
use App\Services\Attendance\GatewayEventIngestor;
use App\Services\DeviceGateway\DeviceSourceVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class GatewayDeviceEventController extends Controller
{
    public function store(Request $request, GatewayEventIngestor $ingestor, DeviceSourceVerifier $sourceVerifier): JsonResponse
    {
        $maxEvents = config('device-gateway.max_events_per_batch');
        $data = $request->validate([
            'receipt' => ['required', 'array'],
            'receipt.id' => ['required', 'uuid'],
            'receipt.device_binding_id' => ['required', 'uuid'],
            'receipt.binding_version' => ['required', 'integer', 'min:1'],
            'receipt.serial_number' => ['required', 'string', 'max:255'],
            'receipt.source_ip' => ['required', 'ip'],
            'receipt.payload_digest' => ['required', 'string', 'size:64'],
            'receipt.payload' => ['present', 'string'],
            'receipt.query' => ['present', 'array'],
            'receipt.endpoint' => ['required', 'string', 'max:255'],
            'receipt.method' => ['required', Rule::in(['GET', 'POST'])],
            'receipt.received_at' => ['required', 'date'],
            'events' => ['present', 'array', 'max:'.$maxEvents],
            'events.*.event_id' => ['required', 'uuid'],
            'events.*.receipt_id' => ['required', 'uuid'],
            'events.*.binding_version' => ['required', 'integer', 'min:1'],
            'events.*.source_line' => ['required', 'integer', 'min:1'],
            'events.*.payload_digest' => ['required', 'string', 'size:64'],
            'events.*.pin' => ['required', 'string', 'max:255'],
            'events.*.occurred_at' => ['required', 'date'],
            'events.*.original_timestamp' => ['required', 'string', 'max:255'],
            'events.*.punch_code' => ['required', 'integer', 'between:0,255'],
            'events.*.verification_code' => ['required', 'integer', 'between:0,255'],
            'events.*.work_code' => ['nullable', 'integer', 'between:0,255'],
            'events.*.classification' => ['nullable', 'string', 'max:100'],
            'users' => ['present', 'array', 'max:1000'],
            'users.*.pin' => ['required', 'string', 'max:255'],
            'users.*.name' => ['nullable', 'string', 'max:255'],
            'users.*.privilege' => ['required', 'integer'],
            'users.*.card' => ['nullable', 'string', 'max:255'],
            'command_updates' => ['present', 'array', 'max:100'],
            'command_updates.*.command_id' => ['required', 'uuid'],
            'command_updates.*.protocol_command_id' => ['required', 'integer', 'min:1'],
            'command_updates.*.state' => ['required', Rule::in(['delivered', 'acknowledged', 'failed', 'outcome_unknown'])],
            'command_updates.*.result_code' => ['nullable', 'integer'],
            'command_updates.*.response' => ['nullable', 'string', 'max:2048'],
            'device_info' => ['present', 'array'],
        ]);

        $binding = DeviceBinding::query()
            ->whereKey($data['receipt']['device_binding_id'])
            ->where('is_active', true)
            ->firstOrFail();

        if ($binding->ownership_version !== $data['receipt']['binding_version'] || $binding->serial_number !== $data['receipt']['serial_number']) {
            abort(409, 'Invalid request: the device registration version is inactive.');
        }
        if (! $sourceVerifier->matches($binding, $data['receipt']['source_ip'])) {
            abort(403, 'Invalid request: unauthorized device IP address.');
        }
        $rawPayload = base64_decode($data['receipt']['payload'], true);
        if ($rawPayload === false) {
            abort(422, 'Invalid request: payload is not valid base64.');
        }

        $receipt = DB::transaction(function () use ($binding, $data, $rawPayload): GatewayReceipt {
            $receipt = GatewayReceipt::firstOrCreate(
                ['id' => $data['receipt']['id']],
                [
                    'device_binding_id' => $binding->id,
                    'tenant_id' => $binding->tenant_id,
                    'binding_version' => $binding->ownership_version,
                    'payload_digest' => $data['receipt']['payload_digest'],
                    'endpoint' => $data['receipt']['endpoint'],
                    'method' => $data['receipt']['method'],
                    'source_ip' => $data['receipt']['source_ip'],
                    'query' => $data['receipt']['query'],
                    'raw_payload' => $rawPayload,
                    'received_at' => $data['receipt']['received_at'],
                    'routing_metadata' => ['ingress' => 'go_adms_gateway'],
                ],
            );
            if ($receipt->device_binding_id !== $binding->id || $receipt->payload_digest !== $data['receipt']['payload_digest']) {
                abort(409, 'Invalid request: receipt ID was already used for different content.');
            }

            return $receipt;
        });

        foreach ($data['events'] as $event) {
            if ($event['receipt_id'] !== $receipt->id || $event['payload_digest'] !== $receipt->payload_digest) {
                abort(422, 'Invalid request: event references a different receipt or digest.');
            }
        }

        $results = $ingestor->ingestBatch($binding, $data['events'], $data['users'], $data['command_updates'], $data['device_info']);
        $receipt->update(['status' => 'accepted', 'delivered_at' => now()]);
        $bindingUpdate = ['last_source_ip' => $data['receipt']['source_ip'], 'last_seen_at' => now()];
        if ($binding->source_cidr === 'auto' || blank($binding->source_cidr)) {
            $bindingUpdate['source_cidr'] = $data['receipt']['source_ip'];
        }
        $binding->update($bindingUpdate);

        return response()->json(['receipt_id' => $receipt->id, 'result' => $results], 202);
    }
}
