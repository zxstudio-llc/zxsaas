<?php

namespace App\Filament\Company\Resources\Sales\ClientResource\Pages;

use App\Filament\Company\Resources\Sales\ClientResource;
use App\Filament\Company\Resources\Sales\ClientResource\RelationManagers;
use App\Models\Common\Client;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Number;

class ViewClient extends ViewRecord
{
    protected static string $resource = ClientResource::class;

    public function getRelationManagers(): array
    {
        return [
            RelationManagers\InvoicesRelationManager::class,
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Financial Overview')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('last_12_months_paid')
                            ->label('Last 12 Months Paid')
                            ->getStateUsing(function (Client $record) {
                                return $record->invoices()
                                    ->whereNotNull('paid_at')
                                    ->where('paid_at', '>=', now()->subMonths(12))
                                    ->get()
                                    ->sumMoneyInDefaultCurrency('total');
                            })
                            ->currency(convert: false),
                        TextEntry::make('total_unpaid')
                            ->label('Total Unpaid')
                            ->getStateUsing(function (Client $record) {
                                return $record->invoices()
                                    ->unpaid()
                                    ->get()
                                    ->sumMoneyInDefaultCurrency('amount_due');
                            })
                            ->currency(convert: false),
                        TextEntry::make('total_overdue')
                            ->label('Total Overdue')
                            ->getStateUsing(function (Client $record) {
                                return $record->invoices()
                                    ->overdue()
                                    ->get()
                                    ->sumMoneyInDefaultCurrency('amount_due');
                            })
                            ->currency(convert: false),
                        TextEntry::make('average_payment_time')
                            ->label('Average Payment Time')
                            ->getStateUsing(function (Client $record) {
                                return $record->invoices()
                                    ->whereNotNull('paid_at')
                                    ->selectRaw('AVG(TIMESTAMPDIFF(DAY, date, paid_at)) as avg_days')
                                    ->value('avg_days');
                            })
                            ->suffix(' days')
                            ->formatStateUsing(function ($state) {
                                return Number::format($state ?? 0, maxPrecision: 1);
                            }),
                    ]),
            ]);
    }
}
