<?php

namespace App\Services\Attendance;

use App\Events\AttendanceReceived;
use App\Models\AttendanceLog;
use App\Models\Device;
use App\Models\DeviceBinding;
use App\Models\Organisation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GatewayEventIngestor
{
    /**
     * Store a normalized event in its bound tenant. The gateway's receipt and
     * event IDs are immutable idempotency keys; device supplied tenant fields
     * are deliberately not accepted here.
     */
    public function ingest(DeviceBinding $binding, array $event): array
    {
        if (($event['binding_version'] ?? null) !== $binding->ownership_version) {
            throw ValidationException::withMessages([
                'events' => 'The event was produced under a stale device binding.',
            ]);
        }

        $tenant = Organisation::findOrFail($binding->tenant_id);
        tenancy()->initialize($tenant);

        try {
            return DB::transaction(function () use ($binding, $event): array {
                $device = Device::query()
                    ->where('serial_number', $binding->serial_number)
                    ->first();

                if (! $device) {
                    throw ValidationException::withMessages([
                        'events' => 'The bound device does not exist in its tenant database.',
                    ]);
                }

                $record = AttendanceLog::firstOrCreate(
                    ['gateway_event_id' => $event['event_id']],
                    [
                        'gateway_receipt_id' => $event['receipt_id'],
                        'source_line' => $event['source_line'],
                        'source_digest' => $event['payload_digest'],
                        'device_id' => $device->id,
                        'pin' => (string) $event['pin'],
                        'punched_at' => Carbon::parse($event['occurred_at']),
                        'status' => (int) $event['punch_code'],
                        'verify_type' => (int) $event['verification_code'],
                        'work_code' => $event['work_code'] ?? null,
                        'raw_data' => [
                            'source' => 'go_adms_gateway',
                            'original_timestamp' => $event['original_timestamp'],
                            'classification' => $event['classification'] ?? null,
                        ],
                    ],
                );

                if ($record->wasRecentlyCreated) {
                    event(new AttendanceReceived($record, $device));
                }

                return ['event_id' => $record->gateway_event_id, 'created' => $record->wasRecentlyCreated];
            });
        } finally {
            tenancy()->end();
        }
    }
}
