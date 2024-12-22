<?php

namespace App\Enums\Accounting;

use Filament\Support\Contracts\HasLabel;

enum EstimateStatus: string implements HasLabel
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Viewed = 'viewed';
    case Unsent = 'unsent';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Expired = 'expired';
    case Converted = 'converted';

    public function getLabel(): ?string
    {
        return $this->name;
    }
}
