<?php

namespace App\Services;

use App\DTO\AccountBalanceDTO;
use App\DTO\AccountCategoryDTO;
use App\DTO\AccountDTO;
use App\DTO\AccountTransactionDTO;
use App\DTO\ReportDTO;
use App\Enums\Accounting\AccountCategory;
use App\Models\Accounting\Account;
use App\Support\Column;
use App\Utilities\Currency\CurrencyAccessor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

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
            ->select(['id', 'name', 'currency_code', 'category', 'code'])
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

    public function buildAccountTransactionsReport(string $startDate, string $endDate, ?array $columns = null, ?string $accountId = 'all'): ReportDTO
    {
        $columns ??= [];
        $query = Account::whereHas('journalEntries.transaction', function (Builder $query) use ($startDate, $endDate) {
            $query->whereBetween('posted_at', [$startDate, $endDate]);
        })
            ->with(['journalEntries' => function (Relation $query) use ($startDate, $endDate) {
                $query->whereHas('transaction', function (Builder $query) use ($startDate, $endDate) {
                    $query->whereBetween('posted_at', [$startDate, $endDate]);
                })
                    ->with('transaction:id,type,description,posted_at')
                    ->select(['account_id', 'transaction_id'])
                    ->selectRaw('SUM(CASE WHEN type = "debit" THEN amount ELSE 0 END) AS total_debit')
                    ->selectRaw('SUM(CASE WHEN type = "credit" THEN amount ELSE 0 END) AS total_credit')
                    ->selectRaw('(SELECT MIN(posted_at) FROM transactions WHERE transactions.id = journal_entries.transaction_id) AS earliest_posted_at')
                    ->groupBy('account_id', 'transaction_id')
                    ->orderBy('earliest_posted_at');
            }])
            ->select(['id', 'name', 'category', 'subtype_id', 'currency_code']);

        if ($accountId !== 'all') {
            $query->where('id', $accountId);
        }

        $accounts = $query->get();

        $reportCategories = [];

        $defaultCurrency = CurrencyAccessor::getDefaultCurrency();

        foreach ($accounts as $account) {
            $accountTransactions = [];
            $startingBalance = $this->accountService->getStartingBalance($account, $startDate, true);

            $currentBalance = $startingBalance?->getAmount() ?? 0;
            $totalDebit = 0;
            $totalCredit = 0;

            $accountTransactions[] = new AccountTransactionDTO(
                id: null,
                date: 'Starting Balance',
                description: '',
                debit: '',
                credit: '',
                balance: $startingBalance?->formatInDefaultCurrency() ?? 0,
                type: null,
                tableAction: null,
            );

            foreach ($account->journalEntries as $journalEntry) {
                $transaction = $journalEntry->transaction;
                $totalDebit += $journalEntry->total_debit;
                $totalCredit += $journalEntry->total_credit;

                $currentBalance += $journalEntry->total_debit;
                $currentBalance -= $journalEntry->total_credit;

                $accountTransactions[] = new AccountTransactionDTO(
                    id: $transaction->id,
                    date: $transaction->posted_at->format('Y-m-d'),
                    description: $transaction->description ?? '',
                    debit: $journalEntry->total_debit ? money($journalEntry->total_debit, $defaultCurrency)->format() : '',
                    credit: $journalEntry->total_credit ? money($journalEntry->total_credit, $defaultCurrency)->format() : '',
                    balance: money($currentBalance, $defaultCurrency)->format(),
                    type: $transaction->type,
                    tableAction: $transaction->type->isJournal() ? 'updateJournalTransaction' : 'updateTransaction',
                );
            }

            $balanceChange = $currentBalance - ($startingBalance?->getAmount() ?? 0);

            $accountTransactions[] = new AccountTransactionDTO(
                id: null,
                date: 'Totals and Ending Balance',
                description: '',
                debit: money($totalDebit, $defaultCurrency)->format(),
                credit: money($totalCredit, $defaultCurrency)->format(),
                balance: money($currentBalance, $defaultCurrency)->format(),
                type: null,
                tableAction: null,
            );

            $accountTransactions[] = new AccountTransactionDTO(
                id: null,
                date: 'Balance Change',
                description: '',
                debit: '',
                credit: '',
                balance: money($balanceChange, $defaultCurrency)->format(),
                type: null,
                tableAction: null,
            );

            $reportCategories[] = [
                'category' => $account->name,
                'under' => $account->category->getLabel() . ' > ' . $account->subtype->name,
                'transactions' => $accountTransactions,
            ];
        }

        return new ReportDTO(categories: $reportCategories, fields: $columns);
    }

    private function buildReport(array $allCategories, Collection $categoryGroupedAccounts, callable $balanceCalculator, array $balanceFields, array $allFields, ?callable $initializeCategoryBalances = null, bool $includeRetainedEarnings = false, ?string $startDate = null): ReportDTO
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
                    $account->id,
                    $formattedAccountBalances,
                );
            }

            if ($includeRetainedEarnings && $categoryName === AccountCategory::Equity->getPluralLabel()) {
                $retainedEarnings = $this->accountService->getRetainedEarnings($startDate);
                $retainedEarningsAmount = $retainedEarnings->getAmount();

                if ($retainedEarningsAmount >= 0) {
                    $categorySummaryBalances['credit_balance'] += $retainedEarningsAmount;
                    $categoryAccounts[] = new AccountDTO(
                        'Retained Earnings',
                        'RE',
                        null,
                        $this->formatBalances(['debit_balance' => 0, 'credit_balance' => $retainedEarningsAmount])
                    );
                } else {
                    $categorySummaryBalances['debit_balance'] += abs($retainedEarningsAmount);
                    $categoryAccounts[] = new AccountDTO(
                        'Retained Earnings',
                        'RE',
                        null,
                        $this->formatBalances(['debit_balance' => abs($retainedEarningsAmount), 'credit_balance' => 0])
                    );
                }
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
        }, $balanceFields, $columns, null, true, $startDate);
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
