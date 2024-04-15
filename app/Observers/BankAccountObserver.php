<?php

namespace App\Observers;

use App\Enums\Accounting\AccountType;
use App\Models\Accounting\AccountSubtype;
use App\Models\Accounting\Transaction;
use App\Models\Banking\BankAccount;
use Illuminate\Support\Facades\DB;

class BankAccountObserver
{
    /**
     * Handle the BankAccount "created" event.
     */
    public function created(BankAccount $bankAccount): void
    {
        //
    }

    /**
     * Handle the BankAccount "creating" event.
     */
    public function creating(BankAccount $bankAccount): void
    {
        //
    }

    /**
     * Get the default bank account subtype.
     */
    protected function getDefaultBankAccountSubtype(int $companyId, AccountType $type)
    {
        $subType = AccountSubtype::where('company_id', $companyId)
            ->where('name', 'Cash and Cash Equivalents')
            ->where('type', $type)
            ->first();

        if (! $subType) {
            $subType = AccountSubtype::where('company_id', $companyId)
                ->where('type', $type)
                ->first();
        }

        return $subType?->id;
    }

    /**
     * Handle the BankAccount "updated" event.
     */
    public function updated(BankAccount $bankAccount): void
    {
        //
    }

    /**
     * Handle the BankAccount "deleting" event.
     */
    public function deleting(BankAccount $bankAccount): void
    {
        DB::transaction(function () use ($bankAccount) {
            $account = $bankAccount->account;
            $connectedBankAccount = $bankAccount->connectedBankAccount;

            if ($account) {
                $bankAccount->transactions()->each(fn (Transaction $transaction) => $transaction->delete());
                $account->delete();
            }

            if ($connectedBankAccount) {
                $connectedBankAccount->delete();
            }
        });
    }

    /**
     * Handle the BankAccount "deleted" event.
     */
    public function deleted(BankAccount $bankAccount): void
    {
        //
    }

    /**
     * Handle the BankAccount "restored" event.
     */
    public function restored(BankAccount $bankAccount): void
    {
        //
    }

    /**
     * Handle the BankAccount "force deleted" event.
     */
    public function forceDeleted(BankAccount $bankAccount): void
    {
        //
    }
}
