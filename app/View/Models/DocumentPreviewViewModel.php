<?php

namespace App\View\Models;

use App\Enums\Accounting\AdjustmentComputation;
use App\Enums\Accounting\DocumentDiscountMethod;
use App\Enums\Accounting\DocumentType;
use App\Models\Accounting\Adjustment;
use App\Models\Accounting\DocumentLineItem;
use App\Models\Common\Client;
use App\Models\Company;
use App\Models\Setting\DocumentDefault;
use App\Utilities\Currency\CurrencyAccessor;
use App\Utilities\Currency\CurrencyConverter;
use App\Utilities\RateCalculator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;

class DocumentPreviewViewModel
{
    public function __construct(
        public Model $document,
        public DocumentType $documentType = DocumentType::Invoice,
    ) {}

    public function buildViewData(): array
    {
        return [
            'company' => $this->getCompanyDetails(),
            'client' => $this->getClientDetails(),
            'metadata' => $this->getDocumentMetadata(),
            'lineItems' => $this->getLineItems(),
            'totals' => $this->getTotals(),
            'header' => $this->document->header,
            'footer' => $this->document->footer,
            'terms' => $this->document->terms,
            'logo' => $this->document->logo,
            'style' => $this->getStyle(),
            'labels' => $this->documentType->getLabels(),
        ];
    }

    private function getCompanyDetails(): array
    {
        /** @var Company $company */
        $company = $this->document->company;
        $profile = $company->profile;

        return [
            'name' => $company->name,
            'address' => $profile->address ?? '',
            'city' => $profile->city?->name ?? '',
            'state' => $profile->state?->name ?? '',
            'zip_code' => $profile->zip_code ?? '',
            'country' => $profile->state?->country->name ?? '',
        ];
    }

    private function getClientDetails(): array
    {
        /** @var Client $client */
        $client = $this->document->client;
        $address = $client->billingAddress ?? null;

        return [
            'name' => $client->name,
            'address_line_1' => $address->address_line_1 ?? '',
            'address_line_2' => $address->address_line_2 ?? '',
            'city' => $address->city ?? '',
            'state' => $address->state ?? '',
            'postal_code' => $address->postal_code ?? '',
            'country' => $address->country ?? '',
        ];
    }

    private function getDocumentMetadata(): array
    {
        return [
            'number' => $this->document->invoice_number ?? $this->document->estimate_number,
            'reference_number' => $this->document->order_number ?? $this->document->reference_number,
            'date' => $this->document->date?->toDefaultDateFormat(),
            'due_date' => $this->document->due_date?->toDefaultDateFormat() ?? $this->document->expiration_date?->toDefaultDateFormat(),
            'currency_code' => $this->document->currency_code ?? CurrencyAccessor::getDefaultCurrency(),
        ];
    }

    private function getLineItems(): array
    {
        $currencyCode = $this->document->currency_code ?? CurrencyAccessor::getDefaultCurrency();

        return $this->document->lineItems->map(fn (DocumentLineItem $item) => [
            'name' => $item->offering->name ?? '',
            'description' => $item->description ?? '',
            'quantity' => $item->quantity,
            'unit_price' => CurrencyConverter::formatToMoney($item->unit_price, $currencyCode),
            'subtotal' => CurrencyConverter::formatToMoney($item->subtotal, $currencyCode),
        ])->toArray();
    }

    private function getTotals(): array
    {
        $currencyCode = $this->document->currency_code ?? CurrencyAccessor::getDefaultCurrency();

        return [
            'subtotal' => CurrencyConverter::formatToMoney($this->document->subtotal, $currencyCode),
            'discount' => CurrencyConverter::formatToMoney($this->document->discount_total, $currencyCode),
            'tax' => CurrencyConverter::formatToMoney($this->document->tax_total, $currencyCode),
            'total' => CurrencyConverter::formatToMoney($this->document->total, $currencyCode),
            'amount_due' => $this->document->amount_due ? CurrencyConverter::formatToMoney($this->document->amount_due, $currencyCode) : null,
        ];
    }

    private function getStyle(): array
    {
        /** @var DocumentDefault $settings */
        $settings = $this->document->company->defaultInvoice;

        return [
            'accent_color' => $settings->accent_color ?? '#000000',
            'show_logo' => $settings->show_logo ?? false,
        ];
    }

    private function calculateLineSubtotalInCents(array $item, string $currencyCode): int
    {
        $quantity = max((float) ($item['quantity'] ?? 0), 0);
        $unitPrice = max((float) ($item['unit_price'] ?? 0), 0);

        $subtotal = $quantity * $unitPrice;

        return CurrencyConverter::convertToCents($subtotal, $currencyCode);
    }

    private function calculateAdjustmentsTotalInCents($lineItems, string $key, string $currencyCode): int
    {
        return $lineItems->reduce(function ($carry, $item) use ($key, $currencyCode) {
            $quantity = max((float) ($item['quantity'] ?? 0), 0);
            $unitPrice = max((float) ($item['unit_price'] ?? 0), 0);
            $adjustmentIds = $item[$key] ?? [];
            $lineTotal = $quantity * $unitPrice;

            $lineTotalInCents = CurrencyConverter::convertToCents($lineTotal, $currencyCode);

            $adjustmentTotal = Adjustment::whereIn('id', $adjustmentIds)
                ->get()
                ->sum(function (Adjustment $adjustment) use ($lineTotalInCents) {
                    if ($adjustment->computation->isPercentage()) {
                        return RateCalculator::calculatePercentage($lineTotalInCents, $adjustment->getRawOriginal('rate'));
                    } else {
                        return $adjustment->getRawOriginal('rate');
                    }
                });

            return $carry + $adjustmentTotal;
        }, 0);
    }

    private function calculateDiscountTotalInCents($lineItems, int $subtotalInCents, string $currencyCode): int
    {
        $discountMethod = DocumentDiscountMethod::parse($this->data['discount_method']) ?? DocumentDiscountMethod::PerLineItem;

        if ($discountMethod->isPerLineItem()) {
            return $this->calculateAdjustmentsTotalInCents($lineItems, $this->documentType->getDiscountKey(), $currencyCode);
        }

        $discountComputation = AdjustmentComputation::parse($this->data['discount_computation']) ?? AdjustmentComputation::Percentage;
        $discountRate = blank($this->data['discount_rate']) ? '0' : $this->data['discount_rate'];

        if ($discountComputation->isPercentage()) {
            $scaledDiscountRate = RateCalculator::parseLocalizedRate($discountRate);

            return RateCalculator::calculatePercentage($subtotalInCents, $scaledDiscountRate);
        }

        if (! CurrencyConverter::isValidAmount($discountRate)) {
            $discountRate = '0';
        }

        return CurrencyConverter::convertToCents($discountRate, $currencyCode);
    }

    private function buildConversionMessage(int $grandTotalInCents, string $currencyCode, string $defaultCurrencyCode): ?string
    {
        if ($currencyCode === $defaultCurrencyCode) {
            return null;
        }

        $rate = currency($currencyCode)->getRate();
        $indirectRate = 1 / $rate;

        $convertedTotalInCents = CurrencyConverter::convertBalance($grandTotalInCents, $currencyCode, $defaultCurrencyCode);

        $formattedRate = Number::format($indirectRate, maxPrecision: 10);

        return sprintf(
            'Currency conversion: %s (%s) at an exchange rate of 1 %s = %s %s',
            CurrencyConverter::formatCentsToMoney($convertedTotalInCents, $defaultCurrencyCode),
            $defaultCurrencyCode,
            $currencyCode,
            $formattedRate,
            $defaultCurrencyCode
        );
    }
}
