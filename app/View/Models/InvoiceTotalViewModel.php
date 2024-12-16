<?php

namespace App\View\Models;

use App\Enums\Accounting\AdjustmentComputation;
use App\Enums\Accounting\DocumentDiscountMethod;
use App\Models\Accounting\Adjustment;
use App\Models\Accounting\Invoice;
use App\Utilities\Currency\CurrencyAccessor;
use App\Utilities\Currency\CurrencyConverter;
use App\Utilities\RateCalculator;

class InvoiceTotalViewModel
{
    public function __construct(
        public ?Invoice $invoice,
        public ?array $data = null
    ) {}

    public function buildViewData(): array
    {
        $currencyCode = $this->data['currency_code'] ?? CurrencyAccessor::getDefaultCurrency();
        $defaultCurrencyCode = CurrencyAccessor::getDefaultCurrency();

        $lineItems = collect($this->data['lineItems'] ?? []);

        $subtotalInCents = $lineItems->sum(static function ($item) use ($currencyCode) {
            $quantity = max((float) ($item['quantity'] ?? 0), 0);
            $unitPrice = max((float) ($item['unit_price'] ?? 0), 0);

            $subtotal = $quantity * $unitPrice;

            return CurrencyConverter::convertToCents($subtotal, $currencyCode);
        });

        $taxTotalInCents = $lineItems->reduce(function ($carry, $item) use ($currencyCode) {
            $quantity = max((float) ($item['quantity'] ?? 0), 0);
            $unitPrice = max((float) ($item['unit_price'] ?? 0), 0);
            $salesTaxes = $item['salesTaxes'] ?? [];
            $lineTotal = $quantity * $unitPrice;

            $lineTotalInCents = CurrencyConverter::convertToCents($lineTotal, $currencyCode);

            $taxAmountInCents = Adjustment::whereIn('id', $salesTaxes)
                ->get()
                ->sum(function (Adjustment $adjustment) use ($lineTotalInCents) {
                    if ($adjustment->computation->isPercentage()) {
                        return RateCalculator::calculatePercentage($lineTotalInCents, $adjustment->getRawOriginal('rate'));
                    } else {
                        return $adjustment->getRawOriginal('rate');
                    }
                });

            return $carry + $taxAmountInCents;
        }, 0);

        // Calculate discount based on method
        $discountMethod = DocumentDiscountMethod::parse($this->data['discount_method']) ?? DocumentDiscountMethod::PerLineItem;

        if ($discountMethod->isPerLineItem()) {
            $discountTotalInCents = $lineItems->reduce(function ($carry, $item) use ($currencyCode) {
                $quantity = max((float) ($item['quantity'] ?? 0), 0);
                $unitPrice = max((float) ($item['unit_price'] ?? 0), 0);
                $salesDiscounts = $item['salesDiscounts'] ?? [];
                $lineTotalInCents = CurrencyConverter::convertToCents($quantity * $unitPrice, $currencyCode);

                $discountAmountInCents = Adjustment::whereIn('id', $salesDiscounts)
                    ->get()
                    ->sum(function (Adjustment $adjustment) use ($lineTotalInCents) {
                        if ($adjustment->computation->isPercentage()) {
                            return RateCalculator::calculatePercentage($lineTotalInCents, $adjustment->getRawOriginal('rate'));
                        } else {
                            return $adjustment->getRawOriginal('rate');
                        }
                    });

                return $carry + $discountAmountInCents;
            }, 0);
        } else {
            $discountComputation = AdjustmentComputation::parse($this->data['discount_computation']) ?? AdjustmentComputation::Percentage;
            $discountRate = $this->data['discount_rate'] ?? '0';

            if ($discountComputation->isPercentage()) {
                $scaledDiscountRate = RateCalculator::parseLocalizedRate($discountRate);
                $discountTotalInCents = RateCalculator::calculatePercentage($subtotalInCents, $scaledDiscountRate);
            } else {
                $discountTotalInCents = CurrencyConverter::convertToCents($discountRate, $currencyCode);
            }
        }

        $grandTotalInCents = $subtotalInCents + ($taxTotalInCents - $discountTotalInCents);

        $conversionMessage = null;

        if ($currencyCode !== $defaultCurrencyCode) {
            $rate = currency($currencyCode)->getRate();

            $convertedTotalInCents = CurrencyConverter::convertBalance($grandTotalInCents, $currencyCode, $defaultCurrencyCode);

            $conversionMessage = sprintf(
                'Currency conversion: %s (%s) at an exchange rate of %s',
                CurrencyConverter::formatCentsToMoney($convertedTotalInCents, $defaultCurrencyCode),
                $defaultCurrencyCode,
                $rate
            );
        }

        return [
            'subtotal' => CurrencyConverter::formatCentsToMoney($subtotalInCents, $currencyCode),
            'taxTotal' => CurrencyConverter::formatCentsToMoney($taxTotalInCents, $currencyCode),
            'discountTotal' => CurrencyConverter::formatCentsToMoney($discountTotalInCents, $currencyCode),
            'grandTotal' => CurrencyConverter::formatCentsToMoney($grandTotalInCents, $currencyCode),
            'conversionMessage' => $conversionMessage,
        ];
    }
}
