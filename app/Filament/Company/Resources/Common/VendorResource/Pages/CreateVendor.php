<?php

namespace App\Filament\Company\Resources\Common\VendorResource\Pages;

use App\Concerns\RedirectToListPage;
use App\Filament\Company\Resources\Common\VendorResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\MaxWidth;

class CreateVendor extends CreateRecord
{
    use RedirectToListPage;

    protected static string $resource = VendorResource::class;

    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::FiveExtraLarge;
    }
}
