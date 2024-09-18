<?php

use App\Factories\ReportDateFactory;
use App\Filament\Company\Pages\Reports\TrialBalance;
use App\Models\Accounting\Transaction;
use App\Utilities\Currency\CurrencyAccessor;

use function Pest\Livewire\livewire;

it('correctly builds a standard trial balance report', function () {
    $testCompany = $this->testCompany;

    $reportDatesDTO = ReportDateFactory::create($testCompany);
    $defaultDateRange = $reportDatesDTO->defaultDateRange;
    $defaultEndDate = $reportDatesDTO->defaultEndDate;

    $defaultReportType = 'standard';

    // Create transactions for the company
    Transaction::factory()
        ->forDefaultBankAccount()
        ->forUncategorizedRevenue()
        ->asDeposit(1000)
        ->count(10)
        ->create();

    $expectedBankAccountDebit = 10000;
    $expectedBankAccountCredit = 0;

    livewire(TrialBalance::class)
        ->assertFormSet([
            'deferredFilters.reportType' => $defaultReportType,
            'deferredFilters.dateRange' => $defaultDateRange,
            'deferredFilters.asOfDate' => $defaultEndDate->toDateTimeString(),
        ])
        ->assertSet('filters', [
            'reportType' => $defaultReportType,
            'dateRange' => $defaultDateRange,
            'asOfDate' => $defaultEndDate->toDateString(),
        ])
        ->call('applyFilters')
        ->assertDontSeeText('Retained Earnings')
        ->assertSeeTextInOrder([
            'Cash on Hand',
            money($expectedBankAccountDebit, CurrencyAccessor::getDefaultCurrency(), true),
            money($expectedBankAccountCredit, CurrencyAccessor::getDefaultCurrency(), true),
        ])
        ->assertReportTableData();
});

it('correctly builds a post-closing trial balance report', function () {
    $testCompany = $this->testCompany;

    $reportDatesDTO = ReportDateFactory::create($testCompany);
    $defaultDateRange = $reportDatesDTO->defaultDateRange;
    $defaultEndDate = $reportDatesDTO->defaultEndDate;

    $defaultReportType = 'postClosing';

    // Create transactions for the company
    $transaction1 = Transaction::factory()
        ->forDefaultBankAccount()
        ->forUncategorizedRevenue()
        ->asDeposit(1000)
        ->create();

    $transaction2 = Transaction::factory()
        ->forDefaultBankAccount()
        ->forUncategorizedExpense()
        ->asWithdrawal(500)
        ->create();

    $expectedRetainedEarningsDebit = 0;
    $expectedRetainedEarningsCredit = 500;

    $defaultCurrencyCode = CurrencyAccessor::getDefaultCurrency();

    livewire(TrialBalance::class)
        ->set('deferredFilters.reportType', $defaultReportType)
        ->call('applyFilters')
        ->assertFormSet([
            'deferredFilters.reportType' => $defaultReportType,
            'deferredFilters.dateRange' => $defaultDateRange,
            'deferredFilters.asOfDate' => $defaultEndDate->toDateTimeString(),
        ])
        ->assertSet('filters', [
            'reportType' => $defaultReportType,
            'dateRange' => $defaultDateRange,
            'asOfDate' => $defaultEndDate->toDateString(),
        ])
        ->call('applyFilters')
        ->assertSeeTextInOrder([
            'RE',
            'Retained Earnings',
            money($expectedRetainedEarningsDebit, $defaultCurrencyCode, true),
            money($expectedRetainedEarningsCredit, $defaultCurrencyCode, true),
        ])
        ->assertSeeTextInOrder([
            'Total Revenue',
            money(0, $defaultCurrencyCode, true),
            money(0, $defaultCurrencyCode, true),
        ])
        ->assertSeeTextInOrder([
            'Total Expenses',
            money(0, $defaultCurrencyCode, true),
            money(0, $defaultCurrencyCode, true),
        ])
        ->assertReportTableData();
});
