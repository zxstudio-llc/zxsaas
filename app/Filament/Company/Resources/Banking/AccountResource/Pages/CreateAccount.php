<?php

namespace App\Filament\Company\Resources\Banking\AccountResource\Pages;

use App\Concerns\HandlesResourceRecordCreation;
use App\Filament\Company\Resources\Banking\AccountResource;
use App\Models\Banking\BankAccount;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateAccount extends CreateRecord
{
    use HandlesResourceRecordCreation;

    protected static string $resource = AccountResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['enabled'] = (bool) ($data['enabled'] ?? false);

        return $data;
    }

    /**
     * @throws Halt
     */
    protected function handleRecordCreation(array $data): Model
    {
        $user = Auth::user();

        if (! $user) {
            throw new Halt('No authenticated user found');
        }

        return $this->handleRecordCreationWithUniqueField($data, new BankAccount, $user);
    }
}
