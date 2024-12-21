<?php

namespace App\Enums\Accounting;

use App\Enums\Concerns\ParsesEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EstimateStatus: string implements HasColor, HasLabel
{
    use ParsesEnum;

    case Draft = 'draft';
    case Sent = 'sent';
    case Approved = 'approved';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Converted = 'converted';

    public function getLabel(): ?string
    {
        return $this->name;
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Sent => 'primary',
            self::Approved, self::Accepted => 'success',
            self::Rejected => 'danger',
            self::Expired => 'warning',
            self::Converted => 'info',
        };
    }
}
