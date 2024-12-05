<?php

namespace App\Filament\Company\Resources\Sales\InvoiceResource\Widgets;

use App\Enums\Accounting\InvoiceStatus;
use App\Filament\Company\Resources\Sales\InvoiceResource\Pages\ListInvoices;
use App\Utilities\Currency\CurrencyConverter;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class InvoiceOverview extends BaseWidget
{
    use InteractsWithPageTable;

    protected static ?string $pollingInterval = null;

    protected function getTablePage(): string
    {
        return ListInvoices::class;
    }

    protected function getStats(): array
    {
        $outstandingInvoices = $this->getPageTableQuery()
            ->whereNotIn('status', [
                InvoiceStatus::Paid,
                InvoiceStatus::Void,
                InvoiceStatus::Draft,
                InvoiceStatus::Overpaid,
            ]);

        $amountOutstanding = $outstandingInvoices
            ->clone()
            ->sum('amount_due');

        $amountOverdue = $outstandingInvoices
            ->clone()
            ->where('status', InvoiceStatus::Overdue)
            ->sum('amount_due');

        $amountDueWithin30Days = $outstandingInvoices
            ->clone()
            ->where('due_date', '>=', today())
            ->where('due_date', '<=', today()->addDays(30))
            ->sum('amount_due');

        $validInvoices = $this->getPageTableQuery()
            ->whereNotIn('status', [
                InvoiceStatus::Void,
                InvoiceStatus::Draft,
            ]);

        $totalValidInvoiceAmount = $validInvoices->clone()->sum('amount_due');

        $totalValidInvoiceCount = $validInvoices->clone()->count();

        $averageInvoiceTotal = $totalValidInvoiceCount > 0 ? $totalValidInvoiceAmount / $totalValidInvoiceCount : 0;

        $averagePaymentTime = $this->getPageTableQuery()
            ->withWhereHas('statusHistories', function ($query) {
                $query->where('new_status', InvoiceStatus::Paid);
            })
            ->selectRaw('AVG(TIMESTAMPDIFF(DAY, date, (
                SELECT changed_at
                FROM invoice_status_histories
                WHERE invoice_status_histories.invoice_id = invoices.id
                AND status = ?
                LIMIT 1
            ))) as avg_days', [InvoiceStatus::Paid])
            ->value('avg_days');

        return [
            Stat::make('Total Outstanding', CurrencyConverter::formatCentsToMoney($amountOutstanding))
                ->description('Includes ' . CurrencyConverter::formatCentsToMoney($amountOverdue) . ' overdue'),
            Stat::make('Due Within 30 Days', CurrencyConverter::formatCentsToMoney($amountDueWithin30Days)),
            Stat::make('Average Payment Time', Number::format($averagePaymentTime ?? 0, maxPrecision: 1) . ' days')
                ->description('From invoice date to payment received'),
            Stat::make('Average Invoice Total', CurrencyConverter::formatCentsToMoney($averageInvoiceTotal))
                ->description('Excludes void and draft invoices'),
        ];
    }
}
