<?php

namespace App\Enums\Accounting;

use Filament\Support\Contracts\HasLabel;

enum DocumentEntityType: string implements HasLabel
{
    case Client = 'client';
    case Vendor = 'vendor';

    public function getLabel(): ?string
    {
        return $this->name;
    }

    public function getReportTitle(): string
    {
        return match ($this) {
            self::Client => 'Accounts Receivable Aging',
            self::Vendor => 'Accounts Payable Aging',
        };
    }
}
