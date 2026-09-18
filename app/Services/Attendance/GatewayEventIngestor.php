<?php

namespace App\Services\Attendance;

use App\Models\Device;
use App\Models\DeviceBinding;
use App\Models\DeviceCommand;
use App\Models\DeviceUser;
use App\Models\GatewayCommand;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GatewayEventIngestor
{
    /** @return array<string, int> */
    public function ingestBatch(DeviceBinding $binding, array $events, array $users, array $commandUpdates, array $deviceInfo): array
    {
        $tenant = Organisation::findOrFail($binding->tenant_id);
        tenancy()->initialize($tenant);

        try {
            $device = Device::query()->where('serial_number', $binding->serial_number)->first();
            if (! $device) {
                throw ValidationException::withMessages(['events' => 'The bound device does not exist in this company database.']);
            }

            return DB::transaction(function () use ($binding, $device, $events, $users, $commandUpdates, $deviceInfo): array {
                $now = now();
                $attendanceRows = collect($events)->map(function (array $event) use ($binding, $device, $now): array {
                    if ((int) $event['binding_version'] !== $binding->ownership_version) {
                        throw ValidationException::withMessages(['events' => 'Invalid request: event belongs to an expired device registration.']);
                    }

                    return [
                        'gateway_event_id' => $event['event_id'],
                        'gateway_receipt_id' => $event['receipt_id'],
                        'source_line' => $event['source_line'],
                        'source_digest' => $event['payload_digest'],
                        'device_id' => $device->id,
                        'pin' => (string) $event['pin'],
                        'punched_at' => Carbon::parse($event['occurred_at']),
                        'status' => (int) $event['punch_code'],
                        'verify_type' => (int) $event['verification_code'],
                        'work_code' => $event['work_code'] ?? null,
                        'raw_data' => json_encode(['source' => 'go_adms_gateway', 'original_timestamp' => $event['original_timestamp'], 'classification' => $event['classification'] ?? null], JSON_THROW_ON_ERROR),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                })->all();
                $insertedEvents = $attendanceRows === [] ? 0 : DB::table('attendance_logs')->insertOrIgnore($attendanceRows);

                $userRows = collect($users)->filter(fn (array $user): bool => filled($user['pin'] ?? null))->map(fn (array $user): array => [
                    'pin' => (string) $user['pin'],
                    'name' => filled($user['name'] ?? null) ? $user['name'] : "Device user {$user['pin']}",
                    'privilege' => (int) ($user['privilege'] ?? 0),
                    'card_number' => $user['card'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();
                if ($userRows !== []) {
                    User::query()->upsert($userRows, ['pin'], ['name', 'privilege', 'card_number', 'updated_at']);

                    $deviceUserRows = collect($users)->filter(fn (array $user): bool => filled($user['pin'] ?? null))->map(fn (array $user): array => [
                        'device_id' => $device->id,
                        'pin' => (string) $user['pin'],
                        'name' => filled($user['name'] ?? null) ? $user['name'] : "Device user {$user['pin']}",
                        'card_number' => $user['card'] ?? null,
                        'privilege' => (int) ($user['privilege'] ?? 0),
                        'last_seen_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all();

                    DeviceUser::query()->upsert($deviceUserRows, ['device_id', 'pin'], ['name', 'card_number', 'privilege', 'last_seen_at', 'updated_at']);
                }

                $updatedCommands = $this->applyCommandUpdates($binding, $commandUpdates);
                $options = $device->options ?? [];
                $options['capabilities'] = array_replace($options['capabilities'] ?? [], $deviceInfo);
                $deviceUpdate = ['status' => 'online', 'last_activity_at' => $now];
                $discoveredIp = $binding->last_source_ip ?: $binding->source_cidr;
                if (blank($device->ip_address) && filled($discoveredIp) && $discoveredIp !== 'auto') {
                    $deviceUpdate['ip_address'] = $discoveredIp;
                }
                if ((blank($options['source_cidr'] ?? null) || ($options['source_cidr'] ?? null) === 'auto') && filled($discoveredIp) && $discoveredIp !== 'auto') {
                    $options['source_cidr'] = $discoveredIp;
                }
                $deviceUpdate['options'] = $options;
                $device->update($deviceUpdate);

                return ['events_inserted' => $insertedEvents, 'users_upserted' => count($userRows), 'commands_updated' => $updatedCommands];
            }, 3);
        } finally {
            tenancy()->end();
        }
    }

    private function applyCommandUpdates(DeviceBinding $binding, array $updates): int
    {
        if ($updates === []) {
            return 0;
        }

        $gatewayCommands = GatewayCommand::query()
            ->where('device_binding_id', $binding->id)
            ->whereIn('id', collect($updates)->pluck('command_id'))
            ->get()->keyBy('id');
        $count = 0;

        foreach ($updates as $update) {
            $gatewayCommand = $gatewayCommands->get($update['command_id']);
            if (! $gatewayCommand) {
                continue;
            }
            $tenantCommand = DeviceCommand::find($gatewayCommand->tenant_command_id);
            if (! $tenantCommand || $tenantCommand->device_id !== $gatewayCommand->tenant_device_id) {
                continue;
            }
            $state = $update['state'];
            $tenantCommand->update([
                'status' => match ($state) {
                    'acknowledged' => 'acknowledged',
                    'failed' => 'failed',
                    default => 'sent',
                },
                'delivery_status' => $state,
                'protocol_command_id' => $update['protocol_command_id'],
                'result_code' => $update['result_code'] ?? null,
                'response' => $update['response'] ?? $tenantCommand->response,
                'acknowledged_at' => in_array($state, ['acknowledged', 'failed'], true) ? now() : null,
            ]);
            $gatewayCommand->update([
                'state' => $state,
                'protocol_command_id' => $update['protocol_command_id'],
                'result_code' => $update['result_code'] ?? null,
                'response' => $update['response'] ?? null,
                'delivered_at' => $state === 'delivered' ? now() : $gatewayCommand->delivered_at,
                'completed_at' => in_array($state, ['acknowledged', 'failed'], true) ? now() : null,
            ]);
            $count++;
        }

        return $count;
    }
}
