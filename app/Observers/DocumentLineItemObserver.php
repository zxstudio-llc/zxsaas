<?php

namespace App\Observers;

use App\Models\Accounting\DocumentLineItem;

class DocumentLineItemObserver
{
    /**
     * Handle the DocumentLineItem "created" event.
     */
    public function created(DocumentLineItem $documentLineItem): void
    {
        //
    }

    /**
     * Handle the DocumentLineItem "updated" event.
     */
    public function updated(DocumentLineItem $documentLineItem): void
    {
        //
    }

    public function saving(DocumentLineItem $lineItem): void
    {
        // Calculate the base total (quantity * unit price)
        $lineItem->total = $lineItem->quantity * $lineItem->unit_price;

        // Calculate tax total (if applicable)
        $lineItem->tax_total = $lineItem->taxes->sum(fn ($tax) => $lineItem->total * ($tax->rate / 100));

        // Calculate discount total (if applicable)
        $lineItem->discount_total = $lineItem->discounts->sum(fn ($discount) => $lineItem->total * ($discount->rate / 100));
    }

    /**
     * Handle the DocumentLineItem "deleted" event.
     */
    public function deleted(DocumentLineItem $documentLineItem): void
    {
        //
    }

    /**
     * Handle the DocumentLineItem "restored" event.
     */
    public function restored(DocumentLineItem $documentLineItem): void
    {
        //
    }

    /**
     * Handle the DocumentLineItem "force deleted" event.
     */
    public function forceDeleted(DocumentLineItem $documentLineItem): void
    {
        //
    }
}
