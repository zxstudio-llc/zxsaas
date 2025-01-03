<?php

namespace App\Observers;

use App\Models\Accounting\RecurringInvoice;

class RecurringInvoiceObserver
{
    /**
     * Handle the RecurringInvoice "updated" event.
     */
    public function updated(RecurringInvoice $recurringInvoice): void
    {
        $recurringInvoice->updateQuietly([
            'next_date' => $recurringInvoice->calculateNextDate(),
        ]);
    }

    /**
     * Handle the RecurringInvoice "deleted" event.
     */
    public function deleted(RecurringInvoice $recurringInvoice): void
    {
        //
    }
}
