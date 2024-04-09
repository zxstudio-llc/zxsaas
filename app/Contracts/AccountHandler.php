<?php

namespace App\Contracts;

use App\DTO\AccountBalanceDTO;
use App\DTO\AccountBalanceReportDTO;
use App\Enums\Accounting\AccountCategory;
use App\Models\Accounting\Account;
use App\ValueObjects\BalanceValue;

interface AccountHandler
{
    public function getDebitBalance(Account $account, string $startDate, string $endDate): BalanceValue;

    public function getCreditBalance(Account $account, string $startDate, string $endDate): BalanceValue;

    public function getNetMovement(Account $account, string $startDate, string $endDate): BalanceValue;

    public function getStartingBalance(Account $account, string $startDate): ?BalanceValue;

    public function getEndingBalance(Account $account, string $startDate, string $endDate): ?BalanceValue;

    public function calculateNetMovementByCategory(AccountCategory $category, int $debitBalance, int $creditBalance): int;

    public function getBalances(Account $account, string $startDate, string $endDate): array;

    public function getBalancesFormatted(Account $account, string $startDate, string $endDate): AccountBalanceDTO;

    public function formatBalances(array $balances, string $currency): AccountBalanceDTO;

    public function buildAccountBalanceReport(string $startDate, string $endDate): AccountBalanceReportDTO;

    public function getTotalBalanceForAllBankAccounts(string $startDate, string $endDate): BalanceValue;

    public function getAccountCategoryOrder(): array;

    public function getEarliestTransactionDate(): string;
}
