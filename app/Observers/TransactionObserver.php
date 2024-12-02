<?php

namespace App\Observers;

use App\Enums\Accounting\InvoiceStatus;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Transaction;
use App\Services\TransactionService;
use App\Utilities\Currency\CurrencyConverter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class TransactionObserver
{
    public function __construct(
        protected TransactionService $transactionService,
    ) {}

    /**
     * Handle the Transaction "saving" event.
     */
    public function saving(Transaction $transaction): void
    {
        if ($transaction->type->isTransfer() && $transaction->description === null) {
            $transaction->description = 'Account Transfer';
        }
    }

    /**
     * Handle the Transaction "created" event.
     */
    public function created(Transaction $transaction): void
    {
        $this->transactionService->createJournalEntries($transaction);

        if (! $transaction->is_payment) {
            return;
        }

        $invoice = $transaction->transactionable;

        if ($invoice instanceof Invoice) {
            $this->updateInvoiceTotals($invoice);
        }
    }

    /**
     * Handle the Transaction "updated" event.
     */
    public function updated(Transaction $transaction): void
    {
        $transaction->refresh(); // DO NOT REMOVE

        $this->transactionService->updateJournalEntries($transaction);

        if (! $transaction->is_payment) {
            return;
        }

        $invoice = $transaction->transactionable;

        if ($invoice instanceof Invoice) {
            $this->updateInvoiceTotals($invoice);
        }
    }

    /**
     * Handle the Transaction "deleting" event.
     */
    public function deleting(Transaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            $this->transactionService->deleteJournalEntries($transaction);

            if (! $transaction->is_payment) {
                return;
            }

            $invoice = $transaction->transactionable;

            if ($invoice instanceof Invoice && ! $invoice->exists) {
                return;
            }

            if ($invoice instanceof Invoice) {
                $this->updateInvoiceTotals($invoice, $transaction);
            }
        });
    }

    public function deleted(Transaction $transaction): void
    {
        //
    }

    protected function updateInvoiceTotals(Invoice $invoice, ?Transaction $excludedTransaction = null): void
    {
        $depositTotal = (int) $invoice->deposits()
            ->when($excludedTransaction, fn (Builder $query) => $query->whereKeyNot($excludedTransaction->getKey()))
            ->sum('amount');

        $withdrawalTotal = (int) $invoice->withdrawals()
            ->when($excludedTransaction, fn (Builder $query) => $query->whereKeyNot($excludedTransaction->getKey()))
            ->sum('amount');

        $totalPaid = $depositTotal - $withdrawalTotal;

        $invoiceTotal = (int) $invoice->getRawOriginal('total');

        $invoice->updateQuietly([
            'amount_paid' => CurrencyConverter::convertCentsToFloat($totalPaid),
            'status' => match (true) {
                $totalPaid > $invoiceTotal => InvoiceStatus::Overpaid,
                $totalPaid === $invoiceTotal => InvoiceStatus::Paid,
                $totalPaid === 0 => InvoiceStatus::Sent,
                default => InvoiceStatus::Partial,
            },
        ]);
    }
}
