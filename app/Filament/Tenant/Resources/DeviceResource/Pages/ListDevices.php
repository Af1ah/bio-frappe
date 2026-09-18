<?php

namespace App\Filament\Tenant\Resources\DeviceResource\Pages;

use App\Filament\Tenant\Resources\DeviceResource;
use App\Models\Device;
use App\Models\DeviceBinding;
use App\Models\DeviceCommand;
use App\Services\DeviceCommandCapabilities;
use App\Services\DeviceCommandDispatcher;
use App\Services\DeviceGateway\RegisterGoAdmsDevice;
use App\Services\EbioSoapService;
use Filament\Actions;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Grid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class ListDevices extends ListRecords
{
    protected static string $resource = DeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('syncEbioDevices')
                ->label('Sync from eBioServer')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Sync Devices from eBioServer')
                ->action(function () {
                    try {
                        $service = new EbioSoapService;
                        $result = $service->syncDevices(tenant());

                        Notification::make()
                            ->title('Sync Complete')
                            ->body("Successfully synced {$result['synced']} devices.")
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
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
                    Select::make('device_id')
                        ->label('Select Device')
                        ->options(function () {
                            return Device::all()->mapWithKeys(function ($d) {
                                return [$d->id => $d->name ?: $d->serial_number];
                            });
                        })
                        ->required()
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(fn (callable $set) => $set('command', null))
                        ->default(fn () => Device::count() === 1 ? Device::first()->id : null),
                    Select::make('command')
                        ->label('Select Command')
                        ->live()
                        ->options(function (callable $get) {
                            $deviceId = $get('device_id');
                            $device = $deviceId ? Device::find($deviceId) : null;

                            return $device ? app(DeviceCommandCapabilities::class)->options($device) : [];
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
                        ->hidden(fn ($get) => ! in_array($get('command'), ['clear_logs', 'reboot'])),
                ])
                ->action(function (array $data) {
                    $device = Device::find($data['device_id']);
                    if (! $device) {
                        return;
                    }

                    $command = DeviceCommand::create([
                        'device_id' => $device->id,
                        'command_type' => $data['command'],
                        'command_content' => "Device command: {$data['command']}",
                        'status' => 'pending',
                    ]);

                    app(DeviceCommandDispatcher::class)->dispatch($device, $command);

                    Notification::make()
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
                    Grid::make(2)->schema([
                        TextInput::make('serial_number')
                            ->required()
                            ->label('Serial Number')
                            ->columnSpan('full'),
                        TextInput::make('name')
                            ->required()
                            ->label('Device Name'),
                        TextInput::make('ip_address')
                            ->label('Device IP Address')
                            ->ipv4()
                            ->visible(fn (callable $get): bool => in_array($get('connection_mode'), ['direct', 'adms'], true))
                            ->required(fn (callable $get): bool => in_array($get('connection_mode'), ['direct', 'adms'], true))
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, callable $get, callable $set) {
                                if ($state && blank($get('source_cidr'))) {
                                    $set('source_cidr', $state);
                                }
                            }),
                        TextInput::make('location')
                            ->required()
                            ->label('Location'),
                        Select::make('direction')
                            ->options([
                                'IN' => 'IN',
                                'OUT' => 'OUT',
                                'ALTERNATE_IN_OUT' => 'Alternate IN/OUT',
                                'DEVICE_STATE' => 'State from device',
                            ])
                            ->default('ALTERNATE_IN_OUT')
                            ->required()
                            ->label('Direction / State'),
                        Select::make('device_type')
                            ->label('Device Type')
                            ->options([
                                'Attendance' => 'Attendance Terminal',
                                'Door' => 'Door Access',
                                'Attendance_Door' => 'Attendance & Door Access',
                            ])
                            ->default('Attendance')
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (string $state, callable $set) {
                                if ($state === 'Door') {
                                    $set('is_attendance_device', 'false');
                                } else {
                                    $set('is_attendance_device', 'true');
                                }
                            }),
                        TextInput::make('time_zone')
                            ->default('Asia/Kolkata')
                            ->required()
                            ->label('Time Zone'),
                        TextInput::make('activation_code')
                            ->default('0')
                            ->label('Activation Code'),
                        Select::make('is_attendance_device')
                            ->options(['true' => 'Yes', 'false' => 'No'])
                            ->default('true')
                            ->required()
                            ->label('Is Attendance Device'),
                        DeviceResource::connectionMode('connection_mode')
                            ->columnSpanFull(),
                        TextInput::make('source_cidr')
                            ->label('Expected device IP or CIDR')
                            ->placeholder('Auto-filled from Device IP (or leave blank to auto-detect)')
                            ->helperText('Auto-filled from Device IP Address. If left blank, it will auto-detect on first connection.')
                            ->visible(fn (callable $get): bool => $get('connection_mode') === 'adms')
                            ->columnSpanFull(),
                        CheckboxList::make('enrollment_methods')
                            ->label('Available Enrollment Methods')
                            ->options([
                                'fingerprint' => 'Fingerprint',
                                'rfid' => 'RFID Card',
                                'face' => 'Face (Standard)',
                                'face_v2' => 'Face v2 (AI / Visible Light)',
                            ])
                            ->columns(2)
                            ->default(['fingerprint', 'rfid'])
                            ->helperText('Select authentication & biometric enrollment methods supported by this device.')
                            ->columnSpanFull(),
                    ]),
                ])
                ->using(function (array $data, string $model): Model {
                    $connectionMode = $data['connection_mode'] ?? 'ebio';

                    if ($connectionMode === 'ebio') {
                        $service = new EbioSoapService;

                        try {
                            $success = $service->addDevice(tenant(), $data);
                            if (! $success) {
                                throw new \Exception('eBioServer API rejected the device addition.');
                            }
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Add Device Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();

                            throw ValidationException::withMessages([
                                'serial_number' => $e->getMessage(),
                            ]);
                        }
                    }

                    $isDoorBased = in_array(strtolower((string) ($data['device_type'] ?? 'Attendance')), ['door', 'door based', 'attendance_door'], true);
                    $enrollmentMethods = $data['enrollment_methods'] ?? ['fingerprint', 'rfid'];

                    $device = $model::create([
                        'serial_number' => $data['serial_number'],
                        'name' => $data['name'],
                        'ip_address' => $data['ip_address'] ?? null,
                        'device_type' => $data['device_type'] ?? 'Attendance',
                        'status' => 'offline',
                        'options' => [
                            'location' => $data['location'],
                            'direction' => $data['direction'],
                            'type' => $data['device_type'] ?? 'Attendance',
                            'is_door_based' => $isDoorBased,
                            'is_attendance_device' => $data['is_attendance_device'] ?? ($data['device_type'] === 'Door' ? 'false' : 'true'),
                            'timezone' => $data['time_zone'],
                            'connection_mode' => $connectionMode,
                            'source_cidr' => filled($data['source_cidr'] ?? null) ? $data['source_cidr'] : ($data['ip_address'] ?? 'auto'),
                            'adms_enabled' => $connectionMode === 'direct',
                            'enrollment_methods' => $enrollmentMethods,
                            'capabilities' => [
                                'enrollment_methods' => $enrollmentMethods,
                            ],
                        ],
                    ]);

                    if ($connectionMode === 'adms') {
                        try {
                            app(RegisterGoAdmsDevice::class)->register(
                                $device,
                                tenancy()->tenant->id,
                                $data['time_zone'],
                            );
                        } catch (\Throwable $exception) {
                            $device->delete();
                            DeviceBinding::query()
                                ->where('tenant_id', tenancy()->tenant->id)
                                ->where('serial_number', $data['serial_number'])
                                ->update(['is_active' => false]);

                            throw ValidationException::withMessages([
                                'serial_number' => 'The standalone ADMS gateway did not accept this device.',
                            ]);
                        }
                    }

                    return $device;
                }),
        ];
    }
}
