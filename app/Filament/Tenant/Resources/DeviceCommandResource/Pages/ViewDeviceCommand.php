<?php

namespace App\Filament\Tenant\Resources\DeviceCommandResource\Pages;

use App\Filament\Tenant\Resources\DeviceCommandResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewDeviceCommand extends ViewRecord
{
    protected static string $resource = DeviceCommandResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
