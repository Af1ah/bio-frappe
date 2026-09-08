<?php

namespace App\Filament\Tenant\Resources\DeviceResource\Pages;

use App\Filament\Tenant\Resources\DeviceResource;
use App\Services\Attendance\MatrixDeviceService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateDevice extends CreateRecord
{
    protected static string $resource = DeviceResource::class;

    protected function afterCreate(): void
    {
        if ($this->record->vendor === 'matrix') {
            $result = app(MatrixDeviceService::class)->checkConnection($this->record);
            if ($result['success']) {
                $this->record->update([
                    'status' => 'online',
                    'model' => $result['model'] ?? ($this->record->model ?: 'Matrix COSEC'),
                    'last_activity_at' => now(),
                ]);
                Notification::make()
                    ->title('Matrix Device Connected')
                    ->body($result['message'])
                    ->success()
                    ->send();
            } else {
                $this->record->update(['status' => 'offline']);
                Notification::make()
                    ->title('Device Saved with Connection Error')
                    ->body($result['message'])
                    ->warning()
                    ->persistent()
                    ->send();
            }
        }
    }
}
