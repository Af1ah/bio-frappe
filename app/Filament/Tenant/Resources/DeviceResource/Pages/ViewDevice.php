<?php

namespace App\Filament\Tenant\Resources\DeviceResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use App\Filament\Tenant\Resources\DeviceResource;

class ViewDevice extends ViewRecord
{
    protected static string $resource = DeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('unlockDoor')
                ->label('Unlock Door')
                ->icon('heroicon-o-lock-open')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Unlock Door')
                ->modalDescription('Send an unlock command to the door controller.')
                ->action(function () {
                    $cmd = app(\App\Services\Attendance\DeviceCommandBuilder::class)->unlockDoor($this->record);
                    \Filament\Notifications\Notification::make()
                        ->title($cmd->status === 'failed' ? 'Unlock Failed' : 'Door Unlocked')
                        ->body($cmd->response ?: 'Unlock command processed.')
                        ->status($cmd->status === 'failed' ? 'danger' : 'success')
                        ->send();
                }),
            Actions\Action::make('lockDoor')
                ->label('Lock Door')
                ->icon('heroicon-o-lock-closed')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Lock Door')
                ->modalDescription('Send a lock command to the door controller.')
                ->action(function () {
                    $cmd = app(\App\Services\Attendance\DeviceCommandBuilder::class)->lockDoor($this->record);
                    \Filament\Notifications\Notification::make()
                        ->title($cmd->status === 'failed' ? 'Lock Failed' : 'Door Locked')
                        ->body($cmd->response ?: 'Lock command processed.')
                        ->status($cmd->status === 'failed' ? 'danger' : 'success')
                        ->send();
                }),
            Actions\Action::make('normalizeDoor')
                ->label('Set to Normal')
                ->icon('heroicon-o-shield-check')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Set Door to Normal State')
                ->modalDescription('Return the door to standard access evaluation mode.')
                ->action(function () {
                    $cmd = app(\App\Services\Attendance\DeviceCommandBuilder::class)->normalizeDoor($this->record);
                    \Filament\Notifications\Notification::make()
                        ->title($cmd->status === 'failed' ? 'Command Failed' : 'Door Normalized')
                        ->body($cmd->response ?: 'Door returned to normal mode.')
                        ->status($cmd->status === 'failed' ? 'danger' : 'success')
                        ->send();
                }),
            Actions\Action::make('enableEnrollment')
                ->label('Enable Device Enrollment')
                ->icon('heroicon-o-finger-print')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Enable Enrollment Mode on Device')
                ->modalDescription('Send API command to activate on-device biometric enrollment mode (enroll-on-device=1).')
                ->visible(fn () => $this->record->vendor === 'matrix')
                ->action(function () {
                    $cmd = app(\App\Services\Attendance\DeviceCommandBuilder::class)->enableEnrollment($this->record);
                    \Filament\Notifications\Notification::make()
                        ->title($cmd->status === 'failed' ? 'Failed to Enable Enrollment' : 'Enrollment Enabled')
                        ->body($cmd->response ?: 'Device enrollment mode activated via API.')
                        ->status($cmd->status === 'failed' ? 'danger' : 'success')
                        ->send();
                }),
            Actions\Action::make('checkStatus')
                ->label('Check Status')
                ->icon('heroicon-o-signal')
                ->color('success')
                ->action(function () {
                    if ($this->record->vendor === 'matrix') {
                        $result = app(\App\Services\Attendance\MatrixDeviceService::class)->checkConnection($this->record);
                        if ($result['success']) {
                            $this->record->update([
                                'status' => 'online',
                                'model' => $result['model'] ?? ($this->record->model ?: 'Matrix COSEC'),
                                'last_activity_at' => now(),
                            ]);
                            \Filament\Notifications\Notification::make()
                                ->title('Device Online')
                                ->body($result['message'])
                                ->success()
                                ->send();
                        } else {
                            $this->record->update(['status' => 'offline']);
                            \Filament\Notifications\Notification::make()
                                ->title('Device Connection Failed')
                                ->body($result['message'])
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    } else {
                        app(\App\Services\Attendance\DeviceCommandBuilder::class)->checkConnection($this->record);
                        \Filament\Notifications\Notification::make()
                            ->title('Check Command Queued')
                            ->body('Connection check command queued.')
                            ->success()
                            ->send();
                    }
                }),
            Actions\EditAction::make(),
        ];
    }
}
