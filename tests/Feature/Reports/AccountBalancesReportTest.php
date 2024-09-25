<?php

use App\Facades\Accounting;
use App\Factories\ReportDateFactory;
use App\Filament\Company\Pages\Reports\AccountBalances;
use App\Models\Accounting\Transaction;

use function Pest\Livewire\livewire;

it('correctly builds a account balances report', function () {
    $testCompany = $this->testCompany;

    $reportDatesDTO = ReportDateFactory::create($testCompany);
    $defaultDateRange = $reportDatesDTO->defaultDateRange;
    $defaultStartDate = $reportDatesDTO->defaultStartDate->toImmutable();
    $defaultEndDate = $reportDatesDTO->defaultEndDate->toImmutable();

    $depositAmount = 1000;
    $withdrawalAmount = 1000;
    $depositCount = 10;
    $withdrawalCount = 10;

    // Create transactions for the company
    Transaction::factory()
        ->forDefaultBankAccount()
        ->forUncategorizedRevenue()
        ->asDeposit($depositAmount)
        ->count($depositCount)
        ->state([
            'posted_at' => $defaultStartDate->subWeek(),
        ])
        ->create();

    Transaction::factory()
        ->forDefaultBankAccount()
        ->forUncategorizedExpense()
        ->asWithdrawal($withdrawalAmount)
        ->count($withdrawalCount)
        ->state([
            'posted_at' => $defaultEndDate,
        ])
        ->create();

    $defaultBankAccount = $testCompany->default->bankAccount->account;

    $fields = $defaultBankAccount->category->getRelevantBalanceFields();

    $expectedBalances = Accounting::getBalances($defaultBankAccount, $defaultStartDate->toDateString(), $defaultEndDate->toDateString(), $fields);

    $formattedExpectedBalances = formatReportBalances($expectedBalances);

    livewire(AccountBalances::class)
        ->assertFormSet([
            'deferredFilters.dateRange' => $defaultDateRange,
            'deferredFilters.startDate' => $defaultStartDate->toDateTimeString(),
            'deferredFilters.endDate' => $defaultEndDate->toDateTimeString(),
        ])
        ->assertSet('filters', [
            'dateRange' => $defaultDateRange,
            'startDate' => $defaultStartDate->toDateString(),
            'endDate' => $defaultEndDate->toDateString(),
        ])
        ->call('applyFilters')
        ->assertSeeTextInOrder([
            'Cash on Hand',
            $formattedExpectedBalances->startingBalance,
            $formattedExpectedBalances->debitBalance,
            $formattedExpectedBalances->creditBalance,
            $formattedExpectedBalances->netMovement,
            $formattedExpectedBalances->endingBalance,
        ])
        ->assertReportTableData();

    $expectedBalancesSubYear = Accounting::getBalances($defaultBankAccount, $defaultStartDate->subYear()->startOfYear()->toDateString(), $defaultEndDate->subYear()->endOfYear()->toDateString(), $fields);

    $formattedExpectedBalancesSubYear = formatReportBalances($expectedBalancesSubYear);

    livewire(AccountBalances::class)
        ->assertFormSet([
            'deferredFilters.dateRange' => $defaultDateRange,
            'deferredFilters.startDate' => $defaultStartDate->toDateTimeString(),
            'deferredFilters.endDate' => $defaultEndDate->toDateTimeString(),
        ])
        ->assertSet('filters', [
            'dateRange' => $defaultDateRange,
            'startDate' => $defaultStartDate->toDateString(),
            'endDate' => $defaultEndDate->toDateString(),
        ])
        ->set('deferredFilters', [
            'startDate' => $defaultStartDate->subYear()->startOfYear()->toDateTimeString(),
            'endDate' => $defaultEndDate->subYear()->endOfYear()->toDateTimeString(),
        ])
        ->call('applyFilters')
        ->assertSeeTextInOrder([
            'Cash on Hand',
            $formattedExpectedBalancesSubYear->startingBalance,
            $formattedExpectedBalancesSubYear->debitBalance,
            $formattedExpectedBalancesSubYear->creditBalance,
            $formattedExpectedBalancesSubYear->netMovement,
            $formattedExpectedBalancesSubYear->endingBalance,
        ])
        ->assertReportTableData();
});
