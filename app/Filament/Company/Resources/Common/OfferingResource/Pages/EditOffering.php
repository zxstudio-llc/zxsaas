<?php

namespace App\Filament\Company\Resources\Common\OfferingResource\Pages;

use App\Concerns\RedirectToListPage;
use App\Filament\Company\Resources\Common\OfferingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOffering extends EditRecord
{
    use RedirectToListPage;

    protected static string $resource = OfferingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
