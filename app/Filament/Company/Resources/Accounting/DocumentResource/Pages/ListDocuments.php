<?php

namespace App\Filament\Company\Resources\Accounting\DocumentResource\Pages;

use App\Filament\Company\Resources\Accounting\DocumentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDocuments extends ListRecords
{
    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
