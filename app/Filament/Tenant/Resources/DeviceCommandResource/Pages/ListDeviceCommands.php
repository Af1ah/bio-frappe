<?php

namespace App\Filament\Tenant\Resources\DeviceCommandResource\Pages;

use App\Filament\Tenant\Resources\DeviceCommandResource;
use App\Models\Device;
use App\Models\DeviceCommand;
use App\Services\DeviceCommandCapabilities;
use App\Services\DeviceCommandDispatcher;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListDeviceCommands extends ListRecords
{
    protected static string $resource = DeviceCommandResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('runCommand')
                ->label('Run Command')
                ->icon('heroicon-o-command-line')
                ->color('primary')
                ->form([
                    Select::make('device_id')
                        ->label('Select Device')
                        ->options(function () {
                            return Device::all()->mapWithKeys(function ($d) {
                                return [$d->id => $d->name ?: $d->serial_number];
                            });
                        })
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (callable $set) => $set('command', null))
                        ->default(fn () => Device::count() === 1 ? Device::first()->id : null),
                    Select::make('command')
                        ->label('Select Command')
                        ->live()
                        ->options(function (callable $get): array {
                            $device = $get('device_id') ? Device::find($get('device_id')) : null;

                            return $device ? app(DeviceCommandCapabilities::class)->options($device) : [];
                        })
                        ->required(),
                    TextInput::make('confirm')
                        ->label("Type 'CONFIRM' to execute this command")
                        ->required()
                        ->rule(function () {
                            return function (string $attribute, $value, \Closure $fail) {
                                if ($value !== 'CONFIRM') {
                                    $fail("You must type 'CONFIRM' exactly (all caps) to execute this command.");
                                }
                            };
                        })
                        ->hidden(fn ($get) => ! in_array($get('command'), ['clear_logs', 'reboot'])),
                ])
                ->action(function (array $data) {
                    $device = Device::find($data['device_id']);
                    if (! $device) {
                        return;
                    }

                    $command = DeviceCommand::create([
                        'device_id' => $device->id,
                        'command_type' => $data['command'],
                        'command_content' => "Device command: {$data['command']}",
                        'status' => 'pending',
                    ]);

                    app(DeviceCommandDispatcher::class)->dispatch($device, $command);

                    Notification::make()
                        ->title('Command Queued')
                        ->body("The '{$data['command']}' command has been queued for sync.")
                        ->success()
                        ->send();
                }),
            Actions\CreateAction::make(),
        ];
    }
}
