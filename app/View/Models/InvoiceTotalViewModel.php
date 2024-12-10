<?php

namespace App\View\Models;

use App\Enums\Accounting\AdjustmentComputation;
use App\Models\Accounting\Adjustment;
use App\Models\Accounting\Invoice;
use App\Utilities\Currency\CurrencyConverter;

class InvoiceTotalViewModel
{
    public function __construct(
        public ?Invoice $invoice,
        public ?array $data = null
    ) {}

    public function buildViewData(): array
    {
        $lineItems = collect($this->data['lineItems'] ?? []);

        $subtotal = $lineItems->sum(function ($item) {
            $quantity = max((float) ($item['quantity'] ?? 0), 0);
            $unitPrice = max((float) ($item['unit_price'] ?? 0), 0);

            return $quantity * $unitPrice;
        });

        $taxTotal = $lineItems->reduce(function ($carry, $item) {
            $quantity = max((float) ($item['quantity'] ?? 0), 0);
            $unitPrice = max((float) ($item['unit_price'] ?? 0), 0);
            $salesTaxes = $item['salesTaxes'] ?? [];
            $lineTotal = $quantity * $unitPrice;

            $taxAmount = Adjustment::whereIn('id', $salesTaxes)
                ->pluck('rate')
                ->sum(fn ($rate) => $lineTotal * ($rate / 100));

            return $carry + $taxAmount;
        }, 0);

        // Calculate discount based on method
        $discountMethod = $this->data['discount_method'] ?? 'line_items';

        if ($discountMethod === 'line_items') {
            $discountTotal = $lineItems->reduce(function ($carry, $item) {
                $quantity = max((float) ($item['quantity'] ?? 0), 0);
                $unitPrice = max((float) ($item['unit_price'] ?? 0), 0);
                $salesDiscounts = $item['salesDiscounts'] ?? [];
                $lineTotal = $quantity * $unitPrice;

                $discountAmount = Adjustment::whereIn('id', $salesDiscounts)
                    ->pluck('rate')
                    ->sum(fn ($rate) => $lineTotal * ($rate / 100));

                return $carry + $discountAmount;
            }, 0);
        } else {
            $discountComputation = $this->data['discount_computation'] ?? AdjustmentComputation::Percentage;
            $discountRate = (float) ($this->data['discount_rate'] ?? 0);

            if ($discountComputation === AdjustmentComputation::Percentage) {
                $discountTotal = $subtotal * ($discountRate / 100);
            } else {
                $discountTotal = $discountRate;
            }
        }

        $grandTotal = $subtotal + ($taxTotal - $discountTotal);

        return [
            'subtotal' => CurrencyConverter::formatToMoney($subtotal),
            'taxTotal' => CurrencyConverter::formatToMoney($taxTotal),
            'discountTotal' => CurrencyConverter::formatToMoney($discountTotal),
            'grandTotal' => CurrencyConverter::formatToMoney($grandTotal),
        ];
    }
}
