<?php

namespace App\Filament\Company\Resources\Purchases\BuyableOfferingResource\Pages;

use App\Filament\Company\Resources\Purchases\BuyableOfferingResource;
use App\Filament\Company\Resources\Sales\SellableOfferingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBuyableOffering extends CreateRecord
{
    protected static string $resource = BuyableOfferingResource::class;

    protected function getRedirectUrl(): string
    {
        if ($this->record->income_account_id && ! $this->record->expense_account_id) {
            return SellableOfferingResource::getUrl();
        } else {
            return $this->getResource()::getUrl('index');
        }
    }
}
