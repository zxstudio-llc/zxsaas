<?php

namespace App\Observers;

use App\Enums\Accounting\JournalEntryType;
use App\Enums\Accounting\TransactionType;
use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\Transaction;
use Illuminate\Support\Facades\DB;

class TransactionObserver
{
    /**
     * Handle the Transaction "created" event.
     */
    public function created(Transaction $transaction): void
    {
        if ($transaction->type === TransactionType::Journal) {
            return;
        }

        $chartAccount = $transaction->account;
        $bankAccount = $transaction->bankAccount->account;

        $debitAccount = $transaction->type === TransactionType::Withdrawal ? $chartAccount : $bankAccount;
        $creditAccount = $transaction->type === TransactionType::Withdrawal ? $bankAccount : $chartAccount;

        $this->createJournalEntries($transaction, $debitAccount, $creditAccount);
    }

    private function createJournalEntries(Transaction $transaction, Account $debitAccount, Account $creditAccount): void
    {
        $debitAccount->journalEntries()->create([
            'company_id' => $transaction->company_id,
            'transaction_id' => $transaction->id,
            'type' => JournalEntryType::Debit,
            'amount' => $transaction->amount,
            'description' => $transaction->description,
        ]);

        $creditAccount->journalEntries()->create([
            'company_id' => $transaction->company_id,
            'transaction_id' => $transaction->id,
            'type' => JournalEntryType::Credit,
            'amount' => $transaction->amount,
            'description' => $transaction->description,
        ]);
    }

    /**
     * Handle the Transaction "updated" event.
     */
    public function updated(Transaction $transaction): void
    {
        if ($transaction->type === TransactionType::Journal || $this->hasRelevantChanges($transaction) === false) {
            return;
        }

        $chartAccount = $transaction->account;
        $bankAccount = $transaction->bankAccount?->account;

        if (! $chartAccount || ! $bankAccount) {
            return;
        }

        $journalEntries = $transaction->journalEntries;

        $debitEntry = $journalEntries->where('type', JournalEntryType::Debit)->first();
        $creditEntry = $journalEntries->where('type', JournalEntryType::Credit)->first();

        if (! $debitEntry || ! $creditEntry) {
            return;
        }

        $debitAccount = $transaction->type === TransactionType::Withdrawal ? $chartAccount : $bankAccount;
        $creditAccount = $transaction->type === TransactionType::Withdrawal ? $bankAccount : $chartAccount;

        $this->updateJournalEntriesForTransaction($debitEntry, $debitAccount, $transaction);
        $this->updateJournalEntriesForTransaction($creditEntry, $creditAccount, $transaction);
    }

    protected function hasRelevantChanges(Transaction $transaction): bool
    {
        return $transaction->wasChanged(['amount', 'account_id', 'bank_account_id', 'type']);
    }

    protected function updateJournalEntriesForTransaction(JournalEntry $journalEntry, Account $account, Transaction $transaction): void
    {
        DB::transaction(static function () use ($journalEntry, $account, $transaction) {
            $journalEntry->update([
                'account_id' => $account->id,
                'amount' => $transaction->amount,
            ]);
        });
    }

    /**
     * Handle the Transaction "deleting" event.
     */
    public function deleting(Transaction $transaction): void
    {
        DB::transaction(static function () use ($transaction) {
            $transaction->journalEntries()->each(fn (JournalEntry $entry) => $entry->delete());
        });
    }

    /**
     * Handle the Transaction "deleted" event.
     */
    public function deleted(Transaction $transaction): void
    {
        //
    }

    /**
     * Handle the Transaction "restored" event.
     */
    public function restored(Transaction $transaction): void
    {
        //
    }

    /**
     * Handle the Transaction "force deleted" event.
     */
    public function forceDeleted(Transaction $transaction): void
    {
        //
    }
}
