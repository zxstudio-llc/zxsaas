<?php

namespace App\Filament\Company\Resources\Sales\InvoiceResource\Pages;

use App\Enums\Accounting\InvoiceStatus;
use App\Filament\Company\Resources\Sales\ClientResource;
use App\Filament\Company\Resources\Sales\InvoiceResource;
use Carbon\CarbonInterface;
use Filament\Infolists\Components\Actions\Action;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Carbon;

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->columns(1)
            ->schema([
                Grid::make(5)
                    ->schema([
                        TextEntry::make('invoice_number')
                            ->label('Invoice #')
                            ->size(TextEntry\TextEntrySize::Large),
                        TextEntry::make('status')
                            ->badge()
                            ->size(TextEntry\TextEntrySize::Large),
                        TextEntry::make('client.name')
                            ->color('primary')
                            ->weight(FontWeight::SemiBold)
                            ->size(TextEntry\TextEntrySize::Large)
                            ->url(fn ($record) => ClientResource::getUrl('edit', ['record' => $record->client_id]), true),
                        TextEntry::make('amount_due')
                            ->label('Amount Due')
                            ->money()
                            ->size(TextEntry\TextEntrySize::Large),
                        TextEntry::make('due_date')
                            ->label('Due')
                            ->formatStateUsing(function (TextEntry $entry, mixed $state) {
                                if (blank($state)) {
                                    return null;
                                }

                                $date = Carbon::parse($state)
                                    ->setTimezone($timezone ?? $entry->getTimezone());

                                if ($date->isToday()) {
                                    return 'Today';
                                }

                                return $date->diffForHumans([
                                    'options' => CarbonInterface::ONE_DAY_WORDS,
                                ]);
                            })
                            ->size(TextEntry\TextEntrySize::Large),
                    ]),
                ViewEntry::make('create')
                    ->view('filament.infolists.invoice-create-step')
                    ->registerActions([
                        Action::make('approveDraft')
                            ->label('Approve Draft')
                            ->action('approveDraft')
                            ->visible(fn ($record) => $record->status === InvoiceStatus::Draft),
                        Action::make('edit')
                            ->label('Edit')
                            ->outlined()
                            ->url(fn ($record) => InvoiceResource::getUrl('edit', ['record' => $record->id]), true),
                        Action::make('markAsSent')
                            ->label('Mark as Sent')
                            ->outlined()
                            ->action('markAsSent'),
                        Action::make('sendInvoice')
                            ->label('Send Invoice')
                            ->action('sendInvoice'),
                        Action::make('recordPayment')
                            ->label('Record Payment')
                            ->action('recordPayment'),
                    ]),
            ]);
    }

    public function approveDraft(): void
    {
        $this->record->update([
            'status' => InvoiceStatus::Unsent,
        ]);
    }

    public function markAsSent(): void
    {
        $this->record->update([
            'status' => InvoiceStatus::Sent,
        ]);
    }

    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::FiveExtraLarge;
    }
}
