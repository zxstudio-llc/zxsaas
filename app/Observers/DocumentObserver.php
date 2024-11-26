<?php

namespace App\Observers;

use App\Models\Accounting\Document;

class DocumentObserver
{
    /**
     * Handle the Document "created" event.
     */
    public function created(Document $document): void
    {
        //
    }

    /**
     * Handle the Document "deleted" event.
     */
    public function deleted(Document $document): void
    {
        //
    }

    /**
     * Handle the Document "restored" event.
     */
    public function restored(Document $document): void
    {
        //
    }

    public function saved(Document $document): void
    {
        $this->recalculateTotals($document);
    }

    protected function recalculateTotals(Document $document): void
    {
        // Sum up values from line items
        $subtotalCents = $document->lineItems()->sum('subtotal'); // Use the computed column directly
        $taxTotalCents = $document->lineItems()->sum('tax_total'); // Sum from line items
        $discountTotalCents = $document->lineItems()->sum('discount_total'); // Sum from line items
        $grandTotalCents = $subtotalCents + $taxTotalCents - $discountTotalCents; // Calculated as before

        // Convert from cents to dollars
        $subtotal = $subtotalCents / 100;
        $taxTotal = $taxTotalCents / 100;
        $discountTotal = $discountTotalCents / 100;
        $grandTotal = $grandTotalCents / 100;

        // Update document totals
        $document->updateQuietly([
            'subtotal' => $subtotal, // Use database-computed subtotal
            'tax_total' => $taxTotal,
            'discount_total' => $discountTotal,
            'total' => $grandTotal, // Use calculated grand total
        ]);
    }

    /**
     * Handle the Document "force deleted" event.
     */
    public function forceDeleted(Document $document): void
    {
        //
    }
}
