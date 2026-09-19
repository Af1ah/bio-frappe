<?php

namespace App\Jobs;

use App\Models\Device;
use App\Models\Organisation;
use App\Models\User;
use App\Services\EbioSoapService;
use App\Services\ZktecoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncEbioUsersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $organisation;
    public ?int $deviceId;

    public function __construct(Organisation $organisation, ?int $deviceId = null)
    {
        $this->organisation = $organisation;
        $this->deviceId = $deviceId;
    }

    public function handle(EbioSoapService $service, ZktecoService $zkService): void
    {
        tenancy()->initialize($this->organisation);

        $devicesQuery = Device::whereNotNull('ip_address');
        if ($this->deviceId) {
            $devicesQuery->where('id', $this->deviceId);
        }
        $devices = $devicesQuery->get();

        foreach ($devices as $device) {
            $port = $device->port ?: 4370;
            try {
                $users = $zkService->getUsers($device->ip_address, $port);
                foreach ($users as $u) {
                    if (empty($u['userid'])) {
                        continue;
                    }
                    User::updateOrCreate([
                        'pin' => (string) $u['userid'],
                    ], [
                        'name' => !empty($u['name']) ? $u['name'] : 'User ' . $u['userid'],
                        'card_number' => !empty($u['cardno']) ? (string) $u['cardno'] : null,
                        'privilege' => (int) ($u['role'] ?? 0),
                        'is_enabled' => true,
                    ]);
                }

                $device->update([
                    'status' => 'online',
                    'last_activity_at' => now(),
                    'last_sync_at' => now(),
                ]);
            } catch (\Exception $e) {
                Log::warning("Failed to sync users from ZKTeco device {$device->ip_address}: " . $e->getMessage());
            }
        }

        // Fallback or mirror to eBioServer if configured (only when not targeting a specific direct device)
        if (!$this->deviceId && $this->organisation->ebio_url) {
            try {
                $service->syncUsers($this->organisation);
            } catch (\Exception $e) {
                Log::warning("eBio sync users skipped: " . $e->getMessage());
            }
        }
    }
}
