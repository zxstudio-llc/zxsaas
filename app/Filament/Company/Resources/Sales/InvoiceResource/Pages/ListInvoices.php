<?php

namespace App\Filament\Company\Resources\Sales\InvoiceResource\Pages;

use App\Enums\Accounting\InvoiceStatus;
use App\Filament\Company\Resources\Sales\InvoiceResource;
use App\Models\Accounting\Invoice;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make()
                ->label('All'),

            'overdue' => Tab::make()
                ->label('Overdue')
                ->modifyQueryUsing(function (Builder $query) {
                    $query->where('status', InvoiceStatus::Overdue);
                })
                ->badge(Invoice::where('status', InvoiceStatus::Overdue)->count()),

            'unpaid' => Tab::make()
                ->label('Unpaid')
                ->modifyQueryUsing(function (Builder $query) {
                    $query->whereIn('status', [
                        InvoiceStatus::Unsent,
                        InvoiceStatus::Sent,
                        InvoiceStatus::Partial,
                    ]);
                })
                ->badge(Invoice::whereIn('status', [
                    InvoiceStatus::Unsent,
                    InvoiceStatus::Sent,
                    InvoiceStatus::Partial,
                ])->count()),

            'draft' => Tab::make()
                ->label('Draft')
                ->modifyQueryUsing(function (Builder $query) {
                    $query->where('status', InvoiceStatus::Draft);
                })
                ->badge(Invoice::where('status', InvoiceStatus::Draft)->count()),
        ];
    }
}
