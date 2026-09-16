<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\DeviceBinding;
use App\Models\GatewayReceipt;
use App\Services\Attendance\GatewayEventIngestor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class GatewayDeviceEventController extends Controller
{
    public function store(Request $request, GatewayEventIngestor $ingestor): JsonResponse
    {
        $maxEvents = config('device-gateway.max_events_per_batch');
        $data = $request->validate([
            'receipt' => ['required', 'array'],
            'receipt.id' => ['required', 'uuid'],
            'receipt.device_binding_id' => ['required', 'uuid'],
            'receipt.binding_version' => ['required', 'integer', 'min:1'],
            'receipt.payload_digest' => ['required', 'string', 'size:64'],
            'receipt.endpoint' => ['required', 'string', 'max:255'],
            'receipt.method' => ['required', Rule::in(['GET', 'POST'])],
            'receipt.received_at' => ['required', 'date'],
            'events' => ['required', 'array', 'max:'.$maxEvents],
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
        ]);

        $binding = DeviceBinding::query()
            ->whereKey($data['receipt']['device_binding_id'])
            ->where('is_active', true)
            ->firstOrFail();

        if ($binding->ownership_version !== $data['receipt']['binding_version']) {
            abort(409, 'The receipt belongs to an inactive device binding version.');
        }

        $receipt = DB::transaction(function () use ($binding, $data): GatewayReceipt {
            return GatewayReceipt::firstOrCreate(
                ['id' => $data['receipt']['id']],
                [
                    'device_binding_id' => $binding->id,
                    'tenant_id' => $binding->tenant_id,
                    'binding_version' => $binding->ownership_version,
                    'payload_digest' => $data['receipt']['payload_digest'],
                    'endpoint' => $data['receipt']['endpoint'],
                    'method' => $data['receipt']['method'],
                    'received_at' => $data['receipt']['received_at'],
                    'routing_metadata' => ['ingress' => 'go_adms_gateway'],
                ],
            );
        });

        $results = [];
        foreach ($data['events'] as $event) {
            if ($event['receipt_id'] !== $receipt->id) {
                abort(422, 'An event references a different receipt.');
            }
            $results[] = $ingestor->ingest($binding, $event);
        }

        $receipt->update(['status' => 'accepted']);

        return response()->json(['receipt_id' => $receipt->id, 'events' => $results], 202);
    }
}
