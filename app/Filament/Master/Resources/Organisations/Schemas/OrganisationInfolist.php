<?php

namespace App\Filament\Master\Resources\Organisations\Schemas;

use Filament\Infolists\Components\ColorEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrganisationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('General Information')
                ->schema([
                    TextEntry::make('name')->weight('bold')->size('lg'),
                    TextEntry::make('status')->badge()
                        ->color(fn (string $state): string => $state === 'active' ? 'success' : 'danger'),
                    TextEntry::make('shortname')->label('Slug')->placeholder('-'),
                    TextEntry::make('email')->label('Email address')->placeholder('-'),
                    TextEntry::make('phone')->placeholder('-'),
                    ImageEntry::make('logo'),
                    ColorEntry::make('brand_color')->placeholder('-'),
                    TextEntry::make('created_at')->dateTime(),
                    TextEntry::make('updated_at')->dateTime(),
                ])
                ->columns(2),
            Section::make('Tenant data')
                ->schema([
                    ViewEntry::make('devices')
                        ->hiddenLabel()
                        ->view('infolists.components.tenant-devices'),
                    ViewEntry::make('users')
                        ->hiddenLabel()
                        ->view('infolists.components.tenant-users'),
                ])
                ->columnSpanFull(),
        ]);
    }
}
