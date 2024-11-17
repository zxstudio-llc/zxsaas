<?php

namespace App\Filament\Company\Resources\Purchases\BuyableOfferingResource\Pages;

use App\Filament\Company\Resources\Purchases\BuyableOfferingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListBuyableOfferings extends ListRecords
{
    protected static string $resource = BuyableOfferingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getHeading(): string | Htmlable
    {
        return 'Products & Services (Purchases)';
    }
}
