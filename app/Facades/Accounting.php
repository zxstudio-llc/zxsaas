<?php

namespace App\Facades;

use App\Contracts\AccountHandler;
use App\DTO\AccountBalanceDTO;
use App\DTO\AccountBalanceReportDTO;
use App\Enums\Accounting\AccountCategory;
use App\Models\Accounting\Account;
use App\ValueObjects\BalanceValue;
use Illuminate\Support\Facades\Facade;

/**
 * @method static BalanceValue getDebitBalance(Account $account, string $startDate, string $endDate)
 * @method static BalanceValue getCreditBalance(Account $account, string $startDate, string $endDate)
 * @method static BalanceValue getNetMovement(Account $account, string $startDate, string $endDate)
 * @method static BalanceValue|null getStartingBalance(Account $account, string $startDate)
 * @method static BalanceValue|null getEndingBalance(Account $account, string $startDate, string $endDate)
 * @method static int calculateNetMovementByCategory(AccountCategory $category, int $debitBalance, int $creditBalance)
 * @method static array getBalances(Account $account, string $startDate, string $endDate)
 * @method static AccountBalanceDTO formatBalances(array $balances)
 * @method static AccountBalanceReportDTO buildAccountBalanceReport(string $startDate, string $endDate)
 * @method static BalanceValue getTotalBalanceForAllBankAccounts(string $startDate, string $endDate)
 * @method static array getAccountCategoryOrder()
 * @method static string getEarliestTransactionDate()
 *
 * @see AccountHandler
 */
class Accounting extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AccountHandler::class;
    }
}
