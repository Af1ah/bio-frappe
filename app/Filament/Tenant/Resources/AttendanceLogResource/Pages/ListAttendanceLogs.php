<?php

namespace App\Filament\Tenant\Resources\AttendanceLogResource\Pages;

use App\Filament\Tenant\Resources\AttendanceLogResource;
use App\Jobs\SyncFrappeCheckinJob;
use App\Models\AttendanceLog;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListAttendanceLogs extends ListRecords
{
    protected static string $resource = AttendanceLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncAllFrappe')
                ->label('Sync All to Frappe HR')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->action(function () {
                    $tenant = tenancy()->tenant;
                    $logIds = AttendanceLog::whereNull('frappe_synced_at')->pluck('id');

                    if ($logIds->isEmpty()) {
                        Notification::make()
                            ->title('All Logs Already Synced')
                            ->body('There are no pending attendance logs to push to Frappe HR.')
                            ->info()
                            ->send();
                        return;
                    }

                    foreach ($logIds->chunk(50) as $chunkIds) {
                        \App\Jobs\SyncFrappeCheckinBatchJob::dispatch($chunkIds->toArray(), $tenant);
                    }

                    Notification::make()
                        ->title("Queued {$logIds->count()} Logs for Frappe HR Sync")
                        ->body('Punches are syncing in concurrent batches.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
