<?php

namespace App\Filament\Tenant\Resources;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use App\Filament\Tenant\Resources\DeviceResource\Pages;
use App\Filament\Tenant\Resources\DeviceResource\RelationManagers;

use App\Models\Device;

class DeviceResource extends Resource
{
    protected static ?string $model = Device::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?int $navigationSort = 1;

    protected static \UnitEnum|string|null $navigationGroup = 'Device Management';

    /** @return array<int, string> */
    public static function directDeviceOptions(): array
    {
        return Device::query()
            ->orderBy('name')
            ->get()
            ->filter(fn (Device $device): bool => (bool) data_get($device->options, 'adms_enabled', false))
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

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return true;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            \Filament\Infolists\Components\TextEntry::make('serial_number'),
            \Filament\Infolists\Components\TextEntry::make('name'),
            \Filament\Infolists\Components\TextEntry::make('options.location')
                ->label('Location'),
            \Filament\Infolists\Components\TextEntry::make('last_activity_at')
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
                    \Filament\Schemas\Components\Grid::make(2)->schema([
                        TextInput::make('serial_number')
                            ->label('Serial Number')
                            ->disabled(),
                        TextInput::make('name')
                            ->label('Device Name')
                            ->required(),
                        TextInput::make('ip_address')
                            ->label('Device IP Address')
                            ->ipv4(),
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
                        TextInput::make('options.type')
                            ->label('Device Type')
                            ->default('Attendance')
                            ->visible(fn (callable $get): bool => ! (bool) $get('options.adms_enabled'))
                            ->required(fn (callable $get): bool => ! (bool) $get('options.adms_enabled')),
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
                        static::admsToggle()
                            ->columnSpanFull(),
                    ]),
                ])
                ->columnSpanFull(),
        ]);
    }

    public static function admsToggle(string $statePath = 'options.adms_enabled'): Toggle
    {
        $host = (string) config('services.device_gateway.device_host');
        $port = (string) config('services.device_gateway.device_port');

        return Toggle::make($statePath)
            ->label('Enable standalone ADMS')
            ->default(false)
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
                Tables\Columns\TextColumn::make('options.location')
                    ->label('Location')
                    ->searchable()
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
            ])
            ->recordActions([
                \Filament\Actions\ActionGroup::make([
                    EditAction::make(),
                    \Filament\Actions\Action::make('syncTime')
                        ->label('Sync Device Time')
                        ->icon('heroicon-o-clock')
                        ->visible(fn (Device $record): bool => (bool) data_get($record->options, 'adms_enabled', false))
                        ->requiresConfirmation()
                        ->action(function (Device $record): void {
                            $command = \App\Models\DeviceCommand::create([
                                'device_id' => $record->id,
                                'command_type' => 'sync_time',
                                'command_content' => 'Direct command: sync device time',
                                'status' => 'pending',
                            ]);
                            app(\App\Services\DeviceCommandDispatcher::class)->dispatch($record, $command);
                        }),
                    \Filament\Actions\Action::make('reboot')
                        ->label('Reboot Device')
                        ->icon('heroicon-o-power')
                        ->requiresConfirmation()
                        ->action(function (Device $record) {
                            $command = \App\Models\DeviceCommand::create([
                                'device_id' => $record->id,
                                'command_type' => 'reboot',
                                'command_content' => 'Device command: reboot',
                                'status' => 'pending',
                            ]);
                            app(\App\Services\DeviceCommandDispatcher::class)->dispatch($record, $command);
                            \Filament\Notifications\Notification::make()
                                ->title('Command Queued')
                                ->body('Reboot command queued.')
                                ->success()
                                ->send();
                        }),
                    \Filament\Actions\Action::make('clearLogs')
                        ->label('Clear Logs')
                        ->icon('heroicon-o-trash')
                        ->requiresConfirmation()
                        ->color('danger')
                        ->action(function (Device $record) {
                            $command = \App\Models\DeviceCommand::create([
                                'device_id' => $record->id,
                                'command_type' => 'clear_logs',
                                'command_content' => 'Device command: clear_logs',
                                'status' => 'pending',
                            ]);
                            app(\App\Services\DeviceCommandDispatcher::class)->dispatch($record, $command);
                            \Filament\Notifications\Notification::make()
                                ->title('Command Queued')
                                ->body('Clear logs command queued.')
                                ->success()
                                ->send();
                        }),
                    \Filament\Actions\Action::make('resetTransactionStamp')
                        ->label('Reset Transaction Stamp')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->requiresConfirmation()
                        ->action(function (Device $record) {
                            $command = \App\Models\DeviceCommand::create([
                                'device_id' => $record->id,
                                'command_type' => 'reset_transaction_stamp',
                                'command_content' => 'Device command: reset_transaction_stamp',
                                'status' => 'pending',
                            ]);
                            app(\App\Services\DeviceCommandDispatcher::class)->dispatch($record, $command);
                            \Filament\Notifications\Notification::make()
                                ->title('Command Queued')
                                ->body('Reset transaction stamp command queued.')
                                ->success()
                                ->send();
                        }),
                    \Filament\Actions\Action::make('resetOPStamp')
                        ->label('Reset OP Stamp')
                        ->icon('heroicon-o-arrow-path')
                        ->requiresConfirmation()
                        ->action(function (Device $record) {
                            $command = \App\Models\DeviceCommand::create([
                                'device_id' => $record->id,
                                'command_type' => 'reset_op_stamp',
                                'command_content' => 'Device command: reset_op_stamp',
                                'status' => 'pending',
                            ]);
                            app(\App\Services\DeviceCommandDispatcher::class)->dispatch($record, $command);
                            \Filament\Notifications\Notification::make()
                                ->title('Command Queued')
                                ->body('Reset OP stamp command queued.')
                                ->success()
                                ->send();
                        }),
                    \Filament\Actions\Action::make('unlockDoor')
                        ->label('Unlock Door')
                        ->icon('heroicon-o-lock-open')
                        ->requiresConfirmation()
                        ->action(function (Device $record) {
                            $command = \App\Models\DeviceCommand::create([
                                'device_id' => $record->id,
                                'command_type' => 'unlock_door',
                                'command_content' => 'Device command: unlock_door',
                                'status' => 'pending',
                            ]);
                            app(\App\Services\DeviceCommandDispatcher::class)->dispatch($record, $command);
                            \Filament\Notifications\Notification::make()
                                ->title('Command Queued')
                                ->body('Unlock door command queued.')
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
