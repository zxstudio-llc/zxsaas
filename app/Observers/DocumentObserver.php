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

    public function updated(Document $document): void
    {
        $this->recalculateTotals($document);
    }

    public function saved(Document $document): void
    {
        $this->recalculateTotals($document);
    }

    protected function recalculateTotals(Document $document): void
    {
        // Sum up totals in cents
        $subtotalCents = $document->lineItems()->sum('total');
        $taxTotalCents = $document->lineItems()->sum('tax_total');
        $discountTotalCents = $document->lineItems()->sum('discount_total');
        $grandTotalCents = $subtotalCents + $taxTotalCents - $discountTotalCents;

        $subtotal = $subtotalCents / 100; // Convert to dollars
        $taxTotal = $taxTotalCents / 100;
        $discountTotal = $discountTotalCents / 100;
        $grandTotal = $grandTotalCents / 100;

        ray([
            'subtotal' => $subtotal,
            'tax_total' => $taxTotal,
            'discount_total' => $discountTotal,
            'grand_total' => $grandTotal,
        ]);

        $document->updateQuietly([
            'subtotal' => $subtotal,
            'tax_total' => $taxTotal,
            'discount_total' => $discountTotal,
            'total' => $grandTotal,
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
