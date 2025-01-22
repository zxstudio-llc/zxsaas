<?php

namespace App\Filament\Company\Resources\Purchases\VendorResource\Pages;

use App\Filament\Company\Resources\Purchases\VendorResource;
use App\Filament\Company\Resources\Purchases\VendorResource\RelationManagers;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewVendor extends ViewRecord
{
    protected static string $resource = VendorResource::class;

    public function getRelationManagers(): array
    {
        return [
            RelationManagers\BillsRelationManager::class,
        ];
    }

    public function getTitle(): string
    {
        return $this->record->name;
    }

    protected function getHeaderWidgets(): array
    {
        return [
            VendorResource\Widgets\BillOverview::class,
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('General')
                    ->columns()
                    ->schema([
                        TextEntry::make('contact.full_name')
                            ->label('Contact'),
                        TextEntry::make('contact.email')
                            ->label('Email'),
                        TextEntry::make('contact.first_available_phone')
                            ->label('Primary Phone'),
                        TextEntry::make('website')
                            ->label('Website')
                            ->url(static fn ($state) => $state, true),
                    ]),
                Section::make('Additional Details')
                    ->columns()
                    ->schema([
                        TextEntry::make('address.address_string')
                            ->label('Billing Address')
                            ->listWithLineBreaks(),
                        TextEntry::make('notes'),
                    ]),
            ]);
    }
}
