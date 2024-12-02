<?php

namespace App\Filament\Company\Resources\Sales\InvoiceResource\Pages;

use App\Enums\Accounting\InvoiceStatus;
use App\Filament\Company\Resources\Sales\ClientResource;
use App\Filament\Company\Resources\Sales\InvoiceResource;
use App\Models\Accounting\Invoice;
use Carbon\CarbonInterface;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\FontWeight;
use Illuminate\Support\Carbon;

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    protected $listeners = [
        'refresh' => '$refresh',
    ];

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Invoice Details')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('invoice_number')
                            ->label('Invoice #'),
                        TextEntry::make('status')
                            ->badge(),
                        TextEntry::make('client.name')
                            ->label('Client')
                            ->color('primary')
                            ->weight(FontWeight::SemiBold)
                            ->url(fn (Invoice $record) => ClientResource::getUrl('edit', ['record' => $record->client_id])),
                        TextEntry::make('total')
                            ->label('Total')
                            ->money(),
                        TextEntry::make('amount_due')
                            ->label('Amount Due')
                            ->money(),
                        TextEntry::make('due_date')
                            ->label('Due Date')
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
                            }),
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
}
