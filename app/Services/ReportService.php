<?php

namespace App\Services;

use App\DTO\AccountBalanceDTO;
use App\DTO\AccountCategoryDTO;
use App\DTO\AccountDTO;
use App\DTO\ReportDTO;
use App\Enums\Accounting\AccountCategory;
use App\Models\Accounting\Account;
use App\Support\Column;
use App\Utilities\Currency\CurrencyAccessor;
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
