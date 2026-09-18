<?php

namespace App\Filament\Tenant\Resources\DeviceResource\Pages;

use App\Filament\Tenant\Resources\DeviceResource;
use App\Models\Device;
use App\Models\DeviceCommand;
use App\Models\User;
use App\Services\Attendance\UserDeviceSyncService;
use App\Services\DeviceCommandCapabilities;
use App\Services\DeviceCommandDispatcher;
use Filament\Actions;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;

class ViewDevice extends ViewRecord
{
    protected static string $resource = DeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('checkStatus')
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

            Actions\Action::make('fetchDeviceInfo')
                ->label('Fetch Info')
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

            Actions\Action::make('fetchUsers')
                ->label('Query Users')
                ->icon('heroicon-o-arrow-path')
                ->visible(fn (Device $record): bool => app(DeviceCommandCapabilities::class)->supports($record, 'fetch_users'))
                ->requiresConfirmation()
                ->action(function (Device $record): void {
                    $command = DeviceCommand::create([
                        'device_id' => $record->id,
                        'command_type' => 'fetch_users',
                        'command_content' => 'Query terminal users (DATA QUERY USERINFO)',
                        'status' => 'pending',
                    ]);
                    app(DeviceCommandDispatcher::class)->dispatch($record, $command);

                    Notification::make()
                        ->title('User Query Queued')
                        ->body("Query command queued for {$record->name}. The terminal will send its user list on its next heartbeat.")
                        ->success()
                        ->send();
                }),

            Actions\Action::make('uploadUsers')
                ->label('Upload Users')
                ->icon('heroicon-o-arrow-up-tray')
                ->form([
                    Select::make('user_ids')
                        ->label('Select Software Users')
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

            Actions\EditAction::make(),
        ];
    }
}
