<?php

namespace App\Filament\Company\Resources\Sales\SellableOfferingResource\Pages;

use App\Filament\Company\Resources\Sales\SellableOfferingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListSellableOfferings extends ListRecords
{
    protected static string $resource = SellableOfferingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('New Product or Service'),
        ];
    }

    public function getHeading(): string | Htmlable
    {
        return 'Products & Services (Sales)';
    }
}
