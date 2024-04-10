<?php

namespace App\Observers;

use App\Enums\Accounting\AccountCategory;
use App\Enums\Accounting\AccountType;
use App\Models\Accounting\Account;
use App\Models\Accounting\AccountSubtype;
use App\Models\Banking\BankAccount;
use App\Utilities\Accounting\AccountCode;

class AccountObserver
{
    public function creating(Account $account): void
    {
        $this->setCategoryAndType($account, true);
    }

    public function updating(Account $account): void
    {
        if ($account->isDirty('subtype_id')) {
            $this->setCategoryAndType($account, false);
        }
    }

    private function setCategoryAndType(Account $account, bool $isCreating): void
    {
        $subtype = $account->subtype_id ? AccountSubtype::find($account->subtype_id) : null;

        if ($subtype) {
            $account->category = $subtype->category;
            $account->type = $subtype->type;
        } elseif ($isCreating) {
            $account->category = AccountCategory::Asset;
            $account->type = AccountType::CurrentAsset;
        }
    }

    private function setFieldsForBankAccount(Account $account): void
    {
        $generatedAccountCode = AccountCode::generate($account->company_id, $account->subtype_id);

        $account->code = $generatedAccountCode;

        $account->save();
    }

    /**
     * Handle the Account "created" event.
     */
    public function created(Account $account): void
    {
        if (($account->accountable_type === BankAccount::class) && $account->code === null) {
            $this->setFieldsForBankAccount($account);
        }
    }

    /**
     * Handle the Account "updated" event.
     */
    public function updated(Account $account): void
    {
        //
    }

    /**
     * Handle the Account "deleted" event.
     */
    public function deleted(Account $account): void
    {
        //
    }

    /**
     * Handle the Account "restored" event.
     */
    public function restored(Account $account): void
    {
        //
    }

    /**
     * Handle the Account "force deleted" event.
     */
    public function forceDeleted(Account $account): void
    {
        //
    }
}
