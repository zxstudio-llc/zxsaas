<?php

namespace App\Enums\Common;

use Filament\Support\Contracts\HasLabel;

enum OfferingType: string implements HasLabel
{
    case Product = 'product';
    case Service = 'service';

    public function getLabel(): string
    {
        return $this->name;
    }
}
