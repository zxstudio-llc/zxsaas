<?php

namespace App\Filament\Company\Resources\Sales\ClientResource\Pages;

use App\Filament\Company\Resources\Sales\ClientResource;
use App\Filament\Company\Resources\Sales\ClientResource\RelationManagers;
use App\Filament\Company\Resources\Sales\EstimateResource\Pages\CreateEstimate;
use App\Filament\Company\Resources\Sales\InvoiceResource\Pages\CreateInvoice;
use App\Filament\Company\Resources\Sales\RecurringInvoiceResource\Pages\CreateRecurringInvoice;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Enums\IconSize;
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

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->label('Edit Client')
                ->outlined(),
            ActionGroup::make([
                ActionGroup::make([
                    Action::make('newInvoice')
                        ->label('New Invoice')
                        ->icon('heroicon-m-document-plus')
                        ->url(CreateInvoice::getUrl(['client' => $this->record->getKey()])),
                    Action::make('newEstimate')
                        ->label('New Estimate')
                        ->icon('heroicon-m-document-duplicate')
                        ->url(CreateEstimate::getUrl(['client' => $this->record->getKey()])),
                    Action::make('newRecurringInvoice')
                        ->label('New Recurring Invoice')
                        ->icon('heroicon-m-arrow-path')
                        ->url(CreateRecurringInvoice::getUrl(['client' => $this->record->getKey()])),
                ])->dropdown(false),
                DeleteAction::make(),
            ])
                ->label('Actions')
                ->button()
                ->outlined()
                ->dropdownPlacement('bottom-end')
                ->icon('heroicon-c-chevron-down')
                ->iconSize(IconSize::Small)
                ->iconPosition(IconPosition::After),
        ];
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
