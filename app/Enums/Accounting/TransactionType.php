<?php

namespace App\Enums\Accounting;

use App\Enums\Concerns\ParsesEnum;
use Filament\Support\Contracts\HasLabel;

enum TransactionType: string implements HasLabel
{
    use ParsesEnum;

    case Deposit = 'deposit';
    case Withdrawal = 'withdrawal';
    case Journal = 'journal';

    public function getLabel(): ?string
    {
        return $this->name;
    }
}
