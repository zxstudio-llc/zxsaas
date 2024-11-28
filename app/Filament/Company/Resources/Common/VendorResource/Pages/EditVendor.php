<?php

namespace App\Filament\Company\Resources\Common\VendorResource\Pages;

use App\Concerns\RedirectToListPage;
use App\Filament\Company\Resources\Common\VendorResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\MaxWidth;

class EditVendor extends EditRecord
{
    use RedirectToListPage;

    protected static string $resource = VendorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::FiveExtraLarge;
    }
}
