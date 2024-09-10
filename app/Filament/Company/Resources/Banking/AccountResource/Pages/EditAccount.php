<?php

namespace App\Filament\Company\Resources\Banking\AccountResource\Pages;

use App\Concerns\HandlesResourceRecordUpdate;
use App\Filament\Company\Resources\Banking\AccountResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class EditAccount extends EditRecord
{
    use HandlesResourceRecordUpdate;

    protected static string $resource = AccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['enabled'] = (bool) ($data['enabled'] ?? false);

        return $data;
    }

    /**
     * @throws Halt
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $user = Auth::user();

        if (! $user) {
            throw new Halt('No authenticated user found');
        }

        return $this->handleRecordUpdateWithUniqueField($record, $data, $user);
    }
}
