<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\DeviceResource\Pages;
use App\Filament\Tenant\Resources\DeviceResource\RelationManagers;
use App\Models\Device;
use App\Services\Attendance\DeviceCommandBuilder;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Http;
use Shaykhnazar\HikvisionIsapi\Facades\Hikvision;
use Shaykhnazar\HikvisionIsapi\Services\DeviceService;

class DeviceResource extends Resource
{
    protected static ?string $model = Device::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?int $navigationSort = 1;

    protected static \UnitEnum|string|null $navigationGroup = 'Device Management';

    //

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Device Information')
                ->schema([
                    Select::make('vendor')
                        ->options([
                            'zkteco' => 'ZKTeco (ADMS)',
                            'hikvision' => 'Hikvision (ISAPI)',
                            'matrix' => 'Matrix (COSEC)',
                        ])
                        ->default('zkteco')
                        ->live()
                        ->afterStateUpdated(fn ($state, callable $set) => in_array($state, ['hikvision', 'matrix']) ? $set('serial_number', null) : null),
                    TextInput::make('serial_number')
                        ->label('Serial Number / ID')
                        ->nullable()
                        ->required(fn ($get) => $get('vendor') === 'zkteco')
                        ->unique(ignoreRecord: true)
                        ->maxLength(100)
                        ->helperText(fn ($get) => $get('vendor') === 'matrix' ? 'Optional for Matrix COSEC (used as local identifier).' : null),
                    TextInput::make('ip_address')
                        ->label('IP Address')
                        ->required(fn ($get) => in_array($get('vendor'), ['hikvision', 'matrix'])),
                    TextInput::make('username')
                        ->visible(fn ($get) => in_array($get('vendor'), ['hikvision', 'matrix']))
                        ->required(fn ($get) => in_array($get('vendor'), ['hikvision', 'matrix'])),
                    TextInput::make('password')
                        ->password()
                        ->visible(fn ($get) => in_array($get('vendor'), ['hikvision', 'matrix']))
                        ->required(fn ($get) => in_array($get('vendor'), ['hikvision', 'matrix'])),
                    TextInput::make('port')
                        ->numeric()
                        ->default(80)
                        ->visible(fn ($get) => in_array($get('vendor'), ['hikvision', 'matrix'])),
                    Select::make('protocol')
                        ->options(['http' => 'HTTP', 'https' => 'HTTPS'])
                        ->default('http')
                        ->visible(fn ($get) => in_array($get('vendor'), ['hikvision', 'matrix'])),
                    TextInput::make('name')
                        ->maxLength(255),
                    Select::make('branch_id')
                        ->relationship('branch', 'name')
                        ->searchable()
                        ->preload(),
                    TextInput::make('model')
                        ->disabled(fn ($get) => in_array($get('vendor'), ['hikvision', 'matrix'])),
                    TextInput::make('firmware_version')
                        ->disabled(),
                    TextInput::make('push_version')
                        ->disabled(fn ($get) => in_array($get('vendor'), ['hikvision', 'matrix'])),
                    Select::make('status')
                        ->options([
                            'online' => 'Online',
                            'offline' => 'Offline',
                            'unknown' => 'Unknown',
                        ])
                        ->default('unknown'),
                    Select::make('punch_behavior')
                        ->options([
                            'device_state' => 'Device State (Default)',
                            'always_in' => 'Always In',
                            'always_out' => 'Always Out',
                            'auto' => 'Auto (Alternating)',
                        ])
                        ->default('device_state')
                        ->helperText('Determines if logs from this device are Check-In, Check-Out, or handled automatically.'),
                    CheckboxList::make('options.enrollment_methods')
                        ->label('Supported Enrollment Methods')
                        ->options([
                            'face' => 'Face Recognition',
                            'finger' => 'Fingerprint',
                            'card' => 'RFID Card',
                            'special_card' => 'Special Function Card',
                        ])
                        ->columns(2)
                        ->default(fn ($get) => $get('vendor') === 'matrix' ? ['face', 'card', 'special_card'] : ['finger', 'card'])
                        ->columnSpanFull()
                        ->helperText('Select the credential and biometric types this physical device supports for user enrollment.'),
                ])
                ->columns(2)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Device Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->default('Unnamed Device'),
                Tables\Columns\TextColumn::make('serial_number')
                    ->label('Serial / ID')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Serial number copied')
                    ->fontFamily('mono'),
                Tables\Columns\TextColumn::make('vendor')
                    ->label('Vendor')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match (strtolower((string) $state)) {
                        'matrix' => 'Matrix COSEC',
                        'hikvision' => 'Hikvision',
                        'zkteco' => 'ZKTeco ADMS',
                        default => ucfirst((string) $state) ?: 'ZKTeco',
                    })
                    ->color(fn (?string $state): string => match (strtolower((string) $state)) {
                        'matrix' => 'info',
                        'hikvision' => 'warning',
                        'zkteco' => 'primary',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->formatStateUsing(fn (Device $record) => $record->ip_address ? ($record->port && $record->port != 80 ? "{$record->ip_address}:{$record->port}" : $record->ip_address) : '--')
                    ->searchable()
                    ->fontFamily('mono')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('branch.name')
                    ->label('Branch')
                    ->sortable()
                    ->searchable()
                    ->default('Not Assigned')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->getStateUsing(fn (Device $record): string => $record->isOnline() ? 'online' : 'offline')
                    ->icon(fn (string $state): string => match ($state) {
                        'online' => 'heroicon-o-check-circle',
                        'offline' => 'heroicon-o-x-circle',
                        default => 'heroicon-o-question-mark-circle',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'online' => 'success',
                        'offline' => 'danger',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('model')
                    ->label('Model')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('last_activity_at')
                    ->label('Last Activity')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),
                Tables\Columns\TextColumn::make('attendance_logs_count')
                    ->counts('attendanceLogs')
                    ->label('Logs')
                    ->badge()
                    ->color('gray')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('vendor')
                    ->label('Vendor')
                    ->options([
                        'zkteco' => 'ZKTeco',
                        'hikvision' => 'Hikvision',
                        'matrix' => 'Matrix',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'online' => 'Online',
                        'offline' => 'Offline',
                    ]),
                Tables\Filters\SelectFilter::make('branch_id')
                    ->relationship('branch', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->display_name)
                    ->label('Branch'),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    Action::make('checkStatus')
                        ->label(fn (Device $record) => match ($record->vendor) {
                            'matrix' => 'Check Matrix API Status',
                            'hikvision' => 'Check ISAPI Status',
                            default => 'Check ADMS Connection',
                        })
                        ->icon('heroicon-o-signal')
                        ->color('success')
                        ->action(function (Device $record) {
                            if ($record->vendor === 'matrix') {
                                $result = app(\App\Services\Attendance\MatrixDeviceService::class)->checkConnection($record);

                                if ($result['success']) {
                                    $record->update([
                                        'status' => 'online',
                                        'model' => $result['model'] ?? ($record->model ?: 'Matrix COSEC'),
                                        'last_activity_at' => now(),
                                    ]);

                                    if (!empty($result['enrollment_methods'])) {
                                        $options = $record->options ?? [];
                                        $options['enrollment_methods'] = $result['enrollment_methods'];
                                        $record->update(['options' => $options]);
                                    }

                                    Notification::make()
                                        ->title('Device Online')
                                        ->body($result['message'])
                                        ->success()
                                        ->send();
                                } else {
                                    $record->update(['status' => 'offline']);
                                    Notification::make()
                                        ->title('Device Connection Failed')
                                        ->body($result['message'])
                                        ->danger()
                                        ->persistent()
                                        ->send();
                                }
                            } elseif ($record->vendor === 'hikvision') {
                                try {
                                    Hikvision::registerDevice('device_'.$record->id, [
                                        'ip' => $record->ip_address,
                                        'port' => $record->port ?? 80,
                                        'username' => $record->username,
                                        'password' => $record->password,
                                        'protocol' => $record->protocol ?? 'http',
                                        'timeout' => 5,
                                        'verify_ssl' => false,
                                    ]);
                                    $client = Hikvision::device('device_'.$record->id);
                                    $deviceService = new DeviceService($client);

                                    if ($deviceService->isOnline()) {
                                        $info = $deviceService->getInfo();
                                        $model = $info['DeviceInfo']['model'] ?? 'Unknown';
                                        $fw = $info['DeviceInfo']['firmwareVersion'] ?? 'Unknown';

                                        $record->update([
                                            'status' => 'online',
                                            'model' => $model,
                                            'firmware_version' => $fw,
                                            'last_activity_at' => now(),
                                        ]);

                                        Notification::make()
                                            ->title('Device Online')
                                            ->body("Model: {$model}\nFirmware: {$fw}")
                                            ->success()
                                            ->send();
                                    } else {
                                        $record->update(['status' => 'offline']);
                                        Notification::make()
                                            ->title('Device Offline')
                                            ->body('Device is not responding to ISAPI requests.')
                                            ->warning()
                                            ->send();
                                    }
                                } catch (\Exception $e) {
                                    $record->update(['status' => 'offline']);
                                    Notification::make()
                                        ->title('Connection Failed')
                                        ->body($e->getMessage())
                                        ->danger()
                                        ->send();
                                }
                            } else {
                                app(DeviceCommandBuilder::class)->checkConnection($record);
                                Notification::make()->title('Command Queued')->body('Check connection command queued successfully.')->success()->send();
                            }
                        }),
                    Action::make('syncTime')
                        ->label('Sync Time')
                        ->icon('heroicon-o-clock')
                        ->color('primary')
                        ->requiresConfirmation()
                        ->modalHeading('Sync Device Time')
                        ->modalDescription('Queue a command to sync the device time with the server time.')
                        ->action(function (Device $record) {
                            app(DeviceCommandBuilder::class)->syncTime($record);
                            Notification::make()->title('Command Queued')->body('Time sync command queued successfully.')->success()->send();
                        }),
                    Action::make('unlockDoor')
                        ->label('Unlock Door')
                        ->icon('heroicon-o-lock-open')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Unlock Door')
                        ->modalDescription('Send an unlock command to the door controller.')
                        ->action(function (Device $record) {
                            $cmd = app(DeviceCommandBuilder::class)->unlockDoor($record);
                            Notification::make()
                                ->title($cmd->status === 'failed' ? 'Unlock Failed' : 'Door Unlocked')
                                ->body($cmd->response ?: 'Unlock command processed.')
                                ->status($cmd->status === 'failed' ? 'danger' : 'success')
                                ->send();
                        }),
                    Action::make('lockDoor')
                        ->label('Lock Door')
                        ->icon('heroicon-o-lock-closed')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Lock Door')
                        ->modalDescription('Send a lock command to the door controller.')
                        ->action(function (Device $record) {
                            $cmd = app(DeviceCommandBuilder::class)->lockDoor($record);
                            Notification::make()
                                ->title($cmd->status === 'failed' ? 'Lock Failed' : 'Door Locked')
                                ->body($cmd->response ?: 'Lock command processed.')
                                ->status($cmd->status === 'failed' ? 'danger' : 'success')
                                ->send();
                        }),
                    Action::make('normalizeDoor')
                        ->label('Set to Normal State')
                        ->icon('heroicon-o-shield-check')
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalHeading('Set Door to Normal State')
                        ->modalDescription('Return the door to standard access evaluation mode.')
                        ->action(function (Device $record) {
                            $cmd = app(DeviceCommandBuilder::class)->normalizeDoor($record);
                            Notification::make()
                                ->title($cmd->status === 'failed' ? 'Command Failed' : 'Door Normalized')
                                ->body($cmd->response ?: 'Door returned to normal mode.')
                                ->status($cmd->status === 'failed' ? 'danger' : 'success')
                                ->send();
                        }),
                    Action::make('enableEnrollment')
                        ->label('Enable Device Enrollment')
                        ->icon('heroicon-o-finger-print')
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalHeading('Enable Enrollment on Device')
                        ->modalDescription('Command the Matrix device to enable on-device biometric enrollment mode (enroll-on-device=1).')
                        ->visible(fn (Device $record) => $record->vendor === 'matrix')
                        ->action(function (Device $record) {
                            $cmd = app(DeviceCommandBuilder::class)->enableEnrollment($record);
                            Notification::make()
                                ->title($cmd->status === 'failed' ? 'Failed to Enable Enrollment' : 'Enrollment Enabled')
                                ->body($cmd->response ?: 'Enrollment mode activated on Matrix device.')
                                ->status($cmd->status === 'failed' ? 'danger' : 'success')
                                ->send();
                        }),
                    Action::make('getInfo')
                        ->label('Get Device Info')
                        ->icon('heroicon-o-information-circle')
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalHeading('Get Device Info')
                        ->modalDescription('Send a command to get device information.')
                        ->action(fn (Device $record) => app(DeviceCommandBuilder::class)->info($record)),
                    Action::make('reboot')
                        ->label('Reboot Device')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Reboot Device')
                        ->modalDescription('Are you sure you want to reboot this device?')
                        ->visible(fn (Device $record) => $record->vendor !== 'matrix')
                        ->action(fn (Device $record) => app(DeviceCommandBuilder::class)->reboot($record)),
                    Action::make('clearLogs')
                        ->label('Clear Logs')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Clear Device Logs')
                        ->modalDescription('Are you sure you want to clear all attendance logs on this device?')
                        ->action(fn (Device $record) => app(DeviceCommandBuilder::class)->clearAttendanceLogs($record)),
                    DeleteAction::make(),
                ])
                ->icon('heroicon-m-ellipsis-vertical')
                ->tooltip('Actions'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\AttendanceLogsRelationManager::class,
            RelationManagers\CommandsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDevices::route('/'),
            'create' => Pages\CreateDevice::route('/create'),
            'view' => Pages\ViewDevice::route('/{record}'),
            'edit' => Pages\EditDevice::route('/{record}/edit'),
        ];
    }
}
