<?php

namespace App\Filament\Company\Resources\Sales\RecurringInvoiceResource\Pages;

use App\Enums\Accounting\DocumentType;
use App\Filament\Company\Resources\Sales\ClientResource;
use App\Filament\Company\Resources\Sales\InvoiceResource;
use App\Filament\Company\Resources\Sales\RecurringInvoiceResource;
use App\Filament\Infolists\Components\DocumentPreview;
use App\Models\Accounting\RecurringInvoice;
use CodeWithDennis\SimpleAlert\Components\Infolists\SimpleAlert;
use Filament\Actions;
use Filament\Infolists\Components\Actions\Action;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Enums\IconSize;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Str;

class ViewRecurringInvoice extends ViewRecord
{
    protected static string $resource = RecurringInvoiceResource::class;

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
                RecurringInvoice::getUpdateScheduleAction(),
                RecurringInvoice::getApproveDraftAction(),
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
                SimpleAlert::make('scheduleIsNotSet')
                    ->info()
                    ->title('Schedule Not Set')
                    ->description('The schedule for this recurring invoice has not been set. You must set a schedule before you can approve this draft and start creating invoices.')
                    ->visible(fn (RecurringInvoice $record) => ! $record->hasSchedule())
                    ->columnSpanFull()
                    ->actions([
                        RecurringInvoice::getUpdateScheduleAction(Action::class)
                            ->outlined(),
                    ]),
                SimpleAlert::make('readyToApprove')
                    ->info()
                    ->title('Ready to Approve')
                    ->description('This recurring invoice is ready for approval. Review the details, and approve it when you’re ready to start generating invoices.')
                    ->visible(fn (RecurringInvoice $record) => $record->isDraft() && $record->hasSchedule())
                    ->columnSpanFull()
                    ->actions([
                        RecurringInvoice::getApproveDraftAction(Action::class)
                            ->outlined(),
                    ]),
                Section::make('Invoice Details')
                    ->columns(4)
                    ->schema([
                        Grid::make(1)
                            ->schema([
                                TextEntry::make('status')
                                    ->badge(),
                                TextEntry::make('client.name')
                                    ->label('Client')
                                    ->color('primary')
                                    ->weight(FontWeight::SemiBold)
                                    ->url(static fn (RecurringInvoice $record) => ClientResource::getUrl('edit', ['record' => $record->client_id])),
                                TextEntry::make('last_date')
                                    ->label('Last Invoice')
                                    ->date()
                                    ->placeholder('Not Created'),
                                TextEntry::make('next_date')
                                    ->label('Next Invoice')
                                    ->placeholder('Not Scheduled')
                                    ->date(),
                                TextEntry::make('schedule')
                                    ->label('Schedule')
                                    ->getStateUsing(function (RecurringInvoice $record) {
                                        return $record->getScheduleDescription();
                                    })
                                    ->helperText(function (RecurringInvoice $record) {
                                        return $record->getTimelineDescription();
                                    }),
                                TextEntry::make('occurrences_count')
                                    ->label('Created to Date')
                                    ->visible(static fn (RecurringInvoice $record) => $record->occurrences_count > 0)
                                    ->color('primary')
                                    ->weight(FontWeight::SemiBold)
                                    ->suffix(fn (RecurringInvoice $record) => Str::of(' invoice')->plural($record->occurrences_count))
                                    ->url(static fn (RecurringInvoice $record) => InvoiceResource::getUrl()),
                                TextEntry::make('end_date')
                                    ->label('Ends On')
                                    ->date()
                                    ->visible(fn (RecurringInvoice $record) => $record->end_type?->isOn()),
                                TextEntry::make('approved_at')
                                    ->label('Approved At')
                                    ->placeholder('Not Approved')
                                    ->date(),
                                TextEntry::make('ended_at')
                                    ->label('Ended At')
                                    ->date()
                                    ->visible(fn (RecurringInvoice $record) => $record->ended_at),
                                TextEntry::make('total')
                                    ->label('Invoice Amount')
                                    ->currency(static fn (RecurringInvoice $record) => $record->currency_code),
                            ])->columnSpan(1),
                        DocumentPreview::make()
                            ->type(DocumentType::RecurringInvoice),
                    ]),
            ]);
    }
}
