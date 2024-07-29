<?php

namespace App\Services;

use App\Contracts\AccountHandler;
use App\Enums\Accounting\AccountCategory;
use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\Transaction;
use App\Repositories\Accounting\JournalEntryRepository;
use App\Utilities\Currency\CurrencyAccessor;
use App\ValueObjects\Money;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

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

    public function getStartingBalance(Account $account, string $startDate, bool $override = false): ?Money
    {
        if ($override === false && in_array($account->category, [AccountCategory::Expense, AccountCategory::Revenue], true)) {
            return null;
        }

        $balances = $this->calculateStartingBalances($account, $startDate);

        return new Money($balances['starting_balance'], $account->currency_code);
    }

    public function getStartingBalanceSubquery($startDate, $accountIds = null): Builder
    {
        return DB::table('journal_entries')
            ->select('journal_entries.account_id')
            ->selectRaw('
                SUM(
                    CASE
                        WHEN accounts.category IN ("asset", "expense") THEN
                            CASE WHEN journal_entries.type = "debit" THEN journal_entries.amount ELSE -journal_entries.amount END
                        ELSE
                            CASE WHEN journal_entries.type = "credit" THEN journal_entries.amount ELSE -journal_entries.amount END
                    END
                ) AS starting_balance
            ')
            ->join('transactions', 'transactions.id', '=', 'journal_entries.transaction_id')
            ->join('accounts', 'accounts.id', '=', 'journal_entries.account_id')
            ->where('transactions.posted_at', '<', $startDate)
            ->groupBy('journal_entries.account_id');
    }

    public function getTotalDebitSubquery($startDate, $endDate, $accountIds = null): Builder
    {
        return DB::table('journal_entries')
            ->select('journal_entries.account_id')
            ->selectRaw('
                SUM(CASE WHEN journal_entries.type = "debit" THEN journal_entries.amount ELSE 0 END) as total_debit
            ')
            ->join('transactions', 'transactions.id', '=', 'journal_entries.transaction_id')
            ->whereBetween('transactions.posted_at', [$startDate, $endDate])
            ->groupBy('journal_entries.account_id');
    }

    public function getTotalCreditSubquery($startDate, $endDate, $accountIds = null): Builder
    {
        return DB::table('journal_entries')
            ->select('journal_entries.account_id')
            ->selectRaw('
                SUM(CASE WHEN journal_entries.type = "credit" THEN journal_entries.amount ELSE 0 END) as total_credit
            ')
            ->join('transactions', 'transactions.id', '=', 'journal_entries.transaction_id')
            ->whereBetween('transactions.posted_at', [$startDate, $endDate])
            ->groupBy('journal_entries.account_id');
    }

    public function getTransactionDetailsSubquery($startDate, $endDate): Closure
    {
        return function ($query) use ($startDate, $endDate) {
            $query->select(
                'journal_entries.id',
                'journal_entries.account_id',
                'journal_entries.transaction_id',
                'journal_entries.type',
                'journal_entries.amount',
                DB::raw('journal_entries.amount * IF(journal_entries.type = "debit", 1, -1) AS signed_amount')
            )
                ->whereBetween('transactions.posted_at', [$startDate, $endDate])
                ->join('transactions', 'transactions.id', '=', 'journal_entries.transaction_id')
                ->orderBy('transactions.posted_at')
                ->with('transaction:id,type,description,posted_at');
        };
    }

    public function getAccountBalances($startDate, $endDate, $accountIds = null): \Illuminate\Database\Eloquent\Builder
    {
        $query = Account::query()
            ->select([
                'accounts.id',
                'accounts.name',
                'accounts.category',
                'accounts.subtype_id',
                'accounts.currency_code',
                'accounts.code',
            ])
            ->leftJoinSub($this->getStartingBalanceSubquery($startDate), 'starting_balance', function ($join) {
                $join->on('accounts.id', '=', 'starting_balance.account_id');
            })
            ->leftJoinSub($this->getTotalDebitSubquery($startDate, $endDate), 'total_debit', function ($join) {
                $join->on('accounts.id', '=', 'total_debit.account_id');
            })
            ->leftJoinSub($this->getTotalCreditSubquery($startDate, $endDate), 'total_credit', function ($join) {
                $join->on('accounts.id', '=', 'total_credit.account_id');
            })
            ->addSelect([
                'starting_balance.starting_balance',
                'total_debit.total_debit',
                'total_credit.total_credit',
            ])
            ->with(['subtype:id,name'])
            ->whereHas('journalEntries.transaction', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('posted_at', [$startDate, $endDate]);
            });

        if ($accountIds !== null) {
            $query->whereIn('accounts.id', (array) $accountIds);
        }

        return $query;
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

    public function getRetainedEarnings(string $startDate): Money
    {
        $revenue = JournalEntry::whereHas('account', static function ($query) {
            $query->where('category', AccountCategory::Revenue);
        })
            ->where('type', 'credit')
            ->whereHas('transaction', static function ($query) use ($startDate) {
                $query->where('posted_at', '<=', $startDate);
            })
            ->sum('amount');

        $expense = JournalEntry::whereHas('account', static function ($query) {
            $query->where('category', AccountCategory::Expense);
        })
            ->where('type', 'debit')
            ->whereHas('transaction', static function ($query) use ($startDate) {
                $query->where('posted_at', '<=', $startDate);
            })
            ->sum('amount');

        $retainedEarnings = $revenue - $expense;

        return new Money($retainedEarnings, CurrencyAccessor::getDefaultCurrency());
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
        $accountIds = Account::whereHas('bankAccount')
            ->pluck('id')
            ->toArray();

        if (empty($accountIds)) {
            return new Money(0, CurrencyAccessor::getDefaultCurrency());
        }

        $result = DB::table('journal_entries')
            ->join('transactions', 'journal_entries.transaction_id', '=', 'transactions.id')
            ->whereIn('journal_entries.account_id', $accountIds)
            ->where('transactions.posted_at', '<=', $endDate)
            ->selectRaw('
            SUM(CASE
                WHEN transactions.posted_at <= ? AND journal_entries.type = "debit" THEN journal_entries.amount
                WHEN transactions.posted_at <= ? AND journal_entries.type = "credit" THEN -journal_entries.amount
                ELSE 0
            END) AS totalStartingBalance,
            SUM(CASE
                WHEN transactions.posted_at BETWEEN ? AND ? AND journal_entries.type = "debit" THEN journal_entries.amount
                WHEN transactions.posted_at BETWEEN ? AND ? AND journal_entries.type = "credit" THEN -journal_entries.amount
                ELSE 0
            END) AS totalNetMovement
        ', [
                $startDate,
                $startDate,
                $startDate,
                $endDate,
                $startDate,
                $endDate,
            ])
            ->first();

        $totalBalance = $result->totalStartingBalance + $result->totalNetMovement;

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
