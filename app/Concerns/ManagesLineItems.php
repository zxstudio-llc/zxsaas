<?php

namespace App\Concerns;

use App\Enums\Accounting\AdjustmentComputation;
use App\Models\Accounting\DocumentLineItem;
use App\Models\Accounting\Invoice;
use App\Utilities\Currency\CurrencyConverter;
use Illuminate\Support\Collection;

trait ManagesLineItems
{
    protected function handleLineItems(Invoice $record, Collection $lineItems): void
    {
        foreach ($lineItems as $itemData) {
            $lineItem = isset($itemData['id'])
                ? $record->lineItems->find($itemData['id'])
                : $record->lineItems()->make();

            $lineItem->fill([
                'offering_id' => $itemData['offering_id'],
                'description' => $itemData['description'],
                'quantity' => $itemData['quantity'],
                'unit_price' => $itemData['unit_price'],
            ]);

            if (! $lineItem->exists) {
                $lineItem->documentable()->associate($record);
            }

            $lineItem->save();

            $this->handleLineItemAdjustments($lineItem, $itemData, $record->discount_method);
            $this->updateLineItemTotals($lineItem, $record->discount_method);
        }
    }

    protected function deleteRemovedLineItems(Invoice $record, Collection $lineItems): void
    {
        $existingLineItemIds = $record->lineItems->pluck('id');
        $updatedLineItemIds = $lineItems->pluck('id')->filter();
        $lineItemsToDelete = $existingLineItemIds->diff($updatedLineItemIds);

        if ($lineItemsToDelete->isNotEmpty()) {
            $record
                ->lineItems()
                ->whereIn('id', $lineItemsToDelete)
                ->each(fn (DocumentLineItem $lineItem) => $lineItem->delete());
        }
    }

    protected function handleLineItemAdjustments(DocumentLineItem $lineItem, array $itemData, string $discountMethod): void
    {
        $adjustmentIds = collect($itemData['salesTaxes'] ?? [])
            ->merge($discountMethod === 'line_items' ? ($itemData['salesDiscounts'] ?? []) : [])
            ->filter()
            ->unique();

        $lineItem->adjustments()->sync($adjustmentIds);
        $lineItem->refresh();
    }

    protected function updateLineItemTotals(DocumentLineItem $lineItem, string $discountMethod): void
    {
        $lineItem->updateQuietly([
            'tax_total' => $lineItem->calculateTaxTotal()->getAmount(),
            'discount_total' => $discountMethod === 'line_items'
                ? $lineItem->calculateDiscountTotal()->getAmount()
                : 0,
        ]);
    }

    protected function updateInvoiceTotals(Invoice $record, array $data): array
    {
        $subtotalCents = $record->lineItems()->sum('subtotal');
        $taxTotalCents = $record->lineItems()->sum('tax_total');
        $discountTotalCents = $this->calculateDiscountTotal(
            $data['discount_method'],
            AdjustmentComputation::parse($data['discount_computation']),
            $data['discount_rate'] ?? null,
            $subtotalCents,
            $record
        );

        $grandTotalCents = $subtotalCents + $taxTotalCents - $discountTotalCents;

        return [
            'subtotal' => CurrencyConverter::convertCentsToFormatSimple($subtotalCents),
            'tax_total' => CurrencyConverter::convertCentsToFormatSimple($taxTotalCents),
            'discount_total' => CurrencyConverter::convertCentsToFormatSimple($discountTotalCents),
            'total' => CurrencyConverter::convertCentsToFormatSimple($grandTotalCents),
        ];
    }

    protected function calculateDiscountTotal(
        string $discountMethod,
        ?AdjustmentComputation $discountComputation,
        ?string $discountRate,
        int $subtotalCents,
        Invoice $record
    ): int {
        if ($discountMethod === 'line_items') {
            return $record->lineItems()->sum('discount_total');
        }

        if ($discountComputation === AdjustmentComputation::Percentage) {
            return (int) ($subtotalCents * ((float) $discountRate / 100));
        }

        return CurrencyConverter::convertToCents($discountRate);
    }
}
