<?php

namespace App\Filament\Tenant\Resources;

use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use App\Filament\Tenant\Resources\AttendanceLogResource\Pages;
use App\Models\AttendanceLog;
use Illuminate\Database\Eloquent\Collection;

class AttendanceLogResource extends Resource
{
    protected static ?string $model = AttendanceLog::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static \UnitEnum|string|null $navigationGroup = 'Device Management';

    protected static ?int $navigationSort = 99;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Attendance Details')
                ->schema([
                    Select::make('device_id')
                        ->relationship('device', 'serial_number')
                        ->required(),
                    TextInput::make('pin')
                        ->required(),
                    DateTimePicker::make('punched_at')
                        ->required(),
                    Select::make('status')
                        ->options([
                            0 => 'Check In',
                            1 => 'Check Out',
                            2 => 'Break Out',
                            3 => 'Break In',
                            4 => 'OT In',
                            5 => 'OT Out',
                        ]),
                    Select::make('verify_type')
                        ->options([
                            0 => 'Password',
                            1 => 'Fingerprint',
                            2 => 'Card',
                            15 => 'Face',
                        ]),
                ])
                ->columns(2)
                ->columnSpanFull(),

            Section::make('Frappe HR Integration')
                ->schema([
                    DateTimePicker::make('frappe_synced_at')
                        ->label('Synced At')
                        ->disabled(),
                    TextInput::make('frappe_log_id')
                        ->label('Frappe Checkin ID')
                        ->disabled(),
                    TextInput::make('frappe_error')
                        ->label('Sync Error / Notes')
                        ->disabled()
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->columnSpanFull()
                ->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('device.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('pin')
                    ->label('User PIN')
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User Name')
                    ->placeholder('Unknown'),
                Tables\Columns\TextColumn::make('punched_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        0 => 'Check In',
                        1 => 'Check Out',
                        2 => 'Break Out',
                        3 => 'Break In',
                        4 => 'OT In',
                        5 => 'OT Out',
                        default => 'Unknown',
                    })
                    ->color(fn ($state): string => match ($state) {
                        0 => 'success',
                        1 => 'danger',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('verify_type_label')
                    ->label('Verify Type'),
                Tables\Columns\TextColumn::make('frappe_status')
                    ->label('Frappe HR')
                    ->state(function (AttendanceLog $record): string {
                        if ($record->frappe_synced_at) {
                            return 'Synced';
                        }
                        if ($record->frappe_error) {
                            return 'Failed';
                        }
                        return 'Pending';
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Synced' => 'success',
                        'Failed' => 'danger',
                        default => 'gray',
                    })
                    ->tooltip(fn (AttendanceLog $record): ?string => $record->frappe_error 
                        ?: ($record->frappe_synced_at ? "Synced: {$record->frappe_synced_at->format('Y-m-d H:i:s')} ({$record->frappe_log_id})" : 'Not synced yet')),
            ])
            ->defaultSort('punched_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('device')
                    ->relationship('device', 'serial_number'),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        0 => 'Check In',
                        1 => 'Check Out',
                        2 => 'Break Out',
                        3 => 'Break In',
                        4 => 'OT In',
                        5 => 'OT Out',
                    ]),
                Tables\Filters\SelectFilter::make('frappe_status')
                    ->label('Frappe HR Status')
                    ->options([
                        'synced' => 'Synced',
                        'failed' => 'Failed',
                        'pending' => 'Pending',
                    ])
                    ->query(function ($query, array $data) {
                        $value = $data['value'] ?? null;
                        if ($value === 'synced') {
                            return $query->whereNotNull('frappe_synced_at');
                        }
                        if ($value === 'failed') {
                            return $query->whereNull('frappe_synced_at')->whereNotNull('frappe_error');
                        }
                        if ($value === 'pending') {
                            return $query->whereNull('frappe_synced_at')->whereNull('frappe_error');
                        }
                        return $query;
                    }),
                Tables\Filters\Filter::make('punched_at')
                    ->schema([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('punched_at', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('punched_at', '<=', $date));
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('syncFrappe')
                    ->label('Push to Frappe HR')
                    ->icon('heroicon-o-cloud-arrow-up')
                    ->color('info')
                    ->action(function (AttendanceLog $record) {
                        $frappe = app(\App\Services\FrappeHrService::class);
                        $res = $frappe->syncAttendanceCheckin($record);
                        if (!empty($res['success'])) {
                            Notification::make()
                                ->title('Pushed to Frappe HR')
                                ->body("Checkin created for PIN {$record->pin}")
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Frappe HR Push Failed')
                                ->body($res['error'] ?? 'Unknown error')
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('syncFrappeBulk')
                        ->label('Push to Frappe HR')
                        ->icon('heroicon-o-cloud-arrow-up')
                        ->color('info')
                        ->action(function (Collection $records) {
                            $tenant = tenancy()->tenant;
                            foreach ($records as $record) {
                                \App\Jobs\SyncFrappeCheckinJob::dispatch($record, $tenant);
                            }
                            Notification::make()
                                ->title('Queued for Frappe HR Sync')
                                ->body("{$records->count()} attendance logs queued for background sync.")
                                ->success()
                                ->send();
                        }),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAttendanceLogs::route('/'),
            'view' => Pages\ViewAttendanceLog::route('/{record}'),
        ];
    }
}
