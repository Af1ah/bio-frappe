<?php

namespace App\Filament\Master\Resources\Organisations\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrganisationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('General Information')
                ->schema([
                    TextInput::make('name')->required(),
                    TextInput::make('shortname')->required()->unique(ignoreRecord: true)->alphaDash(),
                    TextInput::make('email')->label('Email address')->email()->default(null),
                    TextInput::make('phone')->tel()->default(null),
                    FileUpload::make('logo')
                        ->image()
                        ->acceptedFileTypes(['image/png', 'image/svg+xml', 'image/jpeg', 'image/webp'])
                        ->maxSize(100)
                        ->directory('organisations/logos')
                        ->default(null),
                    ColorPicker::make('brand_color')->default(null),
                    Select::make('status')
                        ->options(['active' => 'Active', 'inactive' => 'Inactive'])
                        ->default('active')
                        ->required(),
                ])
                ->columns(2),
        ]);
    }
}
