<?php

namespace App\Filament\Company\Resources\Sales\InvoiceResource\Pages;

use App\Filament\Company\Resources\Sales\InvoiceResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\MaxWidth;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::Full;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['type'] = 'invoice';

        return parent::mutateFormDataBeforeCreate($data);
    }
}
