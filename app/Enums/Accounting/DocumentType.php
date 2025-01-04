<?php

namespace App\Enums\Accounting;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum DocumentType: string implements HasIcon, HasLabel
{
    case Invoice = 'invoice';
    case Bill = 'bill';
    case Estimate = 'estimate';
    case RecurringInvoice = 'recurring_invoice';

    public const DEFAULT = self::Invoice->value;

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Invoice, self::Bill, self::Estimate => $this->name,
            self::RecurringInvoice => 'Recurring Invoice',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this->value) {
            self::Invoice->value, self::RecurringInvoice->value => 'heroicon-o-document-duplicate',
            self::Bill->value => 'heroicon-o-clipboard-document-list',
            self::Estimate->value => 'heroicon-o-document-text',
        };
    }

    public function getTaxKey(): string
    {
        return match ($this) {
            self::Invoice, self::RecurringInvoice, self::Estimate => 'salesTaxes',
            self::Bill => 'purchaseTaxes',
        };
    }

    public function getDiscountKey(): string
    {
        return match ($this) {
            self::Invoice, self::RecurringInvoice, self::Estimate => 'salesDiscounts',
            self::Bill => 'purchaseDiscounts',
        };
    }

    public function getLabels(): array
    {
        return match ($this) {
            self::Invoice => [
                'title' => 'Invoice',
                'number' => 'Invoice Number',
                'reference_number' => 'P.O/S.O Number',
                'date' => 'Invoice Date',
                'due_date' => 'Payment Due',
                'amount_due' => 'Amount Due',
            ],
            self::RecurringInvoice => [
                'title' => 'Recurring Invoice',
                'number' => 'Invoice Number',
                'reference_number' => 'P.O/S.O Number',
                'date' => 'Invoice Date',
                'due_date' => 'Payment Due',
                'amount_due' => 'Amount Due',
            ],
            self::Estimate => [
                'title' => 'Estimate',
                'number' => 'Estimate Number',
                'reference_number' => 'Reference Number',
                'date' => 'Estimate Date',
                'due_date' => 'Expiration Date',
                'amount_due' => 'Grand Total',
            ],
            self::Bill => [
                'title' => 'Bill',
                'number' => 'Bill Number',
                'reference_number' => 'P.O/S.O Number',
                'date' => 'Bill Date',
                'due_date' => 'Payment Due',
                'amount_due' => 'Amount Due',
            ],
        };
    }
}
