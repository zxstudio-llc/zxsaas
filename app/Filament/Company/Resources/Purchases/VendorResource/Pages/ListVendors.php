<?php

namespace App\Filament\Company\Resources\Purchases\VendorResource\Pages;

use App\Filament\Company\Resources\Purchases\VendorResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVendors extends ListRecords
{
    protected static string $resource = VendorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
