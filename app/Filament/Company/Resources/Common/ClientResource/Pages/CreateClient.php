<?php

namespace App\Filament\Company\Resources\Common\ClientResource\Pages;

use App\Filament\Company\Resources\Common\ClientResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\MaxWidth;

class CreateClient extends CreateRecord
{
    protected static string $resource = ClientResource::class;

    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::FiveExtraLarge;
    }
}
