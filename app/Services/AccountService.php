<?php

namespace App\Services;

use App\Contracts\AccountHandler;
use App\Enums\Accounting\AccountCategory;
use App\Models\Accounting\Account;
use App\Models\Accounting\Transaction;
use App\Models\Banking\BankAccount;
use App\Repositories\Accounting\JournalEntryRepository;
use App\Utilities\Currency\CurrencyAccessor;
use App\ValueObjects\Money;

class AccountService implements AccountHandler
{
    public function __construct(
        protected JournalEntryRepository $journalEntryRepository
    ) {
    }

    public function getDebitBalance(Account $account, string $startDate, string $endDate): Money
    {
        $amount = $this->journalEntryRepository->sumDebitAmounts($account, $startDate, $endDate);

        return new Money($amount, $account->currency_code);
    }

    public function getCreditBalance(Account $account, string $startDate, string $endDate): Money
    {
        $amount = $this->journalEntryRepository->sumCreditAmounts($account, $startDate, $endDate);

        return new Money($amount, $account->currency_code);
    }

    public function getNetMovement(Account $account, string $startDate, string $endDate): Money
    {
        $debitBalance = $this->journalEntryRepository->sumDebitAmounts($account, $startDate, $endDate);
        $creditBalance = $this->journalEntryRepository->sumCreditAmounts($account, $startDate, $endDate);
        $netMovement = $this->calculateNetMovementByCategory($account->category, $debitBalance, $creditBalance);

        return new Money($netMovement, $account->currency_code);
    }

    public function getStartingBalance(Account $account, string $startDate): ?Money
    {
        if (in_array($account->category, [AccountCategory::Expense, AccountCategory::Revenue], true)) {
            return null;
        }

        $debitBalanceBefore = $this->journalEntryRepository->sumDebitAmounts($account, $startDate);
        $creditBalanceBefore = $this->journalEntryRepository->sumCreditAmounts($account, $startDate);
        $startingBalance = $this->calculateNetMovementByCategory($account->category, $debitBalanceBefore, $creditBalanceBefore);

        return new Money($startingBalance, $account->currency_code);
    }

    public function getEndingBalance(Account $account, string $startDate, string $endDate): ?Money
    {
        $netMovement = $this->getNetMovement($account, $startDate, $endDate)->getAmount();

        if (in_array($account->category, [AccountCategory::Expense, AccountCategory::Revenue], true)) {
            return new Money($netMovement, $account->currency_code);
        }

        $startingBalance = $this->getStartingBalance($account, $startDate)?->getAmount();
        $endingBalance = $startingBalance + $netMovement;

        return new Money($endingBalance, $account->currency_code);
    }

    private function calculateNetMovementByCategory(AccountCategory $category, int $debitBalance, int $creditBalance): int
    {
        return match ($category) {
            AccountCategory::Asset, AccountCategory::Expense => $debitBalance - $creditBalance,
            AccountCategory::Liability, AccountCategory::Equity, AccountCategory::Revenue => $creditBalance - $debitBalance,
        };
    }

    public function getBalances(Account $account, string $startDate, string $endDate): array
    {
        $debitBalance = $this->getDebitBalance($account, $startDate, $endDate)->getAmount();
        $creditBalance = $this->getCreditBalance($account, $startDate, $endDate)->getAmount();
        $netMovement = $this->getNetMovement($account, $startDate, $endDate)->getAmount();

        $balances = [
            'debit_balance' => $debitBalance,
            'credit_balance' => $creditBalance,
            'net_movement' => $netMovement,
        ];

        if (! in_array($account->category, [AccountCategory::Expense, AccountCategory::Revenue], true)) {
            $balances['starting_balance'] = $this->getStartingBalance($account, $startDate)?->getAmount();
            $balances['ending_balance'] = $this->getEndingBalance($account, $startDate, $endDate)?->getAmount();
        }

        return $balances;
    }

    public function getTotalBalanceForAllBankAccounts(string $startDate, string $endDate): Money
    {
        $bankAccounts = BankAccount::with('account')
            ->get();

        $totalBalance = 0;

        foreach ($bankAccounts as $bankAccount) {
            $account = $bankAccount->account;

            if ($account) {
                $endingBalance = $this->getEndingBalance($account, $startDate, $endDate)?->getAmount() ?? 0;
                $totalBalance += $endingBalance;
            }
        }

        return new Money($totalBalance, CurrencyAccessor::getDefaultCurrency());
    }

    public function getAccountCategoryOrder(): array
    {
        return [
            AccountCategory::Asset->getPluralLabel(),
            AccountCategory::Liability->getPluralLabel(),
            AccountCategory::Equity->getPluralLabel(),
            AccountCategory::Revenue->getPluralLabel(),
            AccountCategory::Expense->getPluralLabel(),
        ];
    }

    public function getEarliestTransactionDate(): string
    {
        $earliestDate = Transaction::oldest('posted_at')
            ->value('posted_at');

        return $earliestDate ?? now()->format('Y-m-d');
    }
}
