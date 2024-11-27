<?php

namespace App\Enums\Accounting;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DocumentStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Sent = 'sent';

    case Partial = 'partial';

    case Paid = 'paid';

    case Overdue = 'overdue';

    case Void = 'void';

    case Unpaid = 'unpaid';

    public function getInvoiceStatuses(): array
    {
        return [
            self::Draft,
            self::Sent,
            self::Partial,
            self::Paid,
            self::Overdue,
            self::Void,
        ];
    }

    public function getBillStatuses(): array
    {
        return [
            self::Partial,
            self::Paid,
            self::Unpaid,
            self::Void,
        ];
    }

    public function getLabel(): ?string
    {
        return $this->name;
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::Draft, self::Void => 'gray',
            self::Sent => 'primary',
            self::Partial, self::Unpaid => 'warning',
            self::Paid => 'success',
            self::Overdue => 'danger',
        };
    }
}
