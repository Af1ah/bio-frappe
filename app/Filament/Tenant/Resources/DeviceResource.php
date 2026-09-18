<?php

namespace App\Filament\Tenant\Resources;

use App\Enums\DeviceTransport;
use App\Filament\Tenant\Resources\DeviceResource\Pages;
use App\Filament\Tenant\Resources\DeviceResource\RelationManagers;
use App\Models\Device;
use App\Models\DeviceCommand;
use App\Models\DeviceUser;
use App\Models\User;
use App\Services\Attendance\UserDeviceSyncService;
use App\Services\DeviceCommandCapabilities;
use App\Services\DeviceCommandDispatcher;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class DeviceResource extends Resource
{
    protected static ?string $model = Device::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?int $navigationSort = 1;

    protected static \UnitEnum|string|null $navigationGroup = 'Device Management';

    /** @return array<int, string> */
    public static function directDeviceOptions(): array
    {
        return Device::query()
            ->orderBy('name')
            ->get()
            ->filter(fn (Device $device): bool => DeviceTransport::forDevice($device) === DeviceTransport::Direct)
            ->mapWithKeys(fn (Device $device): array => [
                $device->id => "{$device->name} ({$device->serial_number})",
            ])
            ->all();
    }

    /** @return array<int, string> */
    public static function userFetchDeviceOptions(): array
    {
        return Device::query()->orderBy('name')->get()
            ->filter(fn (Device $device): bool => in_array(
                DeviceTransport::forDevice($device),
                [DeviceTransport::Direct, DeviceTransport::Adms],
                true,
            ))
            ->mapWithKeys(fn (Device $device): array => [
                $device->id => "{$device->name} ({$device->serial_number})",
            ])
            ->all();
    }

    //

    public static function canCreate(): bool
    {
        return true;
    }

    public static function canEdit(Model $record): bool
    {
        return true;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('serial_number'),
            TextEntry::make('name'),
            TextEntry::make('options.location')
                ->label('Location'),
            TextEntry::make('last_activity_at')
                ->label('Last Ping')
                ->dateTime(),
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Device')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('serial_number')
                                ->label('Serial Number')
                                ->disabled(),
                            TextInput::make('name')
                                ->label('Device Name')
                                ->required(),
                            TextInput::make('ip_address')
                                ->label('Device IP Address')
                                ->ipv4()
                                ->visible(fn (callable $get): bool => in_array($get('options.connection_mode'), ['direct', 'adms'], true))
                                ->required(fn (callable $get): bool => in_array($get('options.connection_mode'), ['direct', 'adms'], true))
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (?string $state, callable $get, callable $set) {
                                    if ($state && blank($get('options.source_cidr'))) {
                                        $set('options.source_cidr', $state);
                                    }
                                }),
                            TextInput::make('options.location')
                                ->label('Location')
                                ->required(),
                            Select::make('options.direction')
                                ->options([
                                    'IN' => 'IN',
                                    'OUT' => 'OUT',
                                    'ALTERNATE_IN_OUT' => 'Alternate IN/OUT',
                                    'DEVICE_STATE' => 'State from device',
                                    'OTHER' => 'Other (legacy)',
                                ])
                                ->default('ALTERNATE_IN_OUT')
                                ->label('Direction / State')
                                ->required(),
                            Select::make('options.type')
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
                                        $set('options.is_attendance_device', 'false');
                                    } else {
                                        $set('options.is_attendance_device', 'true');
                                    }
                                }),
                            TextInput::make('options.timezone')
                                ->label('Time Zone')
                                ->default('Asia/Kolkata')
                                ->required(),
                            TextInput::make('options.activation_code')
                                ->label('Activation Code')
                                ->default('0'),
                            Select::make('options.is_attendance_device')
                                ->options(['true' => 'Yes', 'false' => 'No'])
                                ->label('Is Attendance Device')
                                ->default('true')
                                ->required(),
                            static::connectionMode()
                                ->columnSpanFull(),
                            TextInput::make('options.source_cidr')
                                ->label('Expected device IP or CIDR')
                                ->placeholder('Auto-filled from Device IP (or leave blank to auto-detect)')
                                ->helperText('Auto-filled from Device IP Address. If left blank, it will auto-detect on first connection.')
                                ->visible(fn (callable $get): bool => $get('options.connection_mode') === 'adms')
                                ->columnSpanFull(),
                            CheckboxList::make('options.enrollment_methods')
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
                    ->columnSpanFull(),
            ]);
    }

    public static function connectionMode(string $statePath = 'options.connection_mode'): Select
    {
        $host = (string) config('services.device_gateway.device_host');
        $port = (string) config('services.device_gateway.device_port');

        return Select::make($statePath)
            ->label('Connection method')
            ->options([
                'ebio' => 'eBioServer',
                'direct' => 'Direct LAN',
                'adms' => 'Standalone ADMS gateway',
            ])
            ->default('ebio')
            ->required()
            ->live()
            ->hintIcon(
                'heroicon-m-question-mark-circle',
                "Device server: {$host}:{$port}",
            );
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('serial_number')
                    ->searchable()
                    ->sortable()
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->getStateUsing(function (Device $record): string {
                        $type = data_get($record->options, 'type', $record->device_type) ?: 'Attendance';

                        return match ($type) {
                            'Door' => 'Door Access',
                            'Attendance_Door' => 'Attendance + Door',
                            default => 'Attendance',
                        };
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Door Access' => 'warning',
                        'Attendance + Door' => 'info',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'Door Access', 'Attendance + Door' => 'heroicon-m-lock-closed',
                        default => 'heroicon-m-finger-print',
                    }),
                Tables\Columns\TextColumn::make('options.location')
                    ->label('Location')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('options.enrollment_methods')
                    ->label('Enrollment Methods')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'fingerprint' => 'Fingerprint',
                        'rfid' => 'RFID',
                        'face' => 'Face',
                        'face_v2' => 'Face v2',
                        default => (string) $state,
                    })
                    ->color('info')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->getStateUsing(fn (Device $record): string => $record->isOnline() ? 'online' : 'offline')
                    ->color(fn (string $state): string => match ($state) {
                        'online' => 'success',
                        'offline' => 'danger',
                        default => 'warning',
                    })
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('last_activity_at')
                    ->label('Last Ping')
                    ->date('M j, Y')
                    ->description(fn (Device $record): ?string => $record->last_activity_at?->format('H:i:s'))
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('last_sync_at')
                    ->label('Last Sync')
                    ->date('M j, Y')
                    ->description(fn (Device $record): ?string => $record->last_sync_at?->format('H:i:s'))
                    ->sortable()
                    ->toggleable()
                    ->visibleFrom('md'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'online' => 'Online',
                        'offline' => 'Offline',
                        'unknown' => 'Unknown',
                    ]),
                Tables\Filters\SelectFilter::make('device_type')
                    ->label('Device Type')
                    ->options([
                        'Attendance' => 'Attendance',
                        'Door' => 'Door Access',
                        'Attendance_Door' => 'Attendance & Door',
                    ]),
            ])
            ->recordActions([
                Action::make('checkStatus')
                    ->label('Check Status')
                    ->icon('heroicon-o-signal')
                    ->color('info')
                    ->modalHeading(fn (Device $record) => "Device Status: {$record->name}")
                    ->modalDescription('Real-time connectivity and status details.')
                    ->modalSubmitActionLabel('Send Check / Ping Command')
                    ->modalCancelActionLabel('Close')
                    ->infolist([
                        Section::make('Live Terminal Status')
                            ->schema([
                                TextEntry::make('serial_number')
                                    ->label('Serial Number')
                                    ->weight('bold'),
                                TextEntry::make('name')
                                    ->label('Device Name'),
                                TextEntry::make('status')
                                    ->label('Current Status')
                                    ->badge()
                                    ->getStateUsing(fn (Device $record): string => $record->isOnline() ? 'online' : 'offline')
                                    ->color(fn (string $state): string => $state === 'online' ? 'success' : 'danger'),
                                TextEntry::make('last_activity_at')
                                    ->label('Last Ping / Heartbeat')
                                    ->dateTime('M j, Y H:i:s')
                                    ->helperText(fn (Device $record): string => $record->last_activity_at ? $record->last_activity_at->diffForHumans() : 'Never'),
                                TextEntry::make('ip_address')
                                    ->label('IP Address')
                                    ->default('Auto / DHCP'),
                                TextEntry::make('options.connection_mode')
                                    ->label('Transport')
                                    ->badge()
                                    ->formatStateUsing(fn ($state) => match ($state) {
                                        'adms' => 'Standalone ADMS',
                                        'direct' => 'Direct LAN',
                                        'ebio' => 'eBioServer',
                                        default => (string) $state,
                                    })
                                    ->color('primary'),
                                TextEntry::make('latest_command')
                                    ->label('Latest Command State')
                                    ->getStateUsing(function (Device $record): string {
                                        $latest = $record->commands()->latest()->first();
                                        if (! $latest) {
                                            return 'No commands yet';
                                        }

                                        return "{$latest->command_type} (".($latest->delivery_status ?: $latest->status).')';
                                    })
                                    ->helperText(function (Device $record): ?string {
                                        $latest = $record->commands()->latest()->first();

                                        return $latest?->updated_at?->diffForHumans();
                                    }),
                            ])
                            ->columns(2),
                    ])
                    ->action(function (Device $record): void {
                        $command = DeviceCommand::create([
                            'device_id' => $record->id,
                            'command_type' => 'check',
                            'command_content' => 'Check device status and connectivity',
                            'status' => 'pending',
                        ]);
                        app(DeviceCommandDispatcher::class)->dispatch($record, $command);

                        Notification::make()
                            ->title('Status Check Queued')
                            ->body("Connectivity check queued for {$record->name}. The terminal will acknowledge on its next heartbeat poll.")
                            ->success()
                            ->send();
                    }),
                ActionGroup::make([
                    EditAction::make(),
                    Action::make('fetchDeviceInfo')
                        ->label('Fetch Device Info')
                        ->icon('heroicon-o-information-circle')
                        ->visible(fn (Device $record): bool => app(DeviceCommandCapabilities::class)->supports($record, 'device_info'))
                        ->requiresConfirmation()
                        ->action(function (Device $record): void {
                            $command = DeviceCommand::create([
                                'device_id' => $record->id,
                                'command_type' => 'device_info',
                                'command_content' => 'Fetch device information and counters',
                                'status' => 'pending',
                            ]);
                            app(DeviceCommandDispatcher::class)->dispatch($record, $command);

                            Notification::make()
                                ->title('Info Request Queued')
                                ->body("Device info request queued for {$record->name}.")
                                ->success()
                                ->send();
                        }),
                    Action::make('syncTime')
                        ->label('Sync Device Time')
                        ->icon('heroicon-o-clock')
                        ->visible(fn (Device $record): bool => app(DeviceCommandCapabilities::class)->supports($record, 'sync_time'))
                        ->requiresConfirmation()
                        ->action(function (Device $record): void {
                            $command = DeviceCommand::create([
                                'device_id' => $record->id,
                                'command_type' => 'sync_time',
                                'command_content' => 'Direct command: sync device time',
                                'status' => 'pending',
                            ]);
                            app(DeviceCommandDispatcher::class)->dispatch($record, $command);
                        }),
                    Action::make('reboot')
                        ->label('Reboot Device')
                        ->icon('heroicon-o-power')
                        ->requiresConfirmation()
                        ->visible(fn (Device $record): bool => app(DeviceCommandCapabilities::class)->supports($record, 'reboot'))
                        ->action(function (Device $record) {
                            $command = DeviceCommand::create([
                                'device_id' => $record->id,
                                'command_type' => 'reboot',
                                'command_content' => 'Device command: reboot',
                                'status' => 'pending',
                            ]);
                            app(DeviceCommandDispatcher::class)->dispatch($record, $command);
                            Notification::make()
                                ->title('Command Queued')
                                ->body('Reboot command queued.')
                                ->success()
                                ->send();
                        }),
                    Action::make('clearLogs')
                        ->label('Clear Logs')
                        ->icon('heroicon-o-trash')
                        ->requiresConfirmation()
                        ->color('danger')
                        ->visible(fn (Device $record): bool => app(DeviceCommandCapabilities::class)->supports($record, 'clear_logs'))
                        ->action(function (Device $record) {
                            $command = DeviceCommand::create([
                                'device_id' => $record->id,
                                'command_type' => 'clear_logs',
                                'command_content' => 'Device command: clear_logs',
                                'status' => 'pending',
                            ]);
                            app(DeviceCommandDispatcher::class)->dispatch($record, $command);
                            Notification::make()
                                ->title('Command Queued')
                                ->body('Clear logs command queued.')
                                ->success()
                                ->send();
                        }),
                    Action::make('resetTransactionStamp')
                        ->label('Reset Transaction Stamp')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->requiresConfirmation()
                        ->visible(fn (Device $record): bool => app(DeviceCommandCapabilities::class)->supports($record, 'reset_transaction_stamp'))
                        ->action(function (Device $record) {
                            $command = DeviceCommand::create([
                                'device_id' => $record->id,
                                'command_type' => 'reset_transaction_stamp',
                                'command_content' => 'Device command: reset_transaction_stamp',
                                'status' => 'pending',
                            ]);
                            app(DeviceCommandDispatcher::class)->dispatch($record, $command);
                            Notification::make()
                                ->title('Command Queued')
                                ->body('Reset transaction stamp command queued.')
                                ->success()
                                ->send();
                        }),
                    Action::make('resetOPStamp')
                        ->label('Reset OP Stamp')
                        ->icon('heroicon-o-arrow-path')
                        ->requiresConfirmation()
                        ->visible(fn (Device $record): bool => app(DeviceCommandCapabilities::class)->supports($record, 'reset_op_stamp'))
                        ->action(function (Device $record) {
                            $command = DeviceCommand::create([
                                'device_id' => $record->id,
                                'command_type' => 'reset_op_stamp',
                                'command_content' => 'Device command: reset_op_stamp',
                                'status' => 'pending',
                            ]);
                            app(DeviceCommandDispatcher::class)->dispatch($record, $command);
                            Notification::make()
                                ->title('Command Queued')
                                ->body('Reset OP stamp command queued.')
                                ->success()
                                ->send();
                        }),
                    Action::make('unlockDoor')
                        ->label('Unlock Door')
                        ->icon('heroicon-o-lock-open')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn (Device $record): bool => app(DeviceCommandCapabilities::class)->supports($record, 'unlock_door'))
                        ->action(function (Device $record) {
                            $command = DeviceCommand::create([
                                'device_id' => $record->id,
                                'command_type' => 'unlock_door',
                                'command_content' => 'Device command: unlock_door',
                                'status' => 'pending',
                            ]);
                            app(DeviceCommandDispatcher::class)->dispatch($record, $command);
                            Notification::make()
                                ->title('Command Queued')
                                ->body('Unlock door command queued.')
                                ->success()
                                ->send();
                        }),
                    Action::make('lockDoor')
                        ->label('Lock Door')
                        ->icon('heroicon-o-lock-closed')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn (Device $record): bool => app(DeviceCommandCapabilities::class)->supports($record, 'lock_door'))
                        ->action(function (Device $record) {
                            $command = DeviceCommand::create([
                                'device_id' => $record->id,
                                'command_type' => 'lock_door',
                                'command_content' => 'Device command: lock_door',
                                'status' => 'pending',
                            ]);
                            app(DeviceCommandDispatcher::class)->dispatch($record, $command);
                            Notification::make()
                                ->title('Command Queued')
                                ->body('Lock door command queued.')
                                ->success()
                                ->send();
                        }),
                    Action::make('normalDoor')
                        ->label('Normal Door')
                        ->icon('heroicon-o-shield-check')
                        ->requiresConfirmation()
                        ->visible(fn (Device $record): bool => app(DeviceCommandCapabilities::class)->supports($record, 'normal_door'))
                        ->action(function (Device $record) {
                            $command = DeviceCommand::create([
                                'device_id' => $record->id,
                                'command_type' => 'normal_door',
                                'command_content' => 'Device command: normal_door',
                                'status' => 'pending',
                            ]);
                            app(DeviceCommandDispatcher::class)->dispatch($record, $command);
                            Notification::make()
                                ->title('Command Queued')
                                ->body('Normal door command queued.')
                                ->success()
                                ->send();
                        }),
                    Action::make('uploadUsers')
                        ->label('Upload Users to Device')
                        ->icon('heroicon-o-arrow-up-tray')
                        ->color('success')
                        ->form([
                            Select::make('user_ids')
                                ->label('Select User(s)')
                                ->options(fn () => User::query()->orderBy('name')->get()->mapWithKeys(fn ($u) => [$u->id => "{$u->name} (PIN: {$u->pin})"]))
                                ->multiple()
                                ->searchable()
                                ->required(),
                            CheckboxList::make('credentials')
                                ->label('Credentials to Upload')
                                ->options([
                                    'pin' => 'PIN & Password',
                                    'card' => 'RFID Card',
                                    'fingerprint' => 'Fingerprint Templates',
                                    'face' => 'Face Templates (v1 / v2)',
                                ])
                                ->columns(2)
                                ->default(['pin', 'card', 'fingerprint', 'face'])
                                ->required(),
                        ])
                        ->action(function (Device $record, array $data): void {
                            $syncService = app(UserDeviceSyncService::class);
                            $users = User::whereIn('id', $data['user_ids'])->get();
                            $totalCommands = 0;
                            foreach ($users as $user) {
                                $res = $syncService->uploadUserToDevice($record, $user, $data['credentials']);
                                $totalCommands += $res['commands_queued'] ?? 0;
                            }
                            Notification::make()
                                ->title('Users Upload Queued')
                                ->body(count($users).' user(s) queued for upload to '.$record->name.'.')
                                ->success()
                                ->send();
                        }),
                    Action::make('deleteUserFromDevice')
                        ->label('Delete User from Device')
                        ->icon('heroicon-o-user-minus')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->form([
                            Select::make('pin')
                                ->label('Select User to Delete')
                                ->options(function (Device $record) {
                                    $devicePins = DeviceUser::where('device_id', $record->id)->pluck('pin')->all();
                                    $users = User::whereIn('pin', $devicePins)->get();
                                    if ($users->isEmpty()) {
                                        $users = User::all();
                                    }

                                    return $users->mapWithKeys(fn ($u) => [$u->pin => "{$u->name} (PIN: {$u->pin})"]);
                                })
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (Device $record, array $data): void {
                            app(UserDeviceSyncService::class)->deleteUserFromDevice($record, $data['pin']);
                            Notification::make()
                                ->title('Delete Queued')
                                ->body("User PIN {$data['pin']} queued for deletion from {$record->name}.")
                                ->success()
                                ->send();
                        }),
                    Action::make('copyUsersFrom')
                        ->label('Copy Users from Another Device')
                        ->icon('heroicon-o-document-duplicate')
                        ->form([
                            Select::make('source_device_id')
                                ->label('Source Device')
                                ->options(fn (Device $record) => Device::where('id', '!=', $record->id)->pluck('name', 'id'))
                                ->required()
                                ->searchable(),
                            CheckboxList::make('credentials')
                                ->label('Credentials to Copy')
                                ->options([
                                    'pin' => 'PIN & Password',
                                    'card' => 'RFID Card',
                                    'fingerprint' => 'Fingerprint Templates',
                                    'face' => 'Face Templates (v1 / v2)',
                                ])
                                ->columns(2)
                                ->default(['pin', 'card', 'fingerprint', 'face'])
                                ->required(),
                        ])
                        ->action(function (Device $record, array $data): void {
                            $sourceDevice = Device::findOrFail($data['source_device_id']);
                            $deviceUsers = DeviceUser::where('device_id', $sourceDevice->id)->pluck('pin');
                            $users = User::whereIn('pin', $deviceUsers)->get();
                            if ($users->isEmpty()) {
                                $users = User::all();
                            }
                            $syncService = app(UserDeviceSyncService::class);
                            foreach ($users as $user) {
                                $syncService->uploadUserToDevice($record, $user, $data['credentials']);
                            }
                            Notification::make()
                                ->title('Copy Queued')
                                ->body(count($users)." user(s) copied from {$sourceDevice->name} to {$record->name}.")
                                ->success()
                                ->send();
                        }),
                ])->icon('heroicon-m-ellipsis-vertical'),
            ])
            ->toolbarActions([]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\DeviceUsersRelationManager::class,
            RelationManagers\AttendanceLogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDevices::route('/'),
            'view' => Pages\ViewDevice::route('/{record}'),
            'edit' => Pages\EditDevice::route('/{record}/edit'),
        ];
    }
}
