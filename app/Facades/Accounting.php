<?php

namespace App\Facades;

use App\Contracts\AccountHandler;
use App\Models\Accounting\Account;
use App\ValueObjects\Money;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Money getDebitBalance(Account $account, string $startDate, string $endDate)
 * @method static Money getCreditBalance(Account $account, string $startDate, string $endDate)
 * @method static Money getNetMovement(Account $account, string $startDate, string $endDate)
 * @method static Money|null getStartingBalance(Account $account, string $startDate)
 * @method static Money|null getEndingBalance(Account $account, string $startDate, string $endDate)
 * @method static array getBalances(Account $account, string $startDate, string $endDate)
 * @method static Money getTotalBalanceForAllBankAccounts(string $startDate, string $endDate)
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
