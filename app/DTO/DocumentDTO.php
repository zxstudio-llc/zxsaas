<?php

namespace App\DTO;

use App\Models\Accounting\Document;
use App\Models\Setting\DocumentDefault;
use App\Utilities\Currency\CurrencyAccessor;
use App\Utilities\Currency\CurrencyConverter;

readonly class DocumentDTO
{
    /**
     * @param  LineItemDTO[]  $lineItems
     */
    public function __construct(
        public ?string $header,
        public ?string $footer,
        public ?string $terms,
        public ?string $logo,
        public string $number,
        public ?string $referenceNumber,
        public string $date,
        public string $dueDate,
        public string $currencyCode,
        public string $subtotal,
        public string $discount,
        public string $tax,
        public string $total,
        public string $amountDue,
        public CompanyDTO $company,
        public ClientDTO $client,
        public iterable $lineItems,
        public DocumentLabelDTO $label,
        public string $accentColor = '#000000',
        public bool $showLogo = true,
    ) {}

    public static function fromModel(Document $document): self
    {
        /** @var DocumentDefault $settings */
        $settings = $document->company->defaultInvoice;

        return new self(
            header: $document->header,
            footer: $document->footer,
            terms: $document->terms,
            logo: $document->logo,
            number: $document->documentNumber(),
            referenceNumber: $document->referenceNumber(),
            date: $document->documentDate(),
            dueDate: $document->dueDate(),
            currencyCode: $document->currency_code ?? CurrencyAccessor::getDefaultCurrency(),
            subtotal: self::formatToMoney($document->subtotal, $document->currency_code),
            discount: self::formatToMoney($document->discount_total, $document->currency_code),
            tax: self::formatToMoney($document->tax_total, $document->currency_code),
            total: self::formatToMoney($document->total, $document->currency_code),
            amountDue: self::formatToMoney($document->amountDue(), $document->currency_code),
            company: CompanyDTO::fromModel($document->company),
            client: ClientDTO::fromModel($document->client),
            lineItems: $document->lineItems->map(fn ($item) => LineItemDTO::fromModel($item)),
            label: $document->documentType()->getLabels(),
            accentColor: $settings->accent_color ?? '#000000',
            showLogo: $settings->show_logo ?? false,
        );
    }

    private static function formatToMoney(float | string $value, ?string $currencyCode): string
    {
        return CurrencyConverter::formatToMoney($value, $currencyCode);
    }
}
