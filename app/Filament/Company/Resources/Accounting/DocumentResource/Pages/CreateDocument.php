<?php

namespace App\Filament\Company\Resources\Accounting\DocumentResource\Pages;

use App\Filament\Company\Resources\Accounting\DocumentResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\MaxWidth;

class CreateDocument extends CreateRecord
{
    protected static string $resource = DocumentResource::class;

    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::Full;
    }
}
