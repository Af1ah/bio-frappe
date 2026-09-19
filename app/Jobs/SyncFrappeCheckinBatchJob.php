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

class SyncFrappeCheckinBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $logIds;
    public ?string $organisationId;
    public int $concurrency;

    /**
     * Create a new job instance.
     *
     * @param array<int> $logIds
     * @param Organisation|null $organisation
     * @param int $concurrency
     */
    public function __construct(array $logIds, ?Organisation $organisation = null, int $concurrency = 10)
    {
        $this->logIds = array_values(array_filter($logIds));
        $this->organisationId = $organisation?->id ?: (tenancy()->tenant?->id ?? null);
        $this->concurrency = $concurrency;
    }

    /**
     * Execute the job.
     */
    public function handle(FrappeHrService $frappe): void
    {
        if (empty($this->logIds)) {
            return;
        }

        $initializedHere = false;
        try {
            $organisation = null;
            if (!tenancy()->initialized && $this->organisationId) {
                $organisation = Organisation::find($this->organisationId);
                if ($organisation) {
                    tenancy()->initialize($organisation);
                    $initializedHere = true;
                }
            } else {
                $organisation = tenancy()->tenant ?: ($this->organisationId ? Organisation::find($this->organisationId) : null);
            }

            $logs = AttendanceLog::whereIn('id', $this->logIds)
                ->with(['device', 'user'])
                ->get();

            if ($logs->isEmpty()) {
                return;
            }

            $start = microtime(true);
            $result = $frappe->syncAttendanceBatch($logs, $organisation, $this->concurrency);
            $duration = round(microtime(true) - $start, 2);

            Log::info("Frappe HR Batch Sync: {$result['synced']}/{$result['total']} punches synced in {$duration}s (Failed: {$result['failed']}).");
            unset($logs);
        } catch (\Throwable $e) {
            Log::error("Failed to run SyncFrappeCheckinBatchJob: " . $e->getMessage(), [
                'organisation_id' => $this->organisationId,
                'log_count' => count($this->logIds),
            ]);
            throw $e;
        } finally {
            if ($initializedHere && tenancy()->initialized) {
                tenancy()->end();
            }
        }
    }
}
