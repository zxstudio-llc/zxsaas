<?php

namespace App\Filament\Company\Resources\Sales\ClientResource\Pages;

use App\Concerns\RedirectToListPage;
use App\Filament\Company\Resources\Sales\ClientResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\MaxWidth;

class CreateClient extends CreateRecord
{
    use RedirectToListPage;

    protected static string $resource = ClientResource::class;

    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::FiveExtraLarge;
    }
}
