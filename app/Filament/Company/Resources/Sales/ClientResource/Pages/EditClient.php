<?php

namespace App\Filament\Company\Resources\Sales\ClientResource\Pages;

use App\Concerns\RedirectToListPage;
use App\Filament\Company\Resources\Sales\ClientResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\MaxWidth;

class EditClient extends EditRecord
{
    use RedirectToListPage;

    protected static string $resource = ClientResource::class;

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
