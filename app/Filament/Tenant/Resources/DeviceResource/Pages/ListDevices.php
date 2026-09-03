<?php

namespace App\Filament\Tenant\Resources\DeviceResource\Pages;

use App\Filament\Tenant\Resources\DeviceResource;
use App\Models\Device;
use App\Services\Attendance\DeviceCommandBuilder;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListDevices extends ListRecords
{
    protected static string $resource = DeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('runCommand')
                ->label('Run Command')
                ->icon('heroicon-o-command-line')
                ->color('primary')
                ->form([
                    Select::make('device_id')
                        ->label('Select Device')
                        ->options(function () {
                            return Device::all()->mapWithKeys(function ($d) {
                                return [$d->id => $d->name ?: $d->serial_number];
                            });
                        })
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (callable $set) => $set('command', null))
                        ->default(fn () => Device::count() === 1 ? Device::first()->id : null),
                    Select::make('command')
                        ->label('Select Command')
                        ->live()
                        ->options(function (callable $get) {
                            $deviceId = $get('device_id');
                            $options = [
                                'info' => 'Get Device Info',
                                'reboot' => 'Reboot Device',
                                'checkConnection' => 'Check Connection',
                                'syncTime' => 'Sync Time',
                                'queryAllUsers' => 'Pull All Users',
                                'queryAllFingerprints' => 'Pull All Fingerprints',
                                'queryAttendanceLogs' => 'Pull All Attendance Logs (Recovery)',
                                'clearAttendanceLogs' => 'CRITICAL: Clear Attendance Logs',
                                'clearUsers' => 'CRITICAL: Clear All Users',
                                'clearAllData' => 'CRITICAL: Clear All Data (Hard Reset)',
                            ];

                            if ($deviceId) {
                                $device = Device::find($deviceId);
                                if ($device && $device->vendor === 'hikvision') {
                                    return [
                                        'queryAllUsers' => 'Pull All Users',
                                        'reboot' => 'Reboot Device',
                                    ];
                                } elseif ($device && $device->vendor === 'matrix') {
                                    return [
                                        'info' => 'Get Device Info',
                                        'checkConnection' => 'Check Connection',
                                        'queryAllUsers' => 'Pull All Users (Counts)',
                                    ];
                                }
                            }

                            return $options;
                        }),
                    TextInput::make('confirm')
                        ->label("Type 'CONFIRM' to execute this command")

                        ->required()
                        ->rule(function () {
                            return function (string $attribute, $value, \Closure $fail) {
                                if ($value !== 'CONFIRM') {
                                    $fail("You must type 'CONFIRM' exactly (all caps) to execute this command.");
                                }
                            };
                        })
                        ->hidden(fn ($get) => ! in_array($get('command'), ['clearAttendanceLogs', 'clearUsers', 'clearAllData', 'reboot'])),
                ])
                ->action(function (array $data) {
                    $device = Device::find($data['device_id']);
                    if (! $device) {
                        return;
                    }

                    $builder = app(DeviceCommandBuilder::class);
                    $commandMethod = $data['command'];

                    if (method_exists($builder, $commandMethod)) {
                        $builder->$commandMethod($device);

                        Notification::make()
                            ->title($device->vendor === 'matrix' ? 'Command Sent' : 'Command Queued')
                            ->body($device->vendor === 'matrix'
                                ? "The '{$data['command']}' command was sent directly to the Matrix device. Check its status below."
                                : "The '{$data['command']}' command will be executed on the next device poll.")
                            ->success()
                            ->send();
                    }
                }),
            Actions\CreateAction::make(),
        ];
    }
}
