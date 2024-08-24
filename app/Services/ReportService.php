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
use App\ValueObjects\Money;
use Illuminate\Support\Carbon;

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

    public function buildAccountBalanceReport(string $startDate, string $endDate, array $columns = []): ReportDTO
    {
        $orderedCategories = AccountCategory::getOrderedCategories();

        $accounts = $this->accountService->getAccountBalances($startDate, $endDate)->get();

        $columnNameKeys = array_map(fn (Column $column) => $column->getName(), $columns);

        $accountCategories = [];
        $reportTotalBalances = [];

        foreach ($orderedCategories as $category) {
            $accountsInCategory = $accounts->where('category', $category)
                ->sortBy('code', SORT_NATURAL);

            $relevantFields = array_intersect($category->getRelevantBalanceFields(), $columnNameKeys);

            $categorySummaryBalances = array_fill_keys($relevantFields, 0);

            $categoryAccounts = [];

            /** @var Account $account */
            foreach ($accountsInCategory as $account) {
                $accountBalances = $this->calculateAccountBalances($account, $category);

                foreach ($relevantFields as $field) {
                    $categorySummaryBalances[$field] += $accountBalances[$field];
                }

                $formattedAccountBalances = $this->formatBalances($accountBalances);

                $categoryAccounts[] = new AccountDTO(
                    $account->name,
                    $account->code,
                    $account->id,
                    $formattedAccountBalances,
                );
            }

            foreach ($relevantFields as $field) {
                $reportTotalBalances[$field] = ($reportTotalBalances[$field] ?? 0) + $categorySummaryBalances[$field];
            }

            $formattedCategorySummaryBalances = $this->formatBalances($categorySummaryBalances);

            $accountCategories[$category->getPluralLabel()] = new AccountCategoryDTO(
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
            'debit_balance' => $account->total_debit ?? 0,
            'credit_balance' => $account->total_credit ?? 0,
        ];

        if ($category->isNormalDebitBalance()) {
            $balances['net_movement'] = $balances['debit_balance'] - $balances['credit_balance'];
        } else {
            $balances['net_movement'] = $balances['credit_balance'] - $balances['debit_balance'];
        }

        if ($category->isReal()) {
            $balances['starting_balance'] = $account->starting_balance ?? 0;
            $balances['ending_balance'] = $balances['starting_balance'] + $balances['net_movement'];
        }

        return $balances;
    }

    public function calculateRetainedEarnings(string $startDate): Money
    {
        $modifiedStartDate = Carbon::parse($this->accountService->getEarliestTransactionDate())->startOfYear()->toDateTimeString();
        $endDate = Carbon::parse($startDate)->subYear()->endOfYear()->toDateTimeString();

        $revenueAccounts = $this->accountService->getAccountBalances($modifiedStartDate, $endDate)->where('category', AccountCategory::Revenue)->get();

        $expenseAccounts = $this->accountService->getAccountBalances($modifiedStartDate, $endDate)->where('category', AccountCategory::Expense)->get();

        $revenueTotal = 0;
        $expenseTotal = 0;

        foreach ($revenueAccounts as $account) {
            $revenueBalances = $this->calculateAccountBalances($account, AccountCategory::Revenue);
            $revenueTotal += $revenueBalances['net_movement'];
        }

        foreach ($expenseAccounts as $account) {
            $expenseBalances = $this->calculateAccountBalances($account, AccountCategory::Expense);
            $expenseTotal += $expenseBalances['net_movement'];
        }

        $retainedEarnings = $revenueTotal - $expenseTotal;

        return new Money($retainedEarnings, CurrencyAccessor::getDefaultCurrency());
    }

    public function buildAccountTransactionsReport(string $startDate, string $endDate, ?array $columns = null, ?string $accountId = 'all'): ReportDTO
    {
        $columns ??= [];
        $defaultCurrency = CurrencyAccessor::getDefaultCurrency();

        $accountIds = $accountId !== 'all' ? [$accountId] : [];

        $query = $this->accountService->getAccountBalances($startDate, $endDate, $accountIds);

        $accounts = $query->with(['journalEntries' => $this->accountService->getTransactionDetailsSubquery($startDate, $endDate)])->get();

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

                if ($account->category->isNormalDebitBalance()) {
                    $currentBalance += $signedAmount;
                } else {
                    $currentBalance -= $signedAmount;
                }

                $formattedAmount = money(abs($signedAmount), $defaultCurrency)->format();

                $accountTransactions[] = new AccountTransactionDTO(
                    id: $transaction->id,
                    date: $transaction->posted_at->toDefaultDateFormat(),
                    description: $transaction->description ?? 'Add a description',
                    debit: $journalEntry->type->isDebit() ? $formattedAmount : '',
                    credit: $journalEntry->type->isCredit() ? $formattedAmount : '',
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

    public function buildTrialBalanceReport(string $startDate, string $endDate, array $columns = []): ReportDTO
    {
        $orderedCategories = AccountCategory::getOrderedCategories();

        $accounts = $this->accountService->getAccountBalances($startDate, $endDate)->get();

        $balanceFields = ['debit_balance', 'credit_balance'];

        $accountCategories = [];
        $reportTotalBalances = array_fill_keys($balanceFields, 0);

        foreach ($orderedCategories as $category) {
            $accountsInCategory = $accounts->where('category', $category)
                ->sortBy('code', SORT_NATURAL);

            $categorySummaryBalances = array_fill_keys($balanceFields, 0);

            $categoryAccounts = [];

            /** @var Account $account */
            foreach ($accountsInCategory as $account) {
                $accountBalances = $this->calculateAccountBalances($account, $category);

                $endingBalance = $accountBalances['ending_balance'] ?? $accountBalances['net_movement'];

                $trialBalance = $this->calculateTrialBalance($account->category, $endingBalance);

                foreach ($trialBalance as $balanceType => $balance) {
                    $categorySummaryBalances[$balanceType] += $balance;
                }

                $formattedAccountBalances = $this->formatBalances($trialBalance);

                $categoryAccounts[] = new AccountDTO(
                    $account->name,
                    $account->code,
                    $account->id,
                    $formattedAccountBalances,
                );
            }

            if ($category === AccountCategory::Equity) {
                $retainedEarningsAmount = $this->calculateRetainedEarnings($startDate)->getAmount();
                $isCredit = $retainedEarningsAmount >= 0;

                $categorySummaryBalances[$isCredit ? 'credit_balance' : 'debit_balance'] += abs($retainedEarningsAmount);

                $categoryAccounts[] = new AccountDTO(
                    'Retained Earnings',
                    'RE',
                    null,
                    $this->formatBalances([
                        'debit_balance' => $isCredit ? 0 : abs($retainedEarningsAmount),
                        'credit_balance' => $isCredit ? $retainedEarningsAmount : 0,
                    ])
                );
            }

            foreach ($categorySummaryBalances as $balanceType => $balance) {
                $reportTotalBalances[$balanceType] += $balance;
            }

            $formattedCategorySummaryBalances = $this->formatBalances($categorySummaryBalances);

            $accountCategories[$category->getPluralLabel()] = new AccountCategoryDTO(
                $categoryAccounts,
                $formattedCategorySummaryBalances,
            );
        }

        $formattedReportTotalBalances = $this->formatBalances($reportTotalBalances);

        return new ReportDTO($accountCategories, $formattedReportTotalBalances, $columns);
    }

    private function calculateTrialBalance(AccountCategory $category, int $endingBalance): array
    {
        if ($category->isNormalDebitBalance()) {
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

    public function buildIncomeStatementReport(string $startDate, string $endDate, array $columns = []): ReportDTO
    {
        $accounts = $this->accountService->getAccountBalances($startDate, $endDate)->get();

        $accountCategories = [];
        $totalRevenue = 0;
        $cogs = 0;
        $totalExpenses = 0;

        $categoryGroups = [
            'Revenue' => [
                'accounts' => $accounts->where('category', AccountCategory::Revenue),
                'total' => &$totalRevenue,
            ],
            'Cost of Goods Sold' => [
                'accounts' => $accounts->where('subtype.name', 'Cost of Goods Sold'),
                'total' => &$cogs,
            ],
            'Expenses' => [
                'accounts' => $accounts->where('category', AccountCategory::Expense)->where('subtype.name', '!=', 'Cost of Goods Sold'),
                'total' => &$totalExpenses,
            ],
        ];

        foreach ($categoryGroups as $label => $group) {
            $categoryAccounts = [];
            $netMovement = 0;

            foreach ($group['accounts']->sortBy('code', SORT_NATURAL) as $account) {
                $category = null;

                if ($label === 'Revenue') {
                    $category = AccountCategory::Revenue;
                } elseif ($label === 'Expenses') {
                    $category = AccountCategory::Expense;
                } elseif ($label === 'Cost of Goods Sold') {
                    // COGS is treated as part of Expenses, so we use AccountCategory::Expense
                    $category = AccountCategory::Expense;
                }

                if ($category !== null) {
                    $accountBalances = $this->calculateAccountBalances($account, $category);
                    $movement = $accountBalances['net_movement'];
                    $netMovement += $movement;
                    $group['total'] += $movement;

                    $categoryAccounts[] = new AccountDTO(
                        $account->name,
                        $account->code,
                        $account->id,
                        $this->formatBalances(['net_movement' => $movement]),
                    );
                }
            }

            $accountCategories[$label] = new AccountCategoryDTO(
                $categoryAccounts,
                $this->formatBalances(['net_movement' => $netMovement]),
            );
        }

        $grossProfit = $totalRevenue - $cogs;
        $netProfit = $grossProfit - $totalExpenses;
        $formattedReportTotalBalances = $this->formatBalances(['net_movement' => $netProfit]);

        return new ReportDTO($accountCategories, $formattedReportTotalBalances, $columns);
    }
}
