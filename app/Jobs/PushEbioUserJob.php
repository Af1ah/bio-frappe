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

class PushEbioUserJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $organisation;
    public $userId;
    public $location;

    public function __construct(Organisation $organisation, int $userId, string $location = '')
    {
        $this->organisation = $organisation;
        $this->userId = $userId;
        $this->location = $location;
    }

    public function handle(EbioSoapService $service, ZktecoService $zkService): void
    {
        tenancy()->initialize($this->organisation);
        $user = User::find($this->userId);
        
        if (!$user) {
            return;
        }

        // Push directly to connected ZKTeco devices
        $devices = Device::whereNotNull('ip_address')->get();
        foreach ($devices as $device) {
            $port = $device->port ?: 4370;
            $uid = (int) ($user->pin ?: $user->id);
            try {
                $zkService->setUser(
                    $device->ip_address,
                    $port,
                    $uid,
                    $user->pin ?: $user->id,
                    $user->name ?: 'User ' . $uid,
                    $user->device_password ?? '',
                    (int) ($user->privilege ?? 0),
                    (int) ($user->card_number ?? 0)
                );
            } catch (\Exception $e) {
                Log::warning("Failed to push user to ZKTeco device {$device->ip_address}: " . $e->getMessage());
            }
        }

        // Fallback or mirror to eBioServer if configured
        if ($this->organisation->ebio_url) {
            try {
                $service->pushUser($this->organisation, $user, $this->location);
            } catch (\Exception $e) {
                Log::warning("eBio push user skipped: " . $e->getMessage());
            }
        }
    }
}
