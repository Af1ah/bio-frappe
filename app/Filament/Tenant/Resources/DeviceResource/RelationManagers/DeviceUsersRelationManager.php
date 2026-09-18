<?php

namespace App\Filament\Tenant\Resources\DeviceResource\RelationManagers;

use App\Models\Device;
use App\Models\DeviceCommand;
use App\Models\DeviceUser;
use App\Models\User;
use App\Services\Attendance\UserDeviceSyncService;
use App\Services\DeviceCommandCapabilities;
use App\Services\DeviceCommandDispatcher;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class DeviceUsersRelationManager extends RelationManager
{
    protected static string $relationship = 'deviceUsers';

    protected static ?string $recordTitleAttribute = 'pin';

    protected static ?string $title = 'Terminal Enrolled Users';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('pin')
                ->label('PIN / User ID')
                ->required()
                ->disabled(),
            TextInput::make('name')
                ->required(),
            Select::make('privilege')
                ->options([
                    0 => 'Standard User',
                    14 => 'Device Administrator',
                ])
                ->default(0),
            TextInput::make('card_number')
                ->label('RFID Card Number'),
        ]);
    }

    public function table(Table $table): Table
    {
        /** @var Device $device */
        $device = $this->getOwnerRecord();

        return $table
            ->recordTitleAttribute('pin')
            ->columns([
                TextColumn::make('pin')
                    ->label('PIN / Hardware ID')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                TextColumn::make('name')
                    ->label('Terminal Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('Software Employee')
                    ->searchable()
                    ->placeholder('Not registered in software')
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'gray'),

                TextColumn::make('card_number')
                    ->label('RFID Card')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('privilege')
                    ->label('Privilege')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ((int) $state) {
                        14 => 'Device Admin',
                        default => 'Standard User',
                    })
                    ->color(fn ($state) => (int) $state === 14 ? 'warning' : 'gray'),

                TextColumn::make('credentials')
                    ->label('Credentials & Biometrics')
                    ->getStateUsing(function (DeviceUser $record): string {
                        $parts = [];
                        if ($record->fingerprint_count > 0) {
                            $parts[] = "FP: {$record->fingerprint_count}";
                        }
                        if ($record->face_count > 0) {
                            $parts[] = "Face: {$record->face_count}";
                        }
                        if (filled($record->card_number)) {
                            $parts[] = 'Card';
                        }
                        if ($record->user?->device_password) {
                            $parts[] = 'PIN Pass';
                        }

                        return empty($parts) ? 'Basic PIN' : implode(' | ', $parts);
                    })
                    ->badge()
                    ->color('info'),

                TextColumn::make('last_seen_at')
                    ->label('Last Synced')
                    ->dateTime('M j, Y H:i:s')
                    ->description(fn (DeviceUser $record): string => $record->last_seen_at ? $record->last_seen_at->diffForHumans() : 'Pending sync')
                    ->sortable(),
            ])
            ->defaultSort('pin', 'asc')
            ->filters([
                SelectFilter::make('privilege')
                    ->options([
                        0 => 'Standard Users',
                        14 => 'Device Administrators',
                    ]),
            ])
            ->headerActions([
                Action::make('refreshFromTerminal')
                    ->label('Refresh Users from Terminal')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->visible(fn (): bool => app(DeviceCommandCapabilities::class)->supports($device, 'fetch_users'))
                    ->requiresConfirmation()
                    ->modalHeading("Refresh Users from {$device->name}")
                    ->modalDescription('Queues a DATA QUERY USERINFO command. The terminal will upload its user list on its next heartbeat poll.')
                    ->action(function () use ($device): void {
                        $command = DeviceCommand::create([
                            'device_id' => $device->id,
                            'command_type' => 'fetch_users',
                            'command_content' => 'Query device user list (DATA QUERY USERINFO)',
                            'status' => 'pending',
                        ]);
                        app(DeviceCommandDispatcher::class)->dispatch($device, $command);

                        Notification::make()
                            ->title('User Query Queued')
                            ->body("Command queued for {$device->name}. Terminal will upload users on its next poll.")
                            ->success()
                            ->send();
                    }),

                Action::make('enrollSoftwareUser')
                    ->label('Enroll Software User')
                    ->icon('heroicon-o-user-plus')
                    ->color('primary')
                    ->form([
                        Select::make('user_id')
                            ->label('Select Software Employee')
                            ->options(fn () => User::query()->orderBy('name')->get()->mapWithKeys(fn ($u) => [$u->id => "{$u->name} (PIN: {$u->pin})"]))
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
                    ->action(function (array $data) use ($device): void {
                        $user = User::findOrFail($data['user_id']);
                        $res = app(UserDeviceSyncService::class)->uploadUserToDevice($device, $user, $data['credentials']);

                        Notification::make()
                            ->title('User Upload Queued')
                            ->body("User {$user->name} (PIN: {$user->pin}) queued for upload with {$res['commands_queued']} command(s).")
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                Action::make('editOnDevice')
                    ->label('Edit on Device')
                    ->icon('heroicon-o-pencil-square')
                    ->color('primary')
                    ->form([
                        TextInput::make('name')
                            ->label('Employee Name on Terminal')
                            ->required()
                            ->default(fn (DeviceUser $record): string => (string) $record->name),
                        Select::make('privilege')
                            ->label('Device Privilege')
                            ->options([
                                0 => 'Standard User',
                                14 => 'Device Administrator',
                            ])
                            ->default(fn (DeviceUser $record): int => (int) $record->privilege)
                            ->required(),
                        TextInput::make('card_number')
                            ->label('RFID Card Number')
                            ->default(fn (DeviceUser $record): ?string => $record->card_number),
                        TextInput::make('password')
                            ->label('Device Keypad Password')
                            ->password()
                            ->revealable()
                            ->helperText('Leave empty to keep existing password.'),
                        Toggle::make('sync_software_user')
                            ->label('Sync changes to software employee profile')
                            ->default(true)
                            ->helperText('Updates the employee record in software if matched by PIN.'),
                    ])
                    ->action(function (DeviceUser $record, array $data) use ($device): void {
                        $payload = [
                            'pin' => $record->pin,
                            'name' => $data['name'],
                            'privilege' => (int) $data['privilege'],
                        ];
                        if (filled($data['card_number'] ?? null)) {
                            $payload['card'] = $data['card_number'];
                        }
                        if (filled($data['password'] ?? null)) {
                            $payload['password'] = $data['password'];
                        }

                        $command = DeviceCommand::create([
                            'device_id' => $device->id,
                            'command_type' => 'upload_user',
                            'command_content' => json_encode($payload),
                            'status' => 'pending',
                        ]);
                        app(DeviceCommandDispatcher::class)->dispatch($device, $command);

                        $record->update([
                            'name' => $data['name'],
                            'privilege' => (int) $data['privilege'],
                            'card_number' => $data['card_number'] ?? null,
                            'last_seen_at' => now(),
                        ]);

                        if (! empty($data['sync_software_user']) && $record->user) {
                            $softwareUpdates = [
                                'name' => $data['name'],
                                'privilege' => (int) $data['privilege'],
                                'card_number' => $data['card_number'] ?? null,
                            ];
                            if (filled($data['password'] ?? null)) {
                                $softwareUpdates['device_password'] = $data['password'];
                            }
                            $record->user->update($softwareUpdates);
                        }

                        Notification::make()
                            ->title('User Updated on Terminal')
                            ->body("Update command queued for PIN {$record->pin} on {$device->name}.")
                            ->success()
                            ->send();
                    }),

                Action::make('triggerEnrollment')
                    ->label('Enroll Biometrics')
                    ->icon('heroicon-o-finger-print')
                    ->color('warning')
                    ->visible(fn (): bool => $device->supportsEnrollment('fingerprint') || $device->supportsEnrollment('face') || $device->supportsEnrollment('face_v2'))
                    ->form(function () use ($device): array {
                        $methodOptions = [];
                        if ($device->supportsEnrollment('fingerprint')) {
                            $methodOptions['fingerprint'] = 'Fingerprint';
                        }
                        if ($device->supportsEnrollment('face') || $device->supportsEnrollment('face_v2')) {
                            $methodOptions['face'] = 'Face';
                        }

                        return [
                            Radio::make('method')
                                ->label('Biometric Method to Enroll')
                                ->options($methodOptions)
                                ->default(array_key_first($methodOptions))
                                ->required()
                                ->live(),
                            Select::make('finger_index')
                                ->label('Finger Position')
                                ->options([
                                    0 => '0 - Right Thumb',
                                    1 => '1 - Right Index',
                                    2 => '2 - Right Middle',
                                    3 => '3 - Right Ring',
                                    4 => '4 - Right Little',
                                    5 => '5 - Left Thumb',
                                    6 => '6 - Left Index',
                                    7 => '7 - Left Middle',
                                    8 => '8 - Left Ring',
                                    9 => '9 - Left Little',
                                ])
                                ->default(0)
                                ->visible(fn ($get): bool => $get('method') === 'fingerprint')
                                ->required(fn ($get): bool => $get('method') === 'fingerprint'),
                        ];
                    })
                    ->action(function (DeviceUser $record, array $data) use ($device): void {
                        $method = $data['method'] ?? 'fingerprint';
                        $fingerIndex = (int) ($data['finger_index'] ?? 0);

                        app(UserDeviceSyncService::class)->triggerOnDeviceEnrollment(
                            $device,
                            $record->pin,
                            $method,
                            $fingerIndex
                        );

                        Notification::make()
                            ->title('Enrollment Queued')
                            ->body("Enrollment prompt queued for PIN {$record->pin}. Terminal LCD will instruct user to scan.")
                            ->success()
                            ->send();
                    }),

                Action::make('reuploadCredentials')
                    ->label('Push Credentials')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('info')
                    ->visible(fn (DeviceUser $record): bool => $record->user !== null)
                    ->form([
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
                    ->action(function (DeviceUser $record, array $data) use ($device): void {
                        $res = app(UserDeviceSyncService::class)->uploadUserToDevice($device, $record->user, $data['credentials']);

                        Notification::make()
                            ->title('Upload Queued')
                            ->body("Queued {$res['commands_queued']} command(s) to re-sync PIN {$record->pin} to {$device->name}.")
                            ->success()
                            ->send();
                    }),

                Action::make('deleteFromDevice')
                    ->label('Delete from Terminal')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(fn (DeviceUser $record) => "Delete User {$record->name} (PIN: {$record->pin})")
                    ->modalDescription(fn (DeviceUser $record) => "Are you sure you want to delete user PIN {$record->pin} from {$device->name}? This will erase their identity and biometric templates on this physical device.")
                    ->action(function (DeviceUser $record) use ($device): void {
                        app(UserDeviceSyncService::class)->deleteUserFromDevice($device, $record->pin);

                        $record->delete();

                        Notification::make()
                            ->title('User Deleted from Device')
                            ->body("User PIN {$record->pin} deletion queued for {$device->name}.")
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('deleteSelectedFromDevice')
                        ->label('Delete Selected from Terminal')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) use ($device): void {
                            $syncService = app(UserDeviceSyncService::class);
                            foreach ($records as $record) {
                                $syncService->deleteUserFromDevice($device, $record->pin);
                                $record->delete();
                            }

                            Notification::make()
                                ->title('Bulk Deletion Queued')
                                ->body(count($records)." user(s) queued for deletion from {$device->name}.")
                                ->success()
                                ->send();
                        }),

                    BulkAction::make('reuploadSelected')
                        ->label('Re-upload Selected to Terminal')
                        ->icon('heroicon-o-arrow-up-tray')
                        ->color('primary')
                        ->action(function (Collection $records) use ($device): void {
                            $syncService = app(UserDeviceSyncService::class);
                            $count = 0;
                            foreach ($records as $record) {
                                if ($record->user) {
                                    $syncService->uploadUserToDevice($device, $record->user, ['pin', 'card', 'fingerprint', 'face']);
                                    $count++;
                                }
                            }

                            Notification::make()
                                ->title('Bulk Upload Queued')
                                ->body("{$count} matched user(s) queued for upload to {$device->name}.")
                                ->success()
                                ->send();
                        }),
                ]),
            ]);
    }
}
