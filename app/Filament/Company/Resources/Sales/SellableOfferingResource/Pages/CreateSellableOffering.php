<?php

namespace App\Filament\Company\Resources\Sales\SellableOfferingResource\Pages;

use App\Filament\Company\Resources\Purchases\BuyableOfferingResource;
use App\Filament\Company\Resources\Sales\SellableOfferingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSellableOffering extends CreateRecord
{
    protected static string $resource = SellableOfferingResource::class;

    protected function getRedirectUrl(): string
    {
        if ($this->record->expense_account_id && ! $this->record->income_account_id) {
            return BuyableOfferingResource::getUrl();
        } else {
            return $this->getResource()::getUrl('index');
        }
    }
}
