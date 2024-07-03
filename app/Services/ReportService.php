<?php

namespace App\Services;

use App\DTO\AccountBalanceDTO;
use App\DTO\AccountCategoryDTO;
use App\DTO\AccountDTO;
use App\DTO\AccountTransactionDTO;
use App\DTO\ReportDTO;
use App\Enums\Accounting\AccountCategory;
use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Support\Column;
use App\Utilities\Currency\CurrencyAccessor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ReportService
{
    public function __construct(
        protected AccountService $accountService,
    ) {}

    public function formatBalances(array $balances): AccountBalanceDTO
    {
        $defaultCurrency = CurrencyAccessor::getDefaultCurrency();

        foreach ($balances as $key => $balance) {
            $balances[$key] = money($balance, $defaultCurrency)->format();
        }

        return new AccountBalanceDTO(
            startingBalance: $balances['starting_balance'] ?? null,
            debitBalance: $balances['debit_balance'] ?? null,
            creditBalance: $balances['credit_balance'] ?? null,
            netMovement: $balances['net_movement'] ?? null,
            endingBalance: $balances['ending_balance'] ?? null,
        );
    }

    private function filterBalances(array $balances, array $fields): array
    {
        return array_filter($balances, static fn ($key) => in_array($key, $fields, true), ARRAY_FILTER_USE_KEY);
    }

    private function getCategoryGroupedAccounts(array $allCategories): Collection
    {
        return Account::whereHas('journalEntries')
            ->select('id', 'name', 'currency_code', 'category', 'code')
            ->get()
            ->groupBy(fn (Account $account) => $account->category->getPluralLabel())
            ->sortBy(static fn (Collection $groupedAccounts, string $key) => array_search($key, $allCategories, true));
    }

    public function buildAccountBalanceReport(string $startDate, string $endDate, array $columns = []): ReportDTO
    {
        $allCategories = $this->accountService->getAccountCategoryOrder();

        $categoryGroupedAccounts = $this->getCategoryGroupedAccounts($allCategories);

        $balanceFields = ['starting_balance', 'debit_balance', 'credit_balance', 'net_movement', 'ending_balance'];

        $columnNameKeys = array_map(fn (Column $column) => $column->getName(), $columns);

        $updatedBalanceFields = array_filter($balanceFields, fn (string $balanceField) => in_array($balanceField, $columnNameKeys, true));

        return $this->buildReport(
            $allCategories,
            $categoryGroupedAccounts,
            fn (Account $account) => $this->accountService->getBalances($account, $startDate, $endDate, $updatedBalanceFields),
            $updatedBalanceFields,
            $columns,
            fn (string $categoryName, array &$categorySummaryBalances) => $this->adjustAccountBalanceCategoryFields($categoryName, $categorySummaryBalances),
        );
    }

    public function buildTrialBalanceReport(string $startDate, string $endDate, array $columns = []): ReportDTO
    {
        $allCategories = $this->accountService->getAccountCategoryOrder();

        $categoryGroupedAccounts = $this->getCategoryGroupedAccounts($allCategories);

        $balanceFields = ['debit_balance', 'credit_balance'];

        return $this->buildReport($allCategories, $categoryGroupedAccounts, function (Account $account) use ($startDate, $endDate) {
            $endingBalance = $this->accountService->getEndingBalance($account, $startDate, $endDate)?->getAmount() ?? 0;

            if ($endingBalance === 0) {
                return [];
            }

            return $this->calculateTrialBalance($account->category, $endingBalance);
        }, $balanceFields, $columns);
    }

    public function buildAccountTransactionsReport(string $startDate, string $endDate, array $columns = []): ReportDTO
    {
        $accounts = Account::whereHas('journalEntries.transaction', static function (Builder $query) use ($startDate, $endDate) {
            $query->whereBetween('posted_at', [$startDate, $endDate]);
        })
            ->with(['journalEntries' => static function ($query) use ($startDate, $endDate) {
                $query->whereHas('transaction', static function (Builder $query) use ($startDate, $endDate) {
                    $query->whereBetween('posted_at', [$startDate, $endDate]);
                })
                    ->with(['transaction' => static function ($query) {
                        $query->select('id', 'posted_at', 'description');
                    }])
                    ->select('id', 'account_id', 'transaction_id', 'type', 'amount');
            }])
            ->select(['id', 'name', 'category', 'subtype_id', 'currency_code'])
            ->get()
            ->lazy();

        $reportCategories = [];

        foreach ($accounts as $account) {
            $accountTransactions = [];
            $startingBalance = $this->accountService->getStartingBalance($account, $startDate, true);

            $currentBalance = $startingBalance?->getAmount() ?? 0;
            $totalDebit = 0;
            $totalCredit = 0;

            $accountTransactions[] = new AccountTransactionDTO(
                date: 'Starting Balance',
                description: '',
                debit: '',
                credit: '',
                balance: $startingBalance?->format() ?? 0,
            );

            $journalEntriesGroupedByTransaction = $account->journalEntries->groupBy('transaction_id');

            foreach ($journalEntriesGroupedByTransaction as $transactionId => $journalEntries) {
                $transaction = $journalEntries->first()->transaction;

                $debitAmount = $journalEntries->sumDebits()->getAmount();
                $creditAmount = $journalEntries->sumCredits()->getAmount();

                // Adjust balance
                $currentBalance += $debitAmount;
                $currentBalance -= $creditAmount;

                $totalDebit += $debitAmount;
                $totalCredit += $creditAmount;

                $accountTransactions[] = new AccountTransactionDTO(
                    date: $transaction->posted_at->format('Y-m-d'),
                    description: $transaction->description,
                    debit: $debitAmount ? money($debitAmount, $account->currency_code)->format() : '',
                    credit: $creditAmount ? money($creditAmount, $account->currency_code)->format() : '',
                    balance: money($currentBalance, $account->currency_code)->format(),
                );
            }

            $balanceChange = $currentBalance - ($startingBalance?->getAmount() ?? 0);

            $accountTransactions[] = new AccountTransactionDTO(
                date: 'Totals and Ending Balance',
                description: '',
                debit: money($totalDebit, $account->currency_code)->format(),
                credit: money($totalCredit, $account->currency_code)->format(),
                balance: money($currentBalance, $account->currency_code)->format(),
            );

            $accountTransactions[] = new AccountTransactionDTO(
                date: 'Balance Change',
                description: '',
                debit: '',
                credit: '',
                balance: money($balanceChange, $account->currency_code)->format(),
            );

            $reportCategories[] = [
                'category' => $account->name,
                'under' => 'Under: ' . $account->category->getLabel() . ' > ' . $account->subtype->name,
                'transactions' => $accountTransactions,
            ];
        }

        return new ReportDTO(categories: $reportCategories, fields: $columns);
    }

    private function buildReport(array $allCategories, Collection $categoryGroupedAccounts, callable $balanceCalculator, array $balanceFields, array $allFields, ?callable $initializeCategoryBalances = null): ReportDTO
    {
        $accountCategories = [];
        $reportTotalBalances = array_fill_keys($balanceFields, 0);

        foreach ($allCategories as $categoryName) {
            $accountsInCategory = $categoryGroupedAccounts[$categoryName] ?? collect();
            $categorySummaryBalances = array_fill_keys($balanceFields, 0);

            if ($initializeCategoryBalances) {
                $initializeCategoryBalances($categoryName, $categorySummaryBalances);
            }

            $categoryAccounts = [];

            foreach ($accountsInCategory as $account) {
                /** @var Account $account */
                $accountBalances = $balanceCalculator($account);

                if (array_sum($accountBalances) === 0) {
                    continue;
                }

                foreach ($accountBalances as $accountBalanceType => $accountBalance) {
                    if (array_key_exists($accountBalanceType, $categorySummaryBalances)) {
                        $categorySummaryBalances[$accountBalanceType] += $accountBalance;
                    }
                }

                $filteredAccountBalances = $this->filterBalances($accountBalances, $balanceFields);
                $formattedAccountBalances = $this->formatBalances($filteredAccountBalances);

                $categoryAccounts[] = new AccountDTO(
                    $account->name,
                    $account->code,
                    $formattedAccountBalances,
                );
            }

            foreach ($balanceFields as $field) {
                if (array_key_exists($field, $categorySummaryBalances)) {
                    $reportTotalBalances[$field] += $categorySummaryBalances[$field];
                }
            }

            $filteredCategorySummaryBalances = $this->filterBalances($categorySummaryBalances, $balanceFields);
            $formattedCategorySummaryBalances = $this->formatBalances($filteredCategorySummaryBalances);

            $accountCategories[$categoryName] = new AccountCategoryDTO(
                $categoryAccounts,
                $formattedCategorySummaryBalances,
            );
        }

        $formattedReportTotalBalances = $this->formatBalances($reportTotalBalances);

        return new ReportDTO($accountCategories, $formattedReportTotalBalances, $allFields);
    }

    private function adjustAccountBalanceCategoryFields(string $categoryName, array &$categorySummaryBalances): void
    {
        if (in_array($categoryName, [AccountCategory::Expense->getPluralLabel(), AccountCategory::Revenue->getPluralLabel()], true)) {
            unset($categorySummaryBalances['starting_balance'], $categorySummaryBalances['ending_balance']);
        }
    }

    private function calculateTrialBalance(AccountCategory $category, int $endingBalance): array
    {
        if (in_array($category, [AccountCategory::Asset, AccountCategory::Expense], true)) {
            if ($endingBalance >= 0) {
                return ['debit_balance' => $endingBalance, 'credit_balance' => 0];
            }

            return ['debit_balance' => 0, 'credit_balance' => abs($endingBalance)];
        }

        if ($endingBalance >= 0) {
            return ['debit_balance' => 0, 'credit_balance' => $endingBalance];
        }

        return ['debit_balance' => abs($endingBalance), 'credit_balance' => 0];
    }
}
