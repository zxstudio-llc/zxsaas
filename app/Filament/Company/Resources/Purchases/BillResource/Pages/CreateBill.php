<?php

namespace App\Filament\Company\Resources\Purchases\BillResource\Pages;

use App\Concerns\RedirectToListPage;
use App\Filament\Company\Resources\Purchases\BillResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\MaxWidth;

class CreateBill extends CreateRecord
{
    use RedirectToListPage;

    protected static string $resource = BillResource::class;

    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::Full;
    }
}
