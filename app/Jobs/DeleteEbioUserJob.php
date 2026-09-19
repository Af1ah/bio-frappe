<?php

namespace App\Jobs;

use App\Models\Device;
use App\Models\Organisation;
use App\Services\EbioSoapService;
use App\Services\ZktecoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DeleteEbioUserJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $organisation;
    public $employeeCode;
    public $location;

    public function __construct(Organisation $organisation, string $employeeCode, string $location = '')
    {
        $this->organisation = $organisation;
        $this->employeeCode = $employeeCode;
        $this->location = $location;
    }

    public function handle(EbioSoapService $service, ZktecoService $zkService): void
    {
        tenancy()->initialize($this->organisation);

        // Delete directly from connected ZKTeco devices
        $devices = Device::whereNotNull('ip_address')->get();
        $uid = (int) $this->employeeCode;

        foreach ($devices as $device) {
            $port = $device->port ?: 4370;
            try {
                $zkService->removeUser($device->ip_address, $port, $uid);
            } catch (\Exception $e) {
                Log::warning("Failed to remove user from ZKTeco device {$device->ip_address}: " . $e->getMessage());
            }
        }

        // Fallback or mirror to eBioServer if configured
        if ($this->organisation->ebio_url) {
            try {
                $service->deleteUser($this->organisation, $this->employeeCode, $this->location);
            } catch (\Exception $e) {
                Log::warning("eBio delete user skipped: " . $e->getMessage());
            }
        }
    }
}
