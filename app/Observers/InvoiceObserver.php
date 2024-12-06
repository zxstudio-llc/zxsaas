<?php

namespace App\Observers;

use App\Enums\Accounting\InvoiceStatus;
use App\Models\Accounting\DocumentLineItem;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Transaction;
use Illuminate\Support\Facades\DB;

class InvoiceObserver
{
    /**
     * Handle the Invoice "created" event.
     */
    public function created(Invoice $invoice): void
    {
        //
    }

    /**
     * Handle the Invoice "updated" event.
     */
    public function updated(Invoice $invoice): void
    {
        if ($invoice->wasChanged('status')) {
            match ($invoice->status) {
                InvoiceStatus::Sent => $invoice->updateQuietly(['last_sent' => now()]),
                default => null,
            };
        }
    }

    public function deleting(Invoice $invoice): void
    {
        //
    }

    public function saving(Invoice $invoice): void
    {
        if ($invoice->approved_at && $invoice->is_currently_overdue) {
            $invoice->status = InvoiceStatus::Overdue;
        }
    }

    /**
     * Handle the Invoice "deleted" event.
     */
    public function deleted(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice) {
            $invoice->lineItems()->each(function (DocumentLineItem $lineItem) {
                $lineItem->delete();
            });

            $invoice->transactions()->each(function (Transaction $transaction) {
                $transaction->delete();
            });
        });
    }

    /**
     * Handle the Invoice "restored" event.
     */
    public function restored(Invoice $invoice): void
    {
        //
    }

    /**
     * Handle the Invoice "force deleted" event.
     */
    public function forceDeleted(Invoice $invoice): void
    {
        //
    }
}
