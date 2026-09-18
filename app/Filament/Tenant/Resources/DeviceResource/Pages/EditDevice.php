<?php

namespace App\Filament\Tenant\Resources\DeviceResource\Pages;

use App\Enums\DeviceTransport;
use App\Filament\Tenant\Resources\DeviceResource;
use App\Models\DeviceBinding;
use App\Services\DeviceGateway\RegisterGoAdmsDevice;
use App\Services\EbioSoapService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use Illuminate\Validation\ValidationException;

class EditDevice extends EditRecord
{
    protected static string $resource = DeviceResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
        ];
    }

    /** @param array<string, mixed> $data */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $options = $data['options'] ?? [];
        $options['connection_mode'] ??= DeviceTransport::forDevice($this->record)->value;
        $options['source_cidr'] ??= $this->record->ip_address;
        $data['options'] = $options;

        return $data;
    }

    protected function afterSave(): void
    {
        $connectionMode = DeviceTransport::forDevice($this->record);

        if ($connectionMode === DeviceTransport::Adms) {
            try {
                app(RegisterGoAdmsDevice::class)->register(
                    $this->record,
                    tenancy()->tenant->id,
                    (string) data_get($this->record->options, 'timezone', config('app.timezone', 'UTC')),
                );
            } catch (\Throwable $exception) {
                $options = $this->record->options ?? [];
                $options['connection_mode'] = 'direct';
                $this->record->update(['options' => $options]);

                throw $exception;
            }

            return;
        }

        DeviceBinding::query()
            ->where('tenant_id', tenancy()->tenant->id)
            ->where('serial_number', $this->record->serial_number)
            ->update(['is_active' => false]);
    }

    /** @param array<string, mixed> $data */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $options = $data['options'] ?? [];

        if (blank($options['source_cidr'] ?? null) && filled($data['ip_address'] ?? null)) {
            $options['source_cidr'] = $data['ip_address'];
            $data['options'] = $options;
        }

        if (($options['connection_mode'] ?? 'ebio') === 'ebio') {
            try {
                $updated = app(EbioSoapService::class)->addDevice(tenant(), [
                    'serial_number' => $this->record->serial_number,
                    'name' => $data['name'],
                    'location' => data_get($options, 'location'),
                    'direction' => data_get($options, 'direction'),
                    'device_type' => data_get($options, 'type'),
                    'time_zone' => data_get($options, 'timezone'),
                    'activation_code' => data_get($options, 'activation_code', '0'),
                    'is_attendance_device' => data_get($options, 'is_attendance_device', 'true'),
                ]);
            } catch (\Throwable) {
                $updated = false;
            }

            if (! $updated) {
                throw ValidationException::withMessages([
                    'name' => 'eBioServer did not accept the device update.',
                ]);
            }
        }

        return $data;
    }
}
