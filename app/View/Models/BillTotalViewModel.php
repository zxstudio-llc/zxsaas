<?php

namespace App\View\Models;

use App\Enums\Accounting\AdjustmentComputation;
use App\Enums\Accounting\DocumentDiscountMethod;
use App\Models\Accounting\Adjustment;
use App\Models\Accounting\Bill;
use App\Utilities\Currency\CurrencyConverter;

class BillTotalViewModel
{
    public function __construct(
        public ?Bill $bill,
        public ?array $data = null
    ) {}

    public function buildViewData(): array
    {
        $lineItems = collect($this->data['lineItems'] ?? []);

        $subtotal = $lineItems->sum(static function ($item) {
            $quantity = max((float) ($item['quantity'] ?? 0), 0);
            $unitPrice = max((float) ($item['unit_price'] ?? 0), 0);

            return $quantity * $unitPrice;
        });

        $taxTotal = $lineItems->reduce(function ($carry, $item) {
            $quantity = max((float) ($item['quantity'] ?? 0), 0);
            $unitPrice = max((float) ($item['unit_price'] ?? 0), 0);
            $purchaseTaxes = $item['purchaseTaxes'] ?? [];
            $lineTotal = $quantity * $unitPrice;

            $taxAmount = Adjustment::whereIn('id', $purchaseTaxes)
                ->pluck('rate')
                ->sum(fn ($rate) => $lineTotal * ($rate / 100));

            return $carry + $taxAmount;
        }, 0);

        // Calculate discount based on method
        $discountMethod = DocumentDiscountMethod::parse($this->data['discount_method']) ?? DocumentDiscountMethod::PerLineItem;

        if ($discountMethod->isPerLineItem()) {
            $discountTotal = $lineItems->reduce(function ($carry, $item) {
                $quantity = max((float) ($item['quantity'] ?? 0), 0);
                $unitPrice = max((float) ($item['unit_price'] ?? 0), 0);
                $purchaseDiscounts = $item['purchaseDiscounts'] ?? [];
                $lineTotal = $quantity * $unitPrice;

                $discountAmount = Adjustment::whereIn('id', $purchaseDiscounts)
                    ->pluck('rate')
                    ->sum(fn ($rate) => $lineTotal * ($rate / 100));

                return $carry + $discountAmount;
            }, 0);
        } else {
            $discountComputation = AdjustmentComputation::parse($this->data['discount_computation']) ?? AdjustmentComputation::Percentage;
            $discountRate = (float) ($this->data['discount_rate'] ?? 0);

            if ($discountComputation->isPercentage()) {
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
