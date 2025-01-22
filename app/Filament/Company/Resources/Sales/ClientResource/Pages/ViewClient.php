<?php

namespace App\Filament\Company\Resources\Sales\ClientResource\Pages;

use App\Filament\Company\Resources\Sales\ClientResource;
use App\Filament\Company\Resources\Sales\ClientResource\RelationManagers;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewClient extends ViewRecord
{
    protected static string $resource = ClientResource::class;

    public function getRelationManagers(): array
    {
        return [
            RelationManagers\InvoicesRelationManager::class,
            RelationManagers\RecurringInvoicesRelationManager::class,
            RelationManagers\EstimatesRelationManager::class,
        ];
    }

    public function getTitle(): string | Htmlable
    {
        return $this->record->name;
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ClientResource\Widgets\InvoiceOverview::class,
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('General')
                    ->columns()
                    ->schema([
                        TextEntry::make('primaryContact.full_name')
                            ->label('Primary Contact'),
                        TextEntry::make('primaryContact.email')
                            ->label('Primary Email'),
                        TextEntry::make('primaryContact.first_available_phone')
                            ->label('Primary Phone'),
                        TextEntry::make('website')
                            ->label('Website')
                            ->url(static fn ($state) => $state, true),
                    ]),
                Section::make('Additional Details')
                    ->columns()
                    ->schema([
                        TextEntry::make('billingAddress.address_string')
                            ->label('Billing Address')
                            ->listWithLineBreaks(),
                        TextEntry::make('shippingAddress.address_string')
                            ->label('Shipping Address')
                            ->listWithLineBreaks(),
                        TextEntry::make('notes')
                            ->label('Delivery Instructions'),
                    ]),
            ]);
    }
}
