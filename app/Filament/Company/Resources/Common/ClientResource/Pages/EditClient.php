<?php

namespace App\Filament\Company\Resources\Common\ClientResource\Pages;

use App\Filament\Company\Resources\Common\ClientResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditClient extends EditRecord
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
