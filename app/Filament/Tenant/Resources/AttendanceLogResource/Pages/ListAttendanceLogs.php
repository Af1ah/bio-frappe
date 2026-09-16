<?php

namespace App\Filament\Tenant\Resources\AttendanceLogResource\Pages;

use Filament\Resources\Pages\ListRecords;
use App\Filament\Tenant\Resources\DeviceResource;
use App\Filament\Tenant\Resources\AttendanceLogResource;

class ListAttendanceLogs extends ListRecords
{
    protected static string $resource = AttendanceLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('fetchDirectDeviceLogs')
                ->label('Fetch Device Logs')
                ->icon('heroicon-o-document-arrow-down')
                ->visible(fn (): bool => DeviceResource::directDeviceOptions() !== [])
                ->form([
                    \Filament\Forms\Components\Select::make('device_id')
                        ->label('Device')
                        ->options(fn (): array => DeviceResource::directDeviceOptions())
                        ->searchable()
                        ->required(),
                ])
                ->action(function (array $data): void {
                    \App\Jobs\DirectDeviceDataSyncJob::dispatch(tenant(), (int) $data['device_id'], 'logs');

                    \Filament\Notifications\Notification::make()
                        ->title('Log fetch queued')
                        ->body('Attendance logs will be fetched from the selected device.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
