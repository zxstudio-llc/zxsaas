<?php

namespace App\Enums\Accounting;

use Filament\Support\Contracts\HasLabel;

enum AdjustmentCategory: string implements HasLabel
{
    case Tax = 'tax';
    case Discount = 'discount';

    public function getLabel(): ?string
    {
        return $this->name;
    }
}
