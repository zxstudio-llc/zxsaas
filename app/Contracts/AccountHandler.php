<?php

namespace App\Contracts;

use App\Models\Accounting\Account;
use App\ValueObjects\Money;

interface AccountHandler
{
    public function getDebitBalance(Account $account, string $startDate, string $endDate): Money;

    public function getCreditBalance(Account $account, string $startDate, string $endDate): Money;

    public function getNetMovement(Account $account, string $startDate, string $endDate): Money;

    public function getStartingBalance(Account $account, string $startDate): ?Money;

    public function getEndingBalance(Account $account, string $startDate, string $endDate): ?Money;

    public function getBalances(Account $account, string $startDate, string $endDate, array $fields): array;

    public function getTotalBalanceForAllBankAccounts(string $startDate, string $endDate): Money;

    public function getEarliestTransactionDate(): string;
}
