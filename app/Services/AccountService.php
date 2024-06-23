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
    ) {}

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
        $balances = $this->calculateBalances($account, $startDate, $endDate);

        return new Money($balances['net_movement'], $account->currency_code);
    }

    public function getStartingBalance(Account $account, string $startDate): ?Money
    {
        if (in_array($account->category, [AccountCategory::Expense, AccountCategory::Revenue], true)) {
            return null;
        }

        $balances = $this->calculateStartingBalances($account, $startDate);

        return new Money($balances['starting_balance'], $account->currency_code);
    }

    public function getEndingBalance(Account $account, string $startDate, string $endDate): ?Money
    {
        $calculatedBalances = $this->calculateBalances($account, $startDate, $endDate);
        $startingBalances = $this->calculateStartingBalances($account, $startDate);

        $netMovement = $calculatedBalances['net_movement'];

        if (in_array($account->category, [AccountCategory::Expense, AccountCategory::Revenue], true)) {
            return new Money($netMovement, $account->currency_code);
        }

        $endingBalance = $startingBalances['starting_balance'] + $netMovement;

        return new Money($endingBalance, $account->currency_code);
    }

    private function calculateNetMovementByCategory(AccountCategory $category, int $debitBalance, int $creditBalance): int
    {
        return match ($category) {
            AccountCategory::Asset, AccountCategory::Expense => $debitBalance - $creditBalance,
            AccountCategory::Liability, AccountCategory::Equity, AccountCategory::Revenue => $creditBalance - $debitBalance,
        };
    }

    private function calculateBalances(Account $account, string $startDate, string $endDate): array
    {
        $debitBalance = $this->journalEntryRepository->sumDebitAmounts($account, $startDate, $endDate);
        $creditBalance = $this->journalEntryRepository->sumCreditAmounts($account, $startDate, $endDate);

        return [
            'debit_balance' => $debitBalance,
            'credit_balance' => $creditBalance,
            'net_movement' => $this->calculateNetMovementByCategory($account->category, $debitBalance, $creditBalance),
        ];
    }

    private function calculateStartingBalances(Account $account, string $startDate): array
    {
        $debitBalanceBefore = $this->journalEntryRepository->sumDebitAmounts($account, $startDate);
        $creditBalanceBefore = $this->journalEntryRepository->sumCreditAmounts($account, $startDate);

        return [
            'debit_balance_before' => $debitBalanceBefore,
            'credit_balance_before' => $creditBalanceBefore,
            'starting_balance' => $this->calculateNetMovementByCategory($account->category, $debitBalanceBefore, $creditBalanceBefore),
        ];
    }

    public function getBalances(Account $account, string $startDate, string $endDate, array $fields): array
    {
        $balances = [];
        $calculatedBalances = $this->calculateBalances($account, $startDate, $endDate);

        // Calculate starting balances only if needed
        $startingBalances = null;
        $needStartingBalances = ! in_array($account->category, [AccountCategory::Expense, AccountCategory::Revenue], true)
                                && (in_array('starting_balance', $fields) || in_array('ending_balance', $fields));

        if ($needStartingBalances) {
            $startingBalances = $this->calculateStartingBalances($account, $startDate);
        }

        foreach ($fields as $field) {
            $balances[$field] = match ($field) {
                'debit_balance', 'credit_balance', 'net_movement' => $calculatedBalances[$field],
                'starting_balance' => $needStartingBalances ? $startingBalances['starting_balance'] : null,
                'ending_balance' => $needStartingBalances ? $startingBalances['starting_balance'] + $calculatedBalances['net_movement'] : null,
                default => null,
            };
        }

        return array_filter($balances, static fn ($value) => $value !== null);
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
