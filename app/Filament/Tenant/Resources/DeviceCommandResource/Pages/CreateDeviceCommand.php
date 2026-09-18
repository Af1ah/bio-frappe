<?php

namespace App\Filament\Tenant\Resources\DeviceCommandResource\Pages;

use App\Filament\Tenant\Resources\DeviceCommandResource;
use App\Services\DeviceCommandDispatcher;
use Filament\Resources\Pages\CreateRecord;

class CreateDeviceCommand extends CreateRecord
{
    protected static string $resource = DeviceCommandResource::class;

    protected function afterCreate(): void
    {
        app(DeviceCommandDispatcher::class)->dispatch($this->record->device, $this->record);
    }
}
