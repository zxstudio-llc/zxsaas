<?php

namespace App\Filament\Company\Resources\Purchases\BillResource\Pages;

use App\Concerns\RedirectToListPage;
use App\Filament\Company\Resources\Purchases\BillResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\MaxWidth;

class EditBill extends EditRecord
{
    use RedirectToListPage;

    protected static string $resource = BillResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::Full;
    }
}
