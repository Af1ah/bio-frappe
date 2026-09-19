<?php

namespace App\Filament\Tenant\Resources\DeviceResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Tenant\Resources\DeviceResource;

class ListDevices extends ListRecords
{
    protected static string $resource = DeviceResource::class;

    protected function getHeaderActions(): array  {
        return [
            Actions\Action::make('syncEbioDevices')
                ->label('Sync from eBioServer')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Sync Devices from eBioServer')
                ->action(function () {
                    try {
                        $service = new \App\Services\EbioSoapService();
                        $result = $service->syncDevices(tenant());
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Sync Complete')
                            ->body("Successfully synced {$result['synced']} devices.")
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        \Filament\Notifications\Notification::make()
                            ->title('Sync Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Actions\Action::make('runCommand')
                ->label('Run Command')
                ->icon('heroicon-o-command-line')
                ->color('primary')
                ->form([
                    \Filament\Forms\Components\Select::make('device_id')
                        ->label('Select Device')
                        ->options(function () {
                            return \App\Models\Device::all()->mapWithKeys(function ($d) {
                                return [$d->id => $d->name ?: $d->serial_number];
                            });
                        })
                        ->required()
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(fn (callable $set) => $set('command', null))
                        ->default(fn () => \App\Models\Device::count() === 1 ? \App\Models\Device::first()->id : null),
                    \Filament\Forms\Components\Select::make('command')
                        ->label('Select Command')
                        ->live()
                        ->options(function (callable $get) {
                            $deviceId = $get('device_id');
                            $options = [
                                'fetch_attendance' => 'Fetch Attendance Logs',
                                'test_connection' => 'Test Connection & Ping',
                                'unlock_door' => 'Unlock Door',
                                'reboot' => 'Reboot Device',
                                'sync_time' => 'Synchronize Device Time',
                                'test_voice' => 'Voice Test ("Thank You")',
                                'clear_logs' => 'CRITICAL: Clear Attendance Logs',
                                'reset_transaction_stamp' => 'Reset Transaction Stamp',
                                'reset_op_stamp' => 'Reset OP Stamp',
                                'shutdown' => 'Power Off / Shutdown Device',
                            ];

                            
                            return $options;
                        }),
                    \Filament\Forms\Components\TextInput::make('confirm')
                        ->label("Type 'CONFIRM' to execute this command")

                        ->required()
                        ->rule(function () {
                            return function (string $attribute, $value, \Closure $fail) {
                                if ($value !== 'CONFIRM') {
                                    $fail("You must type 'CONFIRM' exactly (all caps) to execute this command.");
                                }
                            };
                        })
                        ->hidden(fn ($get) => !in_array($get('command'), ['clear_logs', 'reboot'])),
                ])
                ->action(function (array $data) {
                    $device = \App\Models\Device::find($data['device_id']);
                    if (!$device) return;

                    $command = \App\Models\DeviceCommand::create([
                        'device_id' => $device->id,
                        'command_type' => $data['command'],
                        'command_content' => "eBioServer SOAP Command: {$data['command']}",
                        'status' => 'pending',
                    ]);

                    \App\Jobs\EbioDeviceCommandJob::dispatch(tenant(), $device->serial_number, $data['command'], $command->id);
                    
                    \Filament\Notifications\Notification::make()
                        ->title('Command Queued')
                        ->body("The '{$data['command']}' command has been queued for sync.")
                        ->success()
                        ->send();
                }),
            Actions\CreateAction::make()
                ->label('Add Device')
                ->icon('heroicon-o-plus')
                ->modalHeading('Add New Device')
                ->form([
                    \Filament\Schemas\Components\Grid::make(2)->schema([
                        \Filament\Forms\Components\TextInput::make('serial_number')
                            ->required()
                            ->label('Serial Number')
                            ->columnSpan('full'),
                        \Filament\Forms\Components\TextInput::make('name')
                            ->required()
                            ->label('Device Name'),
                        \Filament\Forms\Components\TextInput::make('location')
                            ->required()
                            ->label('Location'),
                        \Filament\Forms\Components\TextInput::make('ip_address')
                            ->label('IP Address')
                            ->placeholder('192.168.1.201'),
                        \Filament\Forms\Components\TextInput::make('port')
                            ->label('Port')
                            ->numeric()
                            ->default(4370),
                        \Filament\Forms\Components\Select::make('direction')
                            ->options([
                                'device based' => 'Device Based',
                                'IN' => 'IN',
                                'OUT' => 'OUT',
                                'IN/OUT altering' => 'IN/OUT Alternating',
                                'OTHER' => 'OTHER',
                            ])
                            ->default('device based')
                            ->required()
                            ->label('Direction'),
                        \Filament\Forms\Components\TextInput::make('time_zone')
                            ->default('Asia/Kolkata')
                            ->required()
                            ->label('Time Zone'),
                        \Filament\Forms\Components\Select::make('is_attendance_device')
                            ->options(['true' => 'Yes', 'false' => 'No'])
                            ->default('true')
                            ->required()
                            ->label('Is Attendance Device'),
                    ])
                ])
                ->using(function (array $data, string $model): \Illuminate\Database\Eloquent\Model {
                    $punchBehavior = match ($data['direction'] ?? '') {
                        'IN' => 'always_in',
                        'OUT' => 'always_out',
                        'IN/OUT altering' => 'auto',
                        'device based' => 'device_state',
                        default => 'device_state',
                    };

                    return $model::create([
                        'serial_number' => $data['serial_number'],
                        'name' => $data['name'],
                        'ip_address' => $data['ip_address'] ?? null,
                        'port' => !empty($data['port']) ? (int) $data['port'] : 4370,
                        'status' => 'offline',
                        'punch_behavior' => $punchBehavior,
                        'options' => [
                            'location' => $data['location'],
                            'direction' => $data['direction'],
                            'timezone' => $data['time_zone'] ?? 'Asia/Kolkata',
                            'is_attendance_device' => $data['is_attendance_device'] ?? 'true',
                        ]
                    ]);
                }),
        ];
    }
}
