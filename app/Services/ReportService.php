<?php

namespace App\Services;

use App\DTO\AccountBalanceDTO;
use App\DTO\AccountCategoryDTO;
use App\DTO\AccountDTO;
use App\DTO\AccountTransactionDTO;
use App\DTO\ReportDTO;
use App\Enums\Accounting\AccountCategory;
use App\Enums\Accounting\JournalEntryType;
use App\Models\Accounting\Account;
use App\Support\Column;
use App\Utilities\Currency\CurrencyAccessor;
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

        $accountIds = Account::whereHas('journalEntries')->pluck('id')->toArray();

        $accounts = $this->accountService->getAccountBalances($startDate, $endDate, $accountIds)->get();

        $balanceFields = ['starting_balance', 'debit_balance', 'credit_balance', 'net_movement', 'ending_balance'];

        $columnNameKeys = array_map(fn (Column $column) => $column->getName(), $columns);

        $updatedBalanceFields = array_filter($balanceFields, fn (string $balanceField) => in_array($balanceField, $columnNameKeys, true));

        $accountCategories = [];
        $reportTotalBalances = array_fill_keys($updatedBalanceFields, 0);

        foreach ($allCategories as $categoryPluralName) {
            $categoryName = AccountCategory::fromPluralLabel($categoryPluralName);
            $accountsInCategory = $accounts->where('category', $categoryName)->keyBy('id');
            $categorySummaryBalances = array_fill_keys($updatedBalanceFields, 0);

            $categoryAccounts = [];

            foreach ($accountsInCategory as $account) {
                $accountBalances = $this->calculateAccountBalances($account, $categoryName);

                if ($this->isZeroBalance($accountBalances)) {
                    continue;
                }

                foreach ($accountBalances as $accountBalanceType => $accountBalance) {
                    if (array_key_exists($accountBalanceType, $categorySummaryBalances)) {
                        $categorySummaryBalances[$accountBalanceType] += $accountBalance;
                    }
                }

                $filteredAccountBalances = $this->filterBalances($accountBalances, $updatedBalanceFields);
                $formattedAccountBalances = $this->formatBalances($filteredAccountBalances);

                $categoryAccounts[] = new AccountDTO(
                    $account->name,
                    $account->code,
                    $account->id,
                    $formattedAccountBalances,
                );
            }

            $this->adjustAccountBalanceCategoryFields($categoryName, $categorySummaryBalances);

            foreach ($updatedBalanceFields as $field) {
                if (array_key_exists($field, $categorySummaryBalances)) {
                    $reportTotalBalances[$field] += $categorySummaryBalances[$field];
                }
            }

            $filteredCategorySummaryBalances = $this->filterBalances($categorySummaryBalances, $updatedBalanceFields);
            $formattedCategorySummaryBalances = $this->formatBalances($filteredCategorySummaryBalances);

            $accountCategories[$categoryPluralName] = new AccountCategoryDTO(
                $categoryAccounts,
                $formattedCategorySummaryBalances,
            );
        }

        $formattedReportTotalBalances = $this->formatBalances($reportTotalBalances);

        return new ReportDTO($accountCategories, $formattedReportTotalBalances, $columns);
    }

    private function calculateAccountBalances(Account $account, AccountCategory $category): array
    {
        $balances = [
            'debit_balance' => $account->total_debit,
            'credit_balance' => $account->total_credit,
        ];

        if (in_array($category, [AccountCategory::Liability, AccountCategory::Equity, AccountCategory::Revenue])) {
            $balances['net_movement'] = $account->total_credit - $account->total_debit;
        } else {
            $balances['net_movement'] = $account->total_debit - $account->total_credit;
        }

        if (! in_array($category, [AccountCategory::Expense, AccountCategory::Revenue], true)) {
            $balances['starting_balance'] = $account->starting_balance;
            $balances['ending_balance'] = $account->starting_balance + $account->total_credit - $account->total_debit;
        }

        return $balances;
    }

    private function adjustAccountBalanceCategoryFields(AccountCategory $category, array &$categorySummaryBalances): void
    {
        if (in_array($category, [AccountCategory::Expense, AccountCategory::Revenue], true)) {
            unset($categorySummaryBalances['starting_balance'], $categorySummaryBalances['ending_balance']);
        }
    }

    private function isZeroBalance(array $balances): bool
    {
        return array_sum(array_map('abs', $balances)) === 0;
    }

    public function buildAccountTransactionsReport(string $startDate, string $endDate, ?array $columns = null, ?string $accountId = 'all'): ReportDTO
    {
        $columns ??= [];
        $defaultCurrency = CurrencyAccessor::getDefaultCurrency();

        $accountIds = $accountId !== 'all' ? [$accountId] : null;

        $query = $this->accountService->getAccountBalances($startDate, $endDate, $accountIds);

        $query->with(['journalEntries' => $this->accountService->getTransactionDetailsSubquery($startDate, $endDate)]);

        if ($accountId !== 'all') {
            $query->where('id', $accountId);
        }

        $accounts = $query->get();

        $reportCategories = [];

        foreach ($accounts as $account) {
            $accountTransactions = [];
            $currentBalance = $account->starting_balance;

            $accountTransactions[] = new AccountTransactionDTO(
                id: null,
                date: 'Starting Balance',
                description: '',
                debit: '',
                credit: '',
                balance: money($currentBalance, $defaultCurrency)->format(),
                type: null,
                tableAction: null
            );

            /** @var Account $account */
            foreach ($account->journalEntries as $journalEntry) {
                $transaction = $journalEntry->transaction;
                $signedAmount = $journalEntry->signed_amount;

                // Adjust balance based on account category
                if (in_array($account->category, [AccountCategory::Asset, AccountCategory::Expense])) {
                    $currentBalance += $signedAmount;
                } else {
                    $currentBalance -= $signedAmount;
                }

                $accountTransactions[] = new AccountTransactionDTO(
                    id: $transaction->id,
                    date: $transaction->posted_at->format('Y-m-d'),
                    description: $transaction->description ?? '',
                    debit: $journalEntry->type === JournalEntryType::Debit ? money(abs($signedAmount), $defaultCurrency)->format() : '',
                    credit: $journalEntry->type === JournalEntryType::Credit ? money(abs($signedAmount), $defaultCurrency)->format() : '',
                    balance: money($currentBalance, $defaultCurrency)->format(),
                    type: $transaction->type,
                    tableAction: $transaction->type->isJournal() ? 'updateJournalTransaction' : 'updateTransaction'
                );
            }

            $balanceChange = $currentBalance - $account->starting_balance;

            $accountTransactions[] = new AccountTransactionDTO(
                id: null,
                date: 'Totals and Ending Balance',
                description: '',
                debit: money($account->total_debit, $defaultCurrency)->format(),
                credit: money($account->total_credit, $defaultCurrency)->format(),
                balance: money($currentBalance, $defaultCurrency)->format(),
                type: null,
                tableAction: null
            );

            $accountTransactions[] = new AccountTransactionDTO(
                id: null,
                date: 'Balance Change',
                description: '',
                debit: '',
                credit: '',
                balance: money($balanceChange, $defaultCurrency)->format(),
                type: null,
                tableAction: null
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
