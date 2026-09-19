<?php

namespace App\Filament\Tenant\Resources;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
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
        return true;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            \Filament\Infolists\Components\TextEntry::make('serial_number'),
            \Filament\Infolists\Components\TextEntry::make('name'),
            \Filament\Infolists\Components\TextEntry::make('ip_address')
                ->label('IP Address'),
            \Filament\Infolists\Components\TextEntry::make('port')
                ->label('Port'),
            \Filament\Infolists\Components\TextEntry::make('options.location')
                ->label('Location'),
            \Filament\Infolists\Components\TextEntry::make('options.direction')
                ->label('Direction'),
            \Filament\Infolists\Components\TextEntry::make('last_activity_at')
                ->label('Last Ping')
                ->dateTime(),
        ]);
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
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('options.location')
                    ->label('Location')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('options.direction')
                    ->label('Direction')
                    ->badge()
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
                    ViewAction::make(),
                    \Filament\Actions\EditAction::make()
                        ->form([
                            \Filament\Schemas\Components\Grid::make(2)->schema([
                                \Filament\Forms\Components\TextInput::make('serial_number')
                                    ->required()
                                    ->label('Serial Number')
                                    ->columnSpan('full'),
                                \Filament\Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->label('Device Name'),
                                \Filament\Forms\Components\TextInput::make('options.location')
                                    ->required()
                                    ->label('Location'),
                                \Filament\Forms\Components\TextInput::make('ip_address')
                                    ->label('IP Address')
                                    ->placeholder('192.168.1.201'),
                                \Filament\Forms\Components\TextInput::make('port')
                                    ->label('Port')
                                    ->numeric()
                                    ->default(4370),
                                \Filament\Forms\Components\Select::make('options.direction')
                                    ->options([
                                        'device based' => 'Device Based',
                                        'IN' => 'IN',
                                        'OUT' => 'OUT',
                                        'IN/OUT altering' => 'IN/OUT Alternating',
                                        'OTHER' => 'OTHER',
                                    ])
                                    ->required()
                                    ->label('Direction'),
                                \Filament\Forms\Components\TextInput::make('options.timezone')
                                    ->default('Asia/Kolkata')
                                    ->label('Time Zone'),
                            ])
                        ])
                        ->after(function (Device $record) {
                            $dir = $record->options['direction'] ?? '';
                            $behavior = match ($dir) {
                                'IN' => 'always_in',
                                'OUT' => 'always_out',
                                'IN/OUT altering' => 'auto',
                                'device based' => 'device_state',
                                default => 'device_state',
                            };
                            $record->update(['punch_behavior' => $behavior]);
                        }),
                    \Filament\Actions\Action::make('testConnection')
                        ->label('Test Connection')
                        ->icon('heroicon-o-signal')
                        ->color('success')
                        ->action(function (Device $record) {
                            $command = \App\Models\DeviceCommand::create([
                                'device_id' => $record->id,
                                'command_type' => 'test_connection',
                                'command_content' => 'Test connection and sync status',
                                'status' => 'pending',
                            ]);
                            \App\Jobs\EbioDeviceCommandJob::dispatch(tenancy()->tenant, $record->serial_number, 'test_connection', $command->id);
                            \Filament\Notifications\Notification::make()
                                ->title('Command Queued')
                                ->body('Connection test queued in background.')
                                ->success()
                                ->send();
                        }),
                    \Filament\Actions\Action::make('fetchAttendance')
                        ->label('Fetch Attendance')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('info')
                        ->action(function (Device $record) {
                            $command = \App\Models\DeviceCommand::create([
                                'device_id' => $record->id,
                                'command_type' => 'fetch_attendance',
                                'command_content' => 'Fetch attendance logs from device',
                                'status' => 'pending',
                            ]);
                            \App\Jobs\EbioDeviceCommandJob::dispatch(tenancy()->tenant, $record->serial_number, 'fetch_attendance', $command->id);
                            \Filament\Notifications\Notification::make()
                                ->title('Command Queued')
                                ->body('Attendance fetch queued in background.')
                                ->success()
                                ->send();
                        }),
                    \Filament\Actions\Action::make('unlockDoor')
                        ->label('Unlock Door')
                        ->icon('heroicon-o-lock-open')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function (Device $record) {
                            $command = \App\Models\DeviceCommand::create([
                                'device_id' => $record->id,
                                'command_type' => 'unlock_door',
                                'command_content' => 'Door unlock pulse',
                                'status' => 'pending',
                            ]);
                            \App\Jobs\EbioDeviceCommandJob::dispatch(tenancy()->tenant, $record->serial_number, 'unlock_door', $command->id);
                            \Filament\Notifications\Notification::make()
                                ->title('Command Queued')
                                ->body('Door unlock command queued.')
                                ->success()
                                ->send();
                        }),
                    \Filament\Actions\Action::make('reboot')
                        ->label('Reboot Device')
                        ->icon('heroicon-o-power')
                        ->requiresConfirmation()
                        ->action(function (Device $record) {
                            $command = \App\Models\DeviceCommand::create([
                                'device_id' => $record->id,
                                'command_type' => 'reboot',
                                'command_content' => 'Reboot device',
                                'status' => 'pending',
                            ]);
                            \App\Jobs\EbioDeviceCommandJob::dispatch(tenancy()->tenant, $record->serial_number, 'reboot', $command->id);
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
                                'command_content' => 'Clear device attendance logs',
                                'status' => 'pending',
                            ]);
                            \App\Jobs\EbioDeviceCommandJob::dispatch(tenancy()->tenant, $record->serial_number, 'clear_logs', $command->id);
                            \Filament\Notifications\Notification::make()
                                ->title('Command Queued')
                                ->body('Clear logs command queued.')
                                ->success()
                                ->send();
                        }),
                    \Filament\Actions\Action::make('resetTransactionStamp')
                        ->label('Reset Transaction Stamp')
                        ->icon('heroicon-o-arrow-path')
                        ->requiresConfirmation()
                        ->action(function (Device $record) {
                            $command = \App\Models\DeviceCommand::create([
                                'device_id' => $record->id,
                                'command_type' => 'reset_transaction_stamp',
                                'command_content' => 'Reset transaction stamp to 0',
                                'status' => 'pending',
                            ]);
                            \App\Jobs\EbioDeviceCommandJob::dispatch(tenancy()->tenant, $record->serial_number, 'reset_transaction_stamp', $command->id);
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
                                'command_content' => 'Reset OP stamp to 0',
                                'status' => 'pending',
                            ]);
                            \App\Jobs\EbioDeviceCommandJob::dispatch(tenancy()->tenant, $record->serial_number, 'reset_op_stamp', $command->id);
                            \Filament\Notifications\Notification::make()
                                ->title('Command Queued')
                                ->body('Reset OP stamp command queued.')
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
        ];
    }
}
