<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\DeviceCommandResource\Pages;
use App\Models\Device;
use App\Models\DeviceCommand;
use App\Services\DeviceCommandCapabilities;
use App\Services\DeviceCommandDispatcher;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class DeviceCommandResource extends Resource
{
    protected static ?string $model = DeviceCommand::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-command-line';

    protected static \UnitEnum|string|null $navigationGroup = 'Device Management';

    protected static ?int $navigationSort = 4;

    public static function getNavigationGroup(): ?string
    {
        return 'Device Management';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Command Details')
                ->schema([
                    Select::make('device_id')
                        ->relationship('device', 'serial_number')
                        ->required()
                        ->searchable()
                        ->live(),
                    Select::make('command_type')
                        ->options(function (callable $get): array {
                            $device = $get('device_id') ? Device::find($get('device_id')) : null;

                            return $device ? app(DeviceCommandCapabilities::class)->options($device) : [];
                        })
                        ->required()
                        ->live(),
                    Textarea::make('command_content')
                        ->required()
                        ->rows(3),
                    Select::make('status')
                        ->options([
                            'pending' => 'Pending',
                            'sent' => 'Sent',
                            'acknowledged' => 'Acknowledged',
                            'failed' => 'Failed',
                        ])
                        ->default('pending')
                        ->disabled(),
                ])
                ->columns(2)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('device.name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('command_type')
                    ->badge(),
                Tables\Columns\TextColumn::make('command_content')
                    ->limit(40)
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('transport')
                    ->badge(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'sent' => 'info',
                        'acknowledged' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('delivery_status')
                    ->label('Delivery')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'acknowledged' => 'success',
                        'failed', 'outcome_unknown' => 'danger',
                        'delivered', 'sent' => 'info',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('protocol_command_id')
                    ->label('Protocol ID')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('result_code')
                    ->label('Result')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('sent_at')
                    ->dateTime()
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('acknowledged_at')
                    ->dateTime()
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('retry_count')
                    ->label('Retries')
                    ->visibleFrom('md'),
            ])
            ->poll(fn () => DeviceCommand::whereIn('delivery_status', ['queued', 'accepting', 'sent', 'delivered'])->exists() ? '10s' : null)
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('device')
                    ->relationship('device', 'serial_number'),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'sent' => 'Sent',
                        'acknowledged' => 'Acknowledged',
                        'failed' => 'Failed',
                    ]),
                Tables\Filters\SelectFilter::make('command_type')
                    ->options([
                        'device_info' => 'Fetch Device Info', 'check' => 'Check Connection', 'sync_time' => 'Sync Device Time',
                        'reboot' => 'Reboot Device', 'clear_logs' => 'Clear Logs',
                        'reset_transaction_stamp' => 'Reset Transaction Stamp', 'reset_op_stamp' => 'Reset OP Stamp',
                        'unlock_door' => 'Unlock Door',
                    ]),
                Tables\Filters\SelectFilter::make('transport')
                    ->options(['ebio' => 'eBioServer', 'direct' => 'Direct LAN', 'adms' => 'Standalone ADMS']),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    Action::make('retry')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->visible(fn (DeviceCommand $record) => in_array($record->status, ['failed', 'sent']))
                        ->action(function (DeviceCommand $record): void {
                            $record->retry();
                            app(DeviceCommandDispatcher::class)->dispatch($record->device, $record);
                        }),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDeviceCommands::route('/'),
            'create' => Pages\CreateDeviceCommand::route('/create'),
            'view' => Pages\ViewDeviceCommand::route('/{record}'),
        ];
    }
}
