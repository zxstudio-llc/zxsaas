<?php

namespace App\Filament\Company\Resources\Sales\RecurringInvoiceResource\Pages;

use App\Concerns\ManagesLineItems;
use App\Filament\Company\Resources\Sales\RecurringInvoiceResource;
use App\Models\Accounting\RecurringInvoice;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Database\Eloquent\Model;

class CreateRecurringInvoice extends CreateRecord
{
    use ManagesLineItems;

    protected static string $resource = RecurringInvoiceResource::class;

    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::Full;
    }

    protected function handleRecordCreation(array $data): Model
    {
        /** @var RecurringInvoice $record */
        $record = parent::handleRecordCreation($data);

        $this->handleLineItems($record, collect($data['lineItems'] ?? []));

        $totals = $this->updateDocumentTotals($record, $data);

        $record->updateQuietly($totals);

        return $record;
    }
}
