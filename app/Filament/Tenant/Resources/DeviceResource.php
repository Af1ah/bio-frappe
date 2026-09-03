<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\DeviceResource\Pages;
use App\Filament\Tenant\Resources\DeviceResource\RelationManagers;
use App\Models\Device;
use App\Services\Attendance\DeviceCommandBuilder;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
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
                        ->required(fn ($get) => $get('vendor') === 'zkteco')
                        ->unique(ignoreRecord: true)
                        ->maxLength(100),
                    TextInput::make('ip_address')
                        ->label('IP Address'),
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
                ])
                ->columns(2)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('serial_number')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP Address'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->getStateUsing(fn (Device $record): string => $record->isOnline() ? 'online' : 'offline')
                    ->color(fn (string $state): string => match ($state) {
                        'online' => 'success',
                        'offline' => 'danger',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('last_activity_at')
                    ->label('Last Activity')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('attendance_logs_count')
                    ->counts('attendanceLogs')
                    ->label('Logs'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'online' => 'Online',
                        'offline' => 'Offline',
                        'unknown' => 'Unknown',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('getInfo')
                    ->icon('heroicon-o-information-circle')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Get Device Info')
                    ->modalDescription('Send a command to get device information.')
                    ->action(fn (Device $record) => app(DeviceCommandBuilder::class)->info($record)),
                Action::make('reboot')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Reboot Device')
                    ->modalDescription('Are you sure you want to reboot this device?')
                    ->visible(fn (Device $record) => $record->vendor !== 'matrix')
                    ->action(fn (Device $record) => app(DeviceCommandBuilder::class)->reboot($record)),
                Action::make('clearLogs')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Clear Device Logs')
                    ->modalDescription('Are you sure you want to clear all attendance logs on this device?')
                    ->action(fn (Device $record) => app(DeviceCommandBuilder::class)->clearAttendanceLogs($record)),
                Action::make('checkConnection')
                    ->label('Check ADMS Connection')
                    ->icon('heroicon-o-wifi')
                    ->color('info')
                    ->visible(fn (Device $record) => $record->vendor === 'zkteco' || empty($record->vendor))
                    ->requiresConfirmation()
                    ->modalHeading('Check Connection')
                    ->modalDescription('This will queue a CHECK command. The device should process it on its next poll.')
                    ->action(function (Device $record) {
                        app(DeviceCommandBuilder::class)->checkConnection($record);
                        Notification::make()->title('Command Queued')->body('Check connection command queued successfully.')->success()->send();
                    }),
                Action::make('hikvisionStatus')
                    ->label('Check ISAPI Status')
                    ->icon('heroicon-o-signal')
                    ->color('success')
                    ->visible(fn (Device $record) => $record->vendor === 'hikvision')
                    ->action(function (Device $record) {
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

                                // Auto update model/firmware on success
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
                    }),
                Action::make('matrixStatus')
                    ->label('Check Matrix API Status')
                    ->icon('heroicon-o-signal')
                    ->color('success')
                    ->visible(fn (Device $record) => $record->vendor === 'matrix')
                    ->action(function (Device $record) {
                        try {
                            $baseUrl = ($record->protocol ?? 'http').'://'.$record->ip_address.':'.($record->port ?? 80);
                            $response = Http::withDigestAuth($record->username, $record->password)
                                ->timeout(5)
                                ->get($baseUrl.'/device.cgi/device-basic-config', [
                                    'action' => 'get',
                                    'format' => 'xml',
                                ]);

                            if ($response->successful()) {
                                // Simple XML parsing
                                $xml = simplexml_load_string($response->body());
                                $model = 'Matrix COSEC';
                                if ($xml && isset($xml->name)) {
                                    $model = (string) $xml->name;
                                }

                                $record->update([
                                    'status' => 'online',
                                    'model' => $model,
                                    'last_activity_at' => now(),
                                ]);

                                Notification::make()
                                    ->title('Device Online')
                                    ->body("Model: {$model}")
                                    ->success()
                                    ->send();
                            } else {
                                $record->update(['status' => 'offline']);
                                Notification::make()
                                    ->title('Device Offline')
                                    ->body('Device returned error: '.$response->status())
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
            ])
            ->toolbarActions([
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
