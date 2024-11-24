<?php

namespace App\Filament\Company\Resources\Accounting\DocumentResource\Pages;

use App\Filament\Company\Resources\Accounting\DocumentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDocument extends EditRecord
{
    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
