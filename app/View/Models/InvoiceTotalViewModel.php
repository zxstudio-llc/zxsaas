<?php

namespace App\View\Models;

use App\Models\Accounting\Adjustment;
use App\Models\Accounting\Document;
use App\Utilities\Currency\CurrencyConverter;

class InvoiceTotalViewModel
{
    public function __construct(
        public ?Document $invoice,
        public ?array $data = null
    ) {}

    public function buildViewData(): array
    {
        $lineItems = collect($this->data['lineItems'] ?? []);

        $subtotal = $lineItems->sum(fn ($item) => ($item['quantity'] ?? 0) * ($item['unit_price'] ?? 0));

        $taxTotal = $lineItems->reduce(function ($carry, $item) {
            $quantity = $item['quantity'] ?? 0;
            $unitPrice = $item['unit_price'] ?? 0;
            $salesTaxes = $item['salesTaxes'] ?? [];
            $lineTotal = $quantity * $unitPrice;

            $taxAmount = Adjustment::whereIn('id', $salesTaxes)
                ->pluck('rate')
                ->sum(fn ($rate) => $lineTotal * ($rate / 100));

            return $carry + $taxAmount;
        }, 0);

        $grandTotal = $subtotal + $taxTotal;

        $subTotalFormatted = CurrencyConverter::formatToMoney($subtotal);
        $taxTotalFormatted = CurrencyConverter::formatToMoney($taxTotal);
        $grandTotalFormatted = CurrencyConverter::formatToMoney($grandTotal);

        return [
            'subtotal' => $subTotalFormatted,
            'taxTotal' => $taxTotalFormatted,
            'grandTotal' => $grandTotalFormatted,
        ];
    }
}
