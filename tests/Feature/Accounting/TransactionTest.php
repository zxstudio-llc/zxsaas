<?php

use App\Models\Accounting\Account;
use App\Models\Accounting\Transaction;
use App\Utilities\Currency\ConfigureCurrencies;

it('creates correct journal entries for a deposit transaction', function () {
    $transaction = Transaction::factory()
        ->forDefaultBankAccount()
        ->forUncategorizedRevenue()
        ->asDeposit(1000)
        ->create();

    [$debitAccount, $creditAccount] = getTransactionDebitAndCreditAccounts($transaction);

    expect($transaction->journalEntries->count())->toBe(2)
        ->and($debitAccount->name)->toBe('Cash on Hand')
        ->and($creditAccount->name)->toBe('Uncategorized Income');
});

it('creates correct journal entries for a withdrawal transaction', function () {
    $transaction = Transaction::factory()
        ->forDefaultBankAccount()
        ->forUncategorizedExpense()
        ->asWithdrawal(500)
        ->create();

    [$debitAccount, $creditAccount] = getTransactionDebitAndCreditAccounts($transaction);

    expect($transaction->journalEntries->count())->toBe(2)
        ->and($debitAccount->name)->toBe('Uncategorized Expense')
        ->and($creditAccount->name)->toBe('Cash on Hand');
});

it('creates correct journal entries for a transfer transaction', function () {
    $transaction = Transaction::factory()
        ->forDefaultBankAccount()
        ->forDestinationBankAccount()
        ->asTransfer(1500)
        ->create();

    [$debitAccount, $creditAccount] = getTransactionDebitAndCreditAccounts($transaction);

    // Acts as a withdrawal transaction for the source account
    expect($transaction->journalEntries->count())->toBe(2)
        ->and($debitAccount->name)->toBe('Destination Bank Account')
        ->and($creditAccount->name)->toBe('Cash on Hand');
});

it('does not create journal entries for a journal transaction', function () {
    $transaction = Transaction::factory()
        ->forDefaultBankAccount()
        ->forUncategorizedRevenue()
        ->asJournal(1000)
        ->create();

    // Journal entries for a journal transaction are created manually
    expect($transaction->journalEntries->count())->toBe(0);
});

it('stores and sums correct debit and credit amounts for different transaction types', function ($method, $setupMethod, $amount) {
    $transaction = Transaction::factory()
        ->forDefaultBankAccount()
        ->{$setupMethod}()
        ->{$method}($amount)
        ->create();

    expect($transaction->journalEntries->sumDebits()->getValue())->toEqual($amount)
        ->and($transaction->journalEntries->sumCredits()->getValue())->toEqual($amount);
})->with([
    ['asDeposit', 'forUncategorizedRevenue', 2000],
    ['asWithdrawal', 'forUncategorizedExpense', 500],
    ['asTransfer', 'forDestinationBankAccount', 1500],
]);

it('deletes associated journal entries when transaction is deleted', function () {
    $transaction = Transaction::factory()
        ->forDefaultBankAccount()
        ->forUncategorizedRevenue()
        ->asDeposit(1000)
        ->create();

    expect($transaction->journalEntries()->count())->toBe(2);

    $transaction->delete();

    $this->assertModelMissing($transaction);

    $this->assertDatabaseCount('journal_entries', 0);
});

it('handles multi-currency transfers without conversion when the source bank account is in the default currency', function () {
    $foreignBankAccount = Account::factory()
        ->withForeignBankAccount('Foreign Bank Account', 'EUR', 0.92)
        ->create();

    $transaction = Transaction::factory()
        ->forDefaultBankAccount()
        ->forDestinationBankAccount($foreignBankAccount)
        ->asTransfer(1500)
        ->create();

    [$debitAccount, $creditAccount] = getTransactionDebitAndCreditAccounts($transaction);

    expect($transaction->journalEntries->count())->toBe(2)
        ->and($debitAccount->name)->toBe('Foreign Bank Account')
        ->and($creditAccount->name)->toBe('Cash on Hand')
        ->and($transaction->journalEntries->sumDebits()->getValue())->toEqual(1500)
        ->and($transaction->journalEntries->sumCredits()->getValue())->toEqual(1500)
        ->and($transaction->amount)->toEqual('1,500.00');
});

it('handles multi-currency transfers correctly', function () {
    $foreignBankAccount = Account::factory()
        ->withForeignBankAccount('CAD Bank Account', 'CAD', 1.36)
        ->create();

    ConfigureCurrencies::syncCurrencies();

    // Create a transfer of 1500 CAD from the foreign bank account to USD bank account
    $transaction = Transaction::factory()
        ->forBankAccount($foreignBankAccount->bankAccount)
        ->forDestinationBankAccount()
        ->asTransfer(1500)
        ->create();

    [$debitAccount, $creditAccount] = getTransactionDebitAndCreditAccounts($transaction);

    expect($transaction->journalEntries->count())->toBe(2)
        ->and($debitAccount->name)->toBe('Destination Bank Account') // Debit: Destination (USD) account
        ->and($creditAccount->name)->toBe('CAD Bank Account'); // Credit: Foreign (CAD) account

    // The 1500 CAD is worth 1102.94 USD (1500 CAD / 1.36)
    $expectedUSDValue = round(1500 / 1.36, 2);

    // Verify that the debit is 1102.94 USD and the credit is 1500 CAD converted to 1102.94 USD
    // Transaction amount stays in source bank account currency (cast is applied)
    expect($transaction->journalEntries->sumDebits()->getValue())->toEqual($expectedUSDValue)
        ->and($transaction->journalEntries->sumCredits()->getValue())->toEqual($expectedUSDValue)
        ->and($transaction->amount)->toEqual('1,500.00');
});

it('handles multi-currency deposits correctly', function () {
    $foreignBankAccount = Account::factory()
        ->withForeignBankAccount('BHD Bank Account', 'BHD', 0.38)
        ->create();

    ConfigureCurrencies::syncCurrencies();

    // Create a deposit of 1500 BHD to the foreign bank account
    $transaction = Transaction::factory()
        ->forBankAccount($foreignBankAccount->bankAccount)
        ->forUncategorizedRevenue()
        ->asDeposit(1500)
        ->create();

    [$debitAccount, $creditAccount] = getTransactionDebitAndCreditAccounts($transaction);

    expect($transaction->journalEntries->count())->toBe(2)
        ->and($debitAccount->name)->toBe('BHD Bank Account') // Debit: Foreign (BHD) account
        ->and($creditAccount->name)->toBe('Uncategorized Income'); // Credit: Uncategorized Income (USD) account

    // Convert to USD using the rate 0.38 BHD per USD
    $expectedUSDValue = round(1500 / 0.38, 2);

    // Verify that the debit is 39473.68 USD and the credit is 1500 BHD converted to 39473.68 USD
    expect($transaction->journalEntries->sumDebits()->getValue())->toEqual($expectedUSDValue)
        ->and($transaction->journalEntries->sumCredits()->getValue())->toEqual($expectedUSDValue)
        ->and($transaction->amount)->toEqual('1,500.000'); // Original amount in BHD (3 decimal precision)
});

it('handles multi-currency withdrawals correctly', function () {
    $foreignBankAccount = Account::factory()
        ->withForeignBankAccount('Foreign Bank Account', 'GBP', 0.76) // GBP account
        ->create();

    ConfigureCurrencies::syncCurrencies();

    $transaction = Transaction::factory()
        ->forBankAccount($foreignBankAccount->bankAccount)
        ->forUncategorizedExpense()
        ->asWithdrawal(1500)
        ->create();

    [$debitAccount, $creditAccount] = getTransactionDebitAndCreditAccounts($transaction);

    expect($transaction->journalEntries->count())->toBe(2)
        ->and($debitAccount->name)->toBe('Uncategorized Expense')
        ->and($creditAccount->name)->toBe('Foreign Bank Account');

    $expectedUSDValue = round(1500 / 0.76, 2);

    expect($transaction->journalEntries->sumDebits()->getValue())->toEqual($expectedUSDValue)
        ->and($transaction->journalEntries->sumCredits()->getValue())->toEqual($expectedUSDValue)
        ->and($transaction->amount)->toEqual('1,500.00');
});
