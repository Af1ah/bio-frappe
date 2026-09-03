<?php

namespace App\Livewire\Master;

use App\Models\Device;
use App\Models\Organisation;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Component;

class TenantDevicesTable extends Component implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    public Organisation $organisation;

    public function table(Table $table): Table
    {
        tenancy()->initialize($this->organisation);

        return $table
            ->query(Device::query())
            ->columns([
                TextColumn::make('serial_number'),
                TextColumn::make('name'),
                TextColumn::make('vendor')->badge(),
                TextColumn::make('status')
                    ->badge()
                    ->getStateUsing(fn (Device $record): string => $record->isOnline() ? 'online' : 'offline')
                    ->color(fn (string $state): string => $state === 'online' ? 'success' : 'danger'),
                TextColumn::make('last_activity_at')->label('Last activity')->dateTime()->placeholder('-'),
                TextColumn::make('last_sync_at')->label('Last sync')->dateTime()->placeholder('-'),
            ]);
    }

    public function render()
    {
        return view('livewire.master.tenant-devices-table');
    }
}
