<?php

namespace App\Filament\Company\Resources\Sales\InvoiceResource\Pages;

use App\Filament\Company\Resources\Sales\ClientResource;
use App\Filament\Company\Resources\Sales\InvoiceResource;
use App\Models\Accounting\Invoice;
use Carbon\CarbonInterface;
use Filament\Actions;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Enums\IconSize;
use Illuminate\Support\Carbon;

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    protected $listeners = [
        'refresh' => '$refresh',
    ];

    protected function getHeaderActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
                Invoice::getApproveDraftAction(),
                Invoice::getMarkAsSentAction(),
                Invoice::getReplicateAction(),
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

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Invoice Details')
                    ->columns(4)
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
                        TextEntry::make('date')
                            ->label('Date')
                            ->date(),
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
                            }),
                        TextEntry::make('approved_at')
                            ->label('Approved At')
                            ->placeholder('Not Approved')
                            ->date(),
                        TextEntry::make('last_sent')
                            ->label('Last Sent')
                            ->placeholder('Never')
                            ->date(),
                        TextEntry::make('paid_at')
                            ->label('Paid At')
                            ->placeholder('Not Paid')
                            ->date(),
                    ]),
            ]);
    }
}
