<?php

namespace App\Filament\Company\Resources\Common\OfferingResource\Pages;

use App\Filament\Company\Resources\Common\OfferingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOfferings extends ListRecords
{
    protected static string $resource = OfferingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
