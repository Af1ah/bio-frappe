<?php

namespace App\Jobs;

use App\Models\AttendanceLog;
use App\Models\Organisation;
use App\Services\FrappeHrService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncFrappeCheckinJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $logId;
    public $organisationId;

    public function __construct(AttendanceLog $log, ?Organisation $organisation = null)
    {
        $this->logId = $log->id;
        $this->organisationId = $organisation?->id ?: (tenancy()->tenant?->id ?? null);
    }

    public function handle(FrappeHrService $frappe): void
    {
        $organisation = null;
        if ($this->organisationId) {
            $organisation = Organisation::find($this->organisationId);
            if ($organisation) {
                tenancy()->initialize($organisation);
            }
        }

        $log = AttendanceLog::find($this->logId);
        if (!$log) {
            return;
        }

        try {
            $result = $frappe->syncAttendanceCheckin($log, $organisation);
            if (!empty($result['success'])) {
                Log::info("Successfully pushed punch for PIN {$log->pin} to Frappe HR.");
            } else {
                Log::warning("Frappe HR sync notice for PIN {$log->pin}: " . ($result['error'] ?? 'Skipped'));
            }
        } catch (\Exception $e) {
            Log::error("Failed to sync checkin to Frappe HR: " . $e->getMessage());
        }
    }
}
