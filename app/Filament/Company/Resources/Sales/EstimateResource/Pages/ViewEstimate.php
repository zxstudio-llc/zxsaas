<?php

namespace App\Filament\Company\Resources\Sales\EstimateResource\Pages;

use App\Filament\Company\Resources\Sales\ClientResource;
use App\Filament\Company\Resources\Sales\EstimateResource;
use App\Models\Accounting\Estimate;
use Filament\Actions;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Enums\IconSize;
use Filament\Support\Enums\MaxWidth;

class ViewEstimate extends ViewRecord
{
    protected static string $view = 'filament.company.resources.sales.invoices.pages.view-invoice';

    protected static string $resource = EstimateResource::class;

    protected $listeners = [
        'refresh' => '$refresh',
    ];

    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::SixExtraLarge;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
                Estimate::getApproveDraftAction(),
                Estimate::getMarkAsSentAction(),
                Estimate::getReplicateAction(),
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
                Section::make('Estimate Details')
                    ->columns(4)
                    ->schema([
                        Grid::make(1)
                            ->schema([
                                TextEntry::make('invoice_number')
                                    ->label('Estimate #'),
                                TextEntry::make('status')
                                    ->badge(),
                                TextEntry::make('client.name')
                                    ->label('Client')
                                    ->color('primary')
                                    ->weight(FontWeight::SemiBold)
                                    ->url(static fn (Estimate $record) => ClientResource::getUrl('edit', ['record' => $record->client_id])),
                                TextEntry::make('amount_due')
                                    ->label('Amount Due')
                                    ->currency(static fn (Estimate $record) => $record->currency_code),
                                TextEntry::make('due_date')
                                    ->label('Due')
                                    ->asRelativeDay(),
                                TextEntry::make('approved_at')
                                    ->label('Approved At')
                                    ->placeholder('Not Approved')
                                    ->date(),
                                TextEntry::make('last_sent_at')
                                    ->label('Last Sent')
                                    ->placeholder('Never')
                                    ->date(),
                                TextEntry::make('paid_at')
                                    ->label('Paid At')
                                    ->placeholder('Not Paid')
                                    ->date(),
                            ])->columnSpan(1),
                        Grid::make()
                            ->schema([
                                ViewEntry::make('invoice-view')
                                    ->label('View Estimate')
                                    ->columnSpan(3)
                                    ->view('filament.company.resources.sales.invoices.components.invoice-view')
                                    ->viewData([
                                        'invoice' => $this->record,
                                    ]),
                            ])
                            ->columnSpan(3),
                    ]),
            ]);
    }
}
