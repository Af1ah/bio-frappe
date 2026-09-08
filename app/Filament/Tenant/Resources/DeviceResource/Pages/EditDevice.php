<?php

namespace App\Filament\Tenant\Resources\DeviceResource\Pages;

use App\Filament\Tenant\Resources\DeviceResource;
use App\Services\Attendance\MatrixDeviceService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditDevice extends EditRecord
{
    protected static string $resource = DeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('checkStatus')
                ->label('Test Connection')
                ->icon('heroicon-o-signal')
                ->color('success')
                ->action(function () {
                    if ($this->record->vendor === 'matrix') {
                        $result = app(MatrixDeviceService::class)->checkConnection($this->record);
                        if ($result['success']) {
                            $this->record->update([
                                'status' => 'online',
                                'model' => $result['model'] ?? ($this->record->model ?: 'Matrix COSEC'),
                                'last_activity_at' => now(),
                            ]);
                            Notification::make()
                                ->title('Device Online')
                                ->body($result['message'])
                                ->success()
                                ->send();
                        } else {
                            $this->record->update(['status' => 'offline']);
                            Notification::make()
                                ->title('Connection Failed')
                                ->body($result['message'])
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    }
                })
                ->visible(fn () => $this->record->vendor === 'matrix'),
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
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
                    ->title('Device Verified Online')
                    ->body($result['message'])
                    ->success()
                    ->send();
            } else {
                $this->record->update(['status' => 'offline']);
                Notification::make()
                    ->title('Device Connection Warning')
                    ->body($result['message'])
                    ->warning()
                    ->persistent()
                    ->send();
            }
        }
    }
}
