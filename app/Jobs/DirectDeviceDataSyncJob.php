<?php

namespace App\Jobs;

use App\Models\Device;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Attendance\DirectDeviceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

class DirectDeviceDataSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Direct fingerprint retrieval may require many device round trips. */
    public int $timeout = 300;

    /** @param array<int, int> $userIds */
    public function __construct(
        public Organisation $organisation,
        public int $deviceId,
        public string $operation,
        public array $userIds = [],
    ) {}

    public function handle(DirectDeviceService $service): void
    {
        tenancy()->initialize($this->organisation);

        try {
            $device = Device::findOrFail($this->deviceId);
            $result = match ($this->operation) {
                'users' => $service->syncUsersFromDevice($device),
                'logs' => $service->syncAttendanceLogs($device),
                'push_users' => $service->pushUsersToDevice($device, User::query()->whereKey($this->userIds)->get()),
                default => throw new RuntimeException("Unknown direct device operation: {$this->operation}"),
            };

            if (! $result['status']) {
                throw new RuntimeException($result['message']);
            }

            $device->update(['status' => 'online', 'last_activity_at' => now(), 'last_sync_at' => now()]);
        } finally {
            tenancy()->end();
        }
    }
}
