<?php

namespace App\Filament\Tenant\Resources;

use App\Enums\DeviceTransport;
use App\Filament\Tenant\Resources\UserResource\Pages;
use App\Jobs\BlockUnblockEbioUserJob;
use App\Jobs\DirectDeviceDataSyncJob;
use App\Jobs\EnrollEbioBiometricJob;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Device;
use App\Models\TaskGroup;
use App\Models\User;
use App\Services\Attendance\UserDeviceSyncService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'User';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'pin', 'email'];
    }

    public static function getGlobalSearchResultIcon(Model $record): string
    {
        return 'heroicon-o-user';
    }

    protected static ?string $pluralModelLabel = 'Users';

    public static function getNavigationGroup(): ?string
    {
        return null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('User Information')
                ->schema([
                    TextInput::make('pin')
                        ->label('User ID (PIN)')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->autocomplete('off'),
                    TextInput::make('name')
                        ->autocomplete('off'),
                    TextInput::make('email')
                        ->email()
                        ->unique(ignoreRecord: true)
                        ->nullable()
                        ->autocomplete('off'),
                    TextInput::make('whatsapp_number')
                        ->label('WhatsApp Number')
                        ->hint('Include country code without +, e.g., 919876543210')
                        ->tel()
                        ->nullable(),
                    TextInput::make('card_number')
                        ->label('Card Number')
                        ->autocomplete('off'),
                    Select::make('privilege')
                        ->options([
                            0 => 'User',
                            14 => 'Admin',
                        ])
                        ->default(0),
                    TextInput::make('device_password')
                        ->label('Device Password (Numeric)')
                        ->numeric()
                        ->maxLength(8)
                        ->password()
                        ->revealable()
                        ->autocomplete('new-password'),
                    DatePicker::make('valid_from')
                        ->label('Valid From')
                        ->nullable(),
                    DatePicker::make('valid_to')
                        ->label('Valid To')
                        ->nullable(),
                    Toggle::make('is_enabled')
                        ->default(true),
                ])
                ->columns(2)
                ->columnSpanFull(),
            Section::make('Enrollment Details')
                ->schema([
                    Select::make('branch_id')
                        ->relationship('branch', 'name')
                        ->getOptionLabelFromRecordUsing(fn ($record) => $record->display_name)
                        ->label('Branch')
                        ->default(fn () => Branch::count() === 1 ? Branch::first()->id : null)
                        ->live()
                        ->nullable(),
                    Select::make('department_id')
                        ->relationship('department', 'name', fn ($query, $get) => $get('branch_id')
                                ? $query->whereHas('branches', fn ($q) => $q->where('branches.id', $get('branch_id')))
                                : $query
                        )
                        ->label('Department')
                        ->default(fn () => Department::count() === 1 ? Department::first()->id : null)
                        ->nullable(),
                    Select::make('group')
                        ->label('Designation / Group')
                        ->options(User::whereNotNull('group')->where('group', '!=', '')->distinct()->pluck('group', 'group'))
                        ->searchable()
                        ->createOptionForm([
                            TextInput::make('name')->required()->label('Name'),
                        ])
                        ->createOptionUsing(fn (array $data) => $data['name'])
                        ->nullable(),
                    Select::make('taskGroups')
                        ->relationship('taskGroups', 'name')
                        ->label('Task Groups')
                        ->multiple()
                        ->searchable()
                        ->default(fn () => TaskGroup::count() === 1 ? [TaskGroup::first()->id] : [])
                        ->createOptionForm([
                            TextInput::make('name')->required(),
                            Textarea::make('description'),
                        ])
                        ->nullable(),
                ])
                ->columns(3)
                ->columnSpanFull()
                ->collapsed(),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make([
                    Section::make('User Details')
                        ->schema([
                            TextEntry::make('name')
                                ->label('Name')
                                ->weight('bold')
                                ->size('lg'),
                            TextEntry::make('pin')
                                ->label('PIN'),
                            TextEntry::make('whatsapp_number')
                                ->label('WhatsApp Number')
                                ->default('Not Provided'),
                            TextEntry::make('branch.name')
                                ->label('Branch')
                                ->default('Not Assigned'),
                            TextEntry::make('department.name')
                                ->label('Department')
                                ->default('Not Assigned'),
                            TextEntry::make('group')
                                ->label('Designation / Group')
                                ->default('Not Assigned'),
                            TextEntry::make('fingerprints')
                                ->label('Added Fingerprints')
                                ->badge()
                                ->state(function ($record) {
                                    $rawState = $record->fingerprints;
                                    if (empty($rawState) || ! is_array($rawState)) {
                                        return ['None'];
                                    }
                                    $fingers = [
                                        0 => 'Left Pinky', 1 => 'Left Ring', 2 => 'Left Middle', 3 => 'Left Index', 4 => 'Left Thumb',
                                        5 => 'Right Thumb', 6 => 'Right Index', 7 => 'Right Middle', 8 => 'Right Ring', 9 => 'Right Pinky',
                                    ];
                                    $added = [];
                                    foreach ($rawState as $key => $fp) {
                                        $id = is_numeric($key) ? (int) $key : ($fp['finger_id'] ?? $fp['fid'] ?? null);
                                        if ($id !== null && isset($fingers[$id])) {
                                            $added[] = $fingers[$id];
                                        } elseif ($id !== null) {
                                            $added[] = 'Finger '.$id;
                                        }
                                    }

                                    return count($added) > 0 ? $added : [count($rawState).' Template(s)'];
                                })
                                ->color('success')
                                ->columnSpanFull(),
                        ])->columns(['default' => 2, 'sm' => 2, 'md' => 2]),

                    Section::make('Shift Details')
                        ->schema([
                            TextEntry::make('shift')
                                ->label('Active Shift')
                                ->formatStateUsing(function ($record) {
                                    $schedule = $record->getActiveSchedule();
                                    if (! $schedule) {
                                        return 'No Active Schedule';
                                    }
                                    $rules = $schedule->rules;
                                    $time = ($rules['start_time'] ?? '--:--').' to '.($rules['end_time'] ?? '--:--');

                                    return $schedule->name.' ('.$time.')';
                                }),
                        ]),
                ])->columnSpanFull(),

                Section::make('Attendance Calendar')
                    ->schema([
                        ViewEntry::make('calendar')
                            ->hiddenLabel()
                            ->view('filament.tenant.components.attendance-calendar')
                            ->columnSpanFull(),
                    ])->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('pin')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('card_number')
                    ->label('Card')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('whatsapp_number')
                    ->label('WhatsApp Number')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('branch.name')
                    ->label('Branch')
                    ->sortable()
                    ->searchable()
                    ->toggleable()
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('department.name')
                    ->label('Department')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('group')
                    ->label('Designation/Group')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('privilege')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state === 14 ? 'Admin' : 'User')
                    ->color(fn ($state): string => $state === 14 ? 'primary' : 'gray')
                    ->visibleFrom('md'),
                Tables\Columns\IconColumn::make('is_enabled')
                    ->boolean()
                    ->visibleFrom('md'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('privilege')
                    ->options([
                        0 => 'User',
                        14 => 'Admin',
                    ])
                    ->default(0),
                Tables\Filters\SelectFilter::make('branch_id')
                    ->relationship('branch', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->display_name)
                    ->label('Branch'),
                Tables\Filters\SelectFilter::make('department_id')
                    ->relationship('department', 'name')
                    ->label('Department'),
                Tables\Filters\SelectFilter::make('group')
                    ->label('Designation / Group')
                    ->options(fn () => User::whereNotNull('group')->where('group', '!=', '')->distinct()->pluck('group', 'group')->toArray()),
                Tables\Filters\TernaryFilter::make('is_enabled')
                    ->default(true),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('enrollOnDirectDevice')
                        ->label('Enroll on Direct Device')
                        ->icon('heroicon-o-user-plus')
                        ->visible(fn (): bool => DeviceResource::directDeviceOptions() !== [])
                        ->requiresConfirmation()
                        ->form([
                            Select::make('device_id')
                                ->label('Device')
                                ->options(fn (): array => DeviceResource::directDeviceOptions())
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (User $record, array $data): void {
                            DirectDeviceDataSyncJob::dispatch(
                                tenant(),
                                (int) $data['device_id'],
                                'push_users',
                                [$record->id],
                            );

                            Notification::make()
                                ->title('Enrollment queued')
                                ->body("{$record->name} will be enrolled on the selected device.")
                                ->success()
                                ->send();
                        }),
                    Action::make('uploadToDevice')
                        ->label('Upload to Device')
                        ->icon('heroicon-o-arrow-up-tray')
                        ->color('success')
                        ->form([
                            Select::make('device_id')
                                ->label('Target Device')
                                ->options(fn (): array => Device::query()->orderBy('name')->get()->mapWithKeys(fn ($d) => [$d->id => "{$d->name} ({$d->serial_number})"])->all())
                                ->searchable()
                                ->required(),
                            CheckboxList::make('credentials')
                                ->label('Credentials to Upload')
                                ->options(function (User $record) {
                                    $fpCount = count((array) ($record->fingerprints ?? []));
                                    $faceCount = count((array) ($record->face_templates ?? [])) + count((array) ($record->face_v2_templates ?? []));

                                    return [
                                        'pin' => 'PIN & Password'.(filled($record->device_password) ? ' (Pass: Set)' : ''),
                                        'card' => 'RFID Card'.(filled($record->card_number) ? " ({$record->card_number})" : ''),
                                        'fingerprint' => "Fingerprint Templates ({$fpCount})",
                                        'face' => "Face Templates ({$faceCount})",
                                    ];
                                })
                                ->columns(2)
                                ->default(['pin', 'card', 'fingerprint', 'face'])
                                ->required(),
                        ])
                        ->action(function (User $record, array $data): void {
                            $device = Device::findOrFail((int) $data['device_id']);
                            $result = app(UserDeviceSyncService::class)->uploadUserToDevice($device, $record, $data['credentials']);

                            $body = "{$record->name} queued for upload to {$device->name}.";
                            if (! empty($result['skipped_warnings'])) {
                                $body .= ' '.implode(' ', $result['skipped_warnings']);
                            }

                            Notification::make()
                                ->title('Upload Queued')
                                ->body($body)
                                ->success()
                                ->send();
                        }),
                    Action::make('deleteFromDevice')
                        ->label('Delete from Device')
                        ->icon('heroicon-o-user-minus')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->form([
                            Select::make('device_ids')
                                ->label('Select Device(s)')
                                ->options(fn (): array => Device::query()->orderBy('name')->get()->mapWithKeys(fn ($d) => [$d->id => "{$d->name} ({$d->serial_number})"])->all())
                                ->multiple()
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (User $record, array $data): void {
                            $syncService = app(UserDeviceSyncService::class);
                            $devices = Device::whereIn('id', $data['device_ids'])->get();
                            foreach ($devices as $device) {
                                $syncService->deleteUserFromDevice($device, $record);
                            }

                            Notification::make()
                                ->title('Delete Queued')
                                ->body("{$record->name} queued for deletion from ".count($devices).' device(s).')
                                ->success()
                                ->send();
                        }),
                    Action::make('addBiometric')
                        ->label('Trigger Device Enrollment')
                        ->icon('heroicon-o-finger-print')
                        ->form([
                            Select::make('device_id')
                                ->label('Select Device')
                                ->options(function () {
                                    return Device::all()->mapWithKeys(function ($d) {
                                        return [$d->id => $d->name ?: $d->serial_number];
                                    })->toArray();
                                })
                                ->required()
                                ->searchable(),
                            Select::make('type')
                                ->label('Biometric Type')
                                ->options([
                                    'finger' => 'Fingerprint',
                                    'face' => 'Face',
                                ])
                                ->required()
                                ->live(),
                            Select::make('finger_index')
                                ->label('Select Finger')
                                ->options([
                                    0 => '0 - Left Pinky',
                                    1 => '1 - Left Ring',
                                    2 => '2 - Left Middle',
                                    3 => '3 - Left Index',
                                    4 => '4 - Left Thumb',
                                    5 => '5 - Right Thumb',
                                    6 => '6 - Right Index',
                                    7 => '7 - Right Middle',
                                    8 => '8 - Right Ring',
                                    9 => '9 - Right Pinky',
                                ])
                                ->visible(fn ($get) => $get('type') === 'finger')
                                ->required(fn ($get) => $get('type') === 'finger'),
                        ])
                        ->action(function (User $record, array $data) {
                            $device = Device::findOrFail($data['device_id']);
                            $transport = DeviceTransport::forDevice($device);

                            if ($transport === DeviceTransport::Adms) {
                                app(UserDeviceSyncService::class)->triggerOnDeviceEnrollment(
                                    $device,
                                    $record,
                                    $data['type'],
                                    (int) ($data['finger_index'] ?? 0)
                                );
                            } else {
                                EnrollEbioBiometricJob::dispatch(
                                    tenancy()->tenant,
                                    $record->id,
                                    $data['device_id'],
                                    $data['type'],
                                    $data['finger_index'] ?? null
                                );
                            }

                            Notification::make()
                                ->title('Enrollment command queued')
                                ->body("Device {$device->name} will prompt user {$record->name} for enrollment.")
                                ->success()
                                ->send();
                        }),
                    ViewAction::make(),
                    EditAction::make(),
                ]),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('enableUsers')
                        ->label('Unblock user from door')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->form([
                            Select::make('location')
                                ->label('Select Device(s)')
                                ->options(function () {
                                    return Device::all()->mapWithKeys(function ($d) {
                                        $loc = $d->options['location'] ?? null;

                                        return $loc ? [$loc => ($d->name ?: $d->serial_number)." (Location: $loc)"] : [];
                                    })->filter()->toArray();
                                })
                                ->searchable()
                                ->multiple()
                                ->placeholder('Leave blank for all devices'),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $location = ! empty($data['location']) ? (is_array($data['location']) ? implode(',', $data['location']) : $data['location']) : '';
                            $organisation = tenancy()->tenant;
                            $count = 0;
                            foreach ($records as $record) {
                                BlockUnblockEbioUserJob::dispatch($organisation, $record->id, $location, false); // false = Unblock
                                $count++;
                            }
                            Notification::make()
                                ->title('Unblocked and Queued')
                                ->body("{$count} user(s) unblocked and queued for sync.")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('disableUsers')
                        ->label('Block user from door')
                        ->icon('heroicon-o-x-circle')
                        ->color('warning')
                        ->form([
                            Select::make('location')
                                ->label('Select Device(s)')
                                ->options(function () {
                                    return Device::all()->mapWithKeys(function ($d) {
                                        $loc = $d->options['location'] ?? null;

                                        return $loc ? [$loc => ($d->name ?: $d->serial_number)." (Location: $loc)"] : [];
                                    })->filter()->toArray();
                                })
                                ->searchable()
                                ->multiple()
                                ->placeholder('Leave blank for all devices'),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $location = ! empty($data['location']) ? (is_array($data['location']) ? implode(',', $data['location']) : $data['location']) : '';
                            $organisation = tenancy()->tenant;
                            $count = 0;
                            foreach ($records as $record) {
                                BlockUnblockEbioUserJob::dispatch($organisation, $record->id, $location, true); // true = Block
                                $count++;
                            }
                            Notification::make()
                                ->title('Blocked and Queued')
                                ->body("{$count} user(s) blocked and queued for sync.")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('pushToDevice')
                        ->icon('heroicon-o-arrow-up-on-square')
                        ->color('success')
                        ->label('Push / Upload to Devices')
                        ->form([
                            Select::make('device_ids')
                                ->label('Select Device(s)')
                                ->options(fn () => Device::all()->mapWithKeys(fn ($d) => [$d->id => "{$d->name} ({$d->serial_number})"]))
                                ->searchable()
                                ->multiple()
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
                        ->action(function (Collection $records, array $data) {
                            $syncService = app(UserDeviceSyncService::class);
                            $devices = Device::whereIn('id', $data['device_ids'])->get();
                            $count = 0;
                            foreach ($devices as $device) {
                                foreach ($records as $user) {
                                    $syncService->uploadUserToDevice($device, $user, $data['credentials']);
                                    $count++;
                                }
                            }
                            Notification::make()
                                ->title('Sync Queued')
                                ->body("{$count} user push operation(s) queued across ".count($devices).' device(s).')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('deleteFromDevice')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->label('Delete from Devices')
                        ->requiresConfirmation()
                        ->form([
                            Select::make('device_ids')
                                ->label('Select Device(s)')
                                ->options(fn () => Device::all()->mapWithKeys(fn ($d) => [$d->id => "{$d->name} ({$d->serial_number})"]))
                                ->searchable()
                                ->multiple()
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $syncService = app(UserDeviceSyncService::class);
                            $devices = Device::whereIn('id', $data['device_ids'])->get();
                            $count = 0;
                            foreach ($devices as $device) {
                                foreach ($records as $record) {
                                    $syncService->deleteUserFromDevice($device, $record);
                                    $count++;
                                }
                            }
                            Notification::make()
                                ->title('Deletion Queued')
                                ->body("{$count} user deletion operation(s) queued across ".count($devices).' device(s).')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('assignCategory')
                        ->label('Assign Category')
                        ->icon('heroicon-o-tag')
                        ->form([
                            Select::make('branch_id')
                                ->label('Branch')
                                ->options(Branch::all()->pluck('display_name', 'id'))
                                ->default(fn () => Branch::count() === 1 ? Branch::first()->id : null)
                                ->live()
                                ->nullable(),
                            Select::make('department_id')
                                ->label('Department')
                                ->options(function ($get) {
                                    $branchId = $get('branch_id');
                                    if (! $branchId) {
                                        return Department::pluck('name', 'id');
                                    }

                                    return Department::whereHas('branches', fn ($q) => $q->where('branches.id', $branchId))->pluck('name', 'id');
                                })
                                ->default(fn () => Department::count() === 1 ? Department::first()->id : null)
                                ->nullable(),
                            Select::make('group')
                                ->label('Designation / Group')
                                ->options(User::whereNotNull('group')->where('group', '!=', '')->distinct()->pluck('group', 'group'))
                                ->searchable()
                                ->createOptionForm([
                                    TextInput::make('name')->required()->label('Name'),
                                ])
                                ->createOptionUsing(fn (array $data) => $data['name'])
                                ->nullable(),
                            Select::make('taskGroups')
                                ->label('Task Groups')
                                ->multiple()
                                ->options(TaskGroup::pluck('name', 'id'))
                                ->searchable()
                                ->default(fn () => TaskGroup::count() === 1 ? [TaskGroup::first()->id] : [])
                                ->createOptionForm([
                                    TextInput::make('name')->required(),
                                    Textarea::make('description'),
                                ])
                                ->createOptionUsing(function (array $data) {
                                    $taskGroup = TaskGroup::create($data);

                                    return $taskGroup->id;
                                })
                                ->nullable(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $updateData = [];
                            if (array_key_exists('branch_id', $data) && $data['branch_id'] !== null) {
                                $updateData['branch_id'] = $data['branch_id'];
                            }
                            if (array_key_exists('department_id', $data) && $data['department_id'] !== null) {
                                $updateData['department_id'] = $data['department_id'];
                            }
                            if (array_key_exists('group', $data) && $data['group'] !== null) {
                                $updateData['group'] = $data['group'];
                            }

                            if (! empty($updateData)) {
                                foreach ($records as $record) {
                                    $record->update($updateData);
                                }
                            }

                            if (! empty($data['taskGroups'])) {
                                foreach ($records as $record) {
                                    $record->taskGroups()->syncWithoutDetaching($data['taskGroups']);
                                }
                            }

                            Notification::make()
                                ->title('Success')
                                ->body('Categories assigned to selected users.')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
