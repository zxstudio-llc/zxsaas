<?php

namespace App\Filament\Company\Resources\Common\OfferingResource\Pages;

use App\Concerns\RedirectToListPage;
use App\Filament\Company\Resources\Common\OfferingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOffering extends CreateRecord
{
    use RedirectToListPage;

    protected static string $resource = OfferingResource::class;
}
