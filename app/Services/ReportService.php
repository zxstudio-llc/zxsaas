<?php

namespace App\Services;

use App\DTO\AccountBalanceDTO;
use App\DTO\AccountCategoryDTO;
use App\DTO\AccountDTO;
use App\DTO\AccountTransactionDTO;
use App\DTO\AccountTypeDTO;
use App\DTO\ReportDTO;
use App\Enums\Accounting\AccountCategory;
use App\Models\Accounting\Account;
use App\Support\Column;
use App\Utilities\Currency\CurrencyAccessor;
use App\ValueObjects\Money;
use Illuminate\Database\Eloquent\Builder;
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
                    Carbon::parse($startDate)->toDateString(),
                    Carbon::parse($endDate)->toDateString(),
                );
            }

            foreach ($relevantFields as $field) {
                $reportTotalBalances[$field] = ($reportTotalBalances[$field] ?? 0) + $categorySummaryBalances[$field];
            }

            $formattedCategorySummaryBalances = $this->formatBalances($categorySummaryBalances);

            $accountCategories[$category->getPluralLabel()] = new AccountCategoryDTO(
                accounts: $categoryAccounts,
                summary: $formattedCategorySummaryBalances,
            );
        }

        $formattedReportTotalBalances = $this->formatBalances($reportTotalBalances);

        return new ReportDTO($accountCategories, $formattedReportTotalBalances, $columns);
    }

    public function calculateAccountBalances(Account $account, AccountCategory $category): array
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

    public function calculateRetainedEarnings(?string $startDate, string $endDate): Money
    {
        $startDate ??= Carbon::parse($this->accountService->getEarliestTransactionDate())->toDateTimeString();
        $revenueAccounts = $this->accountService->getAccountBalances($startDate, $endDate)->where('category', AccountCategory::Revenue)->get();

        $expenseAccounts = $this->accountService->getAccountBalances($startDate, $endDate)->where('category', AccountCategory::Expense)->get();

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

    public function buildTrialBalanceReport(string $trialBalanceType, string $asOfDate, array $columns = []): ReportDTO
    {
        $asOfDateCarbon = Carbon::parse($asOfDate);
        $startDateCarbon = Carbon::parse($this->accountService->getEarliestTransactionDate());

        $orderedCategories = AccountCategory::getOrderedCategories();

        $isPostClosingTrialBalance = $trialBalanceType === 'postClosing';

        $accounts = $this->accountService->getAccountBalances($startDateCarbon->toDateTimeString(), $asOfDateCarbon->toDateTimeString())
            ->when($isPostClosingTrialBalance, fn (Builder $query) => $query->whereNotIn('category', [AccountCategory::Revenue, AccountCategory::Expense]))
            ->get();

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

                $trialBalance = $this->calculateTrialBalances($account->category, $endingBalance);

                foreach ($trialBalance as $balanceType => $balance) {
                    $categorySummaryBalances[$balanceType] += $balance;
                }

                $formattedAccountBalances = $this->formatBalances($trialBalance);

                $categoryAccounts[] = new AccountDTO(
                    $account->name,
                    $account->code,
                    $account->id,
                    $formattedAccountBalances,
                    startDate: $startDateCarbon->toDateString(),
                    endDate: $asOfDateCarbon->toDateString(),
                );
            }

            if ($category === AccountCategory::Equity && $isPostClosingTrialBalance) {
                $retainedEarningsAmount = $this->calculateRetainedEarnings($startDateCarbon->toDateTimeString(), $asOfDateCarbon->toDateTimeString())->getAmount();
                $isCredit = $retainedEarningsAmount >= 0;

                $categorySummaryBalances[$isCredit ? 'credit_balance' : 'debit_balance'] += abs($retainedEarningsAmount);

                $categoryAccounts[] = new AccountDTO(
                    'Retained Earnings',
                    'RE',
                    null,
                    $this->formatBalances([
                        'debit_balance' => $isCredit ? 0 : abs($retainedEarningsAmount),
                        'credit_balance' => $isCredit ? $retainedEarningsAmount : 0,
                    ]),
                    startDate: $startDateCarbon->toDateString(),
                    endDate: $asOfDateCarbon->toDateString(),
                );
            }

            foreach ($categorySummaryBalances as $balanceType => $balance) {
                $reportTotalBalances[$balanceType] += $balance;
            }

            $formattedCategorySummaryBalances = $this->formatBalances($categorySummaryBalances);

            $accountCategories[$category->getPluralLabel()] = new AccountCategoryDTO(
                accounts: $categoryAccounts,
                summary: $formattedCategorySummaryBalances,
            );
        }

        $formattedReportTotalBalances = $this->formatBalances($reportTotalBalances);

        return new ReportDTO($accountCategories, $formattedReportTotalBalances, $columns, $trialBalanceType);
    }

    public function getRetainedEarningsBalances(string $startDate, string $endDate): AccountBalanceDTO
    {
        $retainedEarningsAmount = $this->calculateRetainedEarnings($startDate, $endDate)->getAmount();

        $isCredit = $retainedEarningsAmount >= 0;
        $retainedEarningsDebitAmount = $isCredit ? 0 : abs($retainedEarningsAmount);
        $retainedEarningsCreditAmount = $isCredit ? $retainedEarningsAmount : 0;

        return $this->formatBalances([
            'debit_balance' => $retainedEarningsDebitAmount,
            'credit_balance' => $retainedEarningsCreditAmount,
        ]);
    }

    public function calculateTrialBalances(AccountCategory $category, int $endingBalance): array
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
        // Query only relevant accounts and sort them at the query level
        $revenueAccounts = $this->accountService->getAccountBalances($startDate, $endDate)
            ->where('category', AccountCategory::Revenue)
            ->orderByRaw('LENGTH(code), code')
            ->get();

        $cogsAccounts = $this->accountService->getAccountBalances($startDate, $endDate)
            ->whereRelation('subtype', 'name', 'Cost of Goods Sold')
            ->orderByRaw('LENGTH(code), code')
            ->get();

        $expenseAccounts = $this->accountService->getAccountBalances($startDate, $endDate)
            ->where('category', AccountCategory::Expense)
            ->whereRelation('subtype', 'name', '!=', 'Cost of Goods Sold')
            ->orderByRaw('LENGTH(code), code')
            ->get();

        $accountCategories = [];
        $totalRevenue = 0;
        $totalCogs = 0;
        $totalExpenses = 0;

        // Define category groups
        $categoryGroups = [
            AccountCategory::Revenue->getPluralLabel() => [
                'accounts' => $revenueAccounts,
                'total' => &$totalRevenue,
            ],
            'Cost of Goods Sold' => [
                'accounts' => $cogsAccounts,
                'total' => &$totalCogs,
            ],
            AccountCategory::Expense->getPluralLabel() => [
                'accounts' => $expenseAccounts,
                'total' => &$totalExpenses,
            ],
        ];

        // Process each category group
        foreach ($categoryGroups as $label => $group) {
            $categoryAccounts = [];
            $netMovement = 0;

            foreach ($group['accounts'] as $account) {
                // Use the category type based on label
                $category = match ($label) {
                    AccountCategory::Revenue->getPluralLabel() => AccountCategory::Revenue,
                    AccountCategory::Expense->getPluralLabel(), 'Cost of Goods Sold' => AccountCategory::Expense,
                    default => null
                };

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
                        Carbon::parse($startDate)->toDateString(),
                        Carbon::parse($endDate)->toDateString(),
                    );
                }
            }

            $accountCategories[$label] = new AccountCategoryDTO(
                accounts: $categoryAccounts,
                summary: $this->formatBalances(['net_movement' => $netMovement])
            );
        }

        // Calculate gross and net profit
        $grossProfit = $totalRevenue - $totalCogs;
        $netProfit = $grossProfit - $totalExpenses;
        $formattedReportTotalBalances = $this->formatBalances(['net_movement' => $netProfit]);

        return new ReportDTO($accountCategories, $formattedReportTotalBalances, $columns);
    }

    public function buildBalanceSheetReport(string $asOfDate, array $columns = []): ReportDTO
    {
        $asOfDateCarbon = Carbon::parse($asOfDate);
        $startDateCarbon = Carbon::parse($this->accountService->getEarliestTransactionDate());

        $orderedCategories = AccountCategory::getOrderedCategories();

        // Filter out non-real categories like Revenue and Expense
        $orderedCategories = array_filter($orderedCategories, fn (AccountCategory $category) => $category->isReal());

        // Fetch account balances
        $accounts = $this->accountService->getAccountBalances($startDateCarbon->toDateTimeString(), $asOfDateCarbon->toDateTimeString())
            ->get();

        $accountCategories = [];
        $reportTotalBalances = [
            'assets' => 0,
            'liabilities' => 0,
            'equity' => 0,
        ];

        foreach ($orderedCategories as $category) {
            $categorySummaryBalances = ['ending_balance' => 0];

            // Group the accounts by their type within the current category
            $categoryAccountsByType = [];
            $subCategoryTotals = [];

            /** @var Account $account */
            foreach ($accounts as $account) {
                // Ensure that the account type's category matches the current loop category
                if ($account->type->getCategory() === $category) {
                    $accountBalances = $this->calculateAccountBalances($account, $category);
                    $endingBalance = $accountBalances['ending_balance'] ?? $accountBalances['net_movement'];

                    $categorySummaryBalances['ending_balance'] += $endingBalance;

                    $formattedAccountBalances = $this->formatBalances($accountBalances);

                    // Create a DTO for each account
                    $accountDTO = new AccountDTO(
                        $account->name,
                        $account->code,
                        $account->id,
                        $formattedAccountBalances,
                        startDate: $startDateCarbon->toDateString(),
                        endDate: $asOfDateCarbon->toDateString(),
                    );

                    // Group by account type label and accumulate subcategory totals
                    $accountType = $account->type->getPluralLabel();
                    $categoryAccountsByType[$accountType][] = $accountDTO;

                    // Track totals for the subcategory (not formatted)
                    $subCategoryTotals[$accountType] = ($subCategoryTotals[$accountType] ?? 0) + $endingBalance;
                }
            }

            // If the category is Equity, include Retained Earnings
            if ($category === AccountCategory::Equity) {
                $retainedEarningsAmount = $this->calculateRetainedEarnings($startDateCarbon->toDateTimeString(), $asOfDateCarbon->toDateTimeString())->getAmount();

                $categorySummaryBalances['ending_balance'] += $retainedEarningsAmount;

                $accountDTO = new AccountDTO(
                    'Retained Earnings',
                    'RE',
                    null,
                    $this->formatBalances(['ending_balance' => $retainedEarningsAmount]),
                    startDate: $startDateCarbon->toDateString(),
                    endDate: $asOfDateCarbon->toDateString(),
                );

                // Add Retained Earnings to the Equity type
                $categoryAccountsByType['Equity'][] = $accountDTO;

                // Add to subcategory total as well
                $subCategoryTotals['Equity'] = ($subCategoryTotals['Equity'] ?? 0) + $retainedEarningsAmount;
            }

            // Create SubCategory DTOs for each account type within the category
            $subCategories = [];
            foreach ($categoryAccountsByType as $accountType => $accountsInType) {
                $subCategorySummary = $this->formatBalances([
                    'ending_balance' => $subCategoryTotals[$accountType] ?? 0,
                ]);

                $subCategories[$accountType] = new AccountTypeDTO(
                    accounts: $accountsInType,
                    summary: $subCategorySummary
                );
            }

            // Add category totals to the overall totals
            if ($category === AccountCategory::Asset) {
                $reportTotalBalances['assets'] += $categorySummaryBalances['ending_balance'];
            } elseif ($category === AccountCategory::Liability) {
                $reportTotalBalances['liabilities'] += $categorySummaryBalances['ending_balance'];
            } elseif ($category === AccountCategory::Equity) {
                $reportTotalBalances['equity'] += $categorySummaryBalances['ending_balance'];
            }

            // Store the subcategories and the summary in the accountCategories array
            $accountCategories[$category->getPluralLabel()] = new AccountCategoryDTO(
                types: $subCategories,
                summary: $this->formatBalances($categorySummaryBalances),
            );
        }

        // Calculate Net Assets (Assets - Liabilities)
        $netAssets = $reportTotalBalances['assets'] - $reportTotalBalances['liabilities'];

        // Format the overall totals for the report
        $formattedReportTotalBalances = $this->formatBalances(['ending_balance' => $netAssets]);

        // Return the constructed ReportDTO
        return new ReportDTO($accountCategories, $formattedReportTotalBalances, $columns);
    }
}
