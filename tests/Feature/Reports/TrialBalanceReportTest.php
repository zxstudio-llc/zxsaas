<?php

use App\Contracts\ExportableReport;
use App\Enums\Accounting\AccountCategory;
use App\Filament\Company\Pages\Reports\TrialBalance;
use App\Models\Accounting\Transaction;
use App\Services\AccountService;
use App\Services\ReportService;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;

use function Pest\Livewire\livewire;

it('correctly builds a standard trial balance report', function () {
    $testUser = $this->testUser;
    $testCompany = $this->testCompany;
    $defaultBankAccount = $testCompany->default->bankAccount;

    $fiscalYearStartDate = $testCompany->locale->fiscalYearStartDate();
    $fiscalYearEndDate = $testCompany->locale->fiscalYearEndDate();
    $defaultEndDate = Carbon::parse($fiscalYearEndDate);
    $defaultAsOfDate = $defaultEndDate->isFuture() ? now()->endOfDay() : $defaultEndDate->endOfDay();

    $defaultDateRange = 'FY-' . now()->year;
    $defaultReportType = 'standard';

    // Create transactions for the company
    Transaction::factory()
        ->forCompanyAndBankAccount($testCompany, $defaultBankAccount)
        ->count(10)
        ->create();

    // Instantiate services
    $accountService = app(AccountService::class);
    $reportService = app(ReportService::class);

    // Calculate balances
    $asOfStartDate = $accountService->getEarliestTransactionDate();
    $netMovement = $accountService->getNetMovement($defaultBankAccount->account, $asOfStartDate, $defaultAsOfDate);
    $netMovementAmount = $netMovement->getAmount();

    // Verify trial balance calculations
    $trialBalance = $reportService->calculateTrialBalance($defaultBankAccount->account->category, $netMovementAmount);
    expect($trialBalance)->toMatchArray([
        'debit_balance' => max($netMovementAmount, 0),
        'credit_balance' => $netMovementAmount < 0 ? abs($netMovementAmount) : 0,
    ]);

    $formattedBalances = $reportService->formatBalances($trialBalance);

    $accountCategoryPluralLabels = array_map(fn ($category) => $category->getPluralLabel(), AccountCategory::getOrderedCategories());

    Filament::setTenant($testCompany);

    $component = livewire(TrialBalance::class)
        ->assertFormSet([
            'deferredFilters.reportType' => $defaultReportType,
            'deferredFilters.dateRange' => $defaultDateRange,
            'deferredFilters.asOfDate' => $defaultAsOfDate->toDateTimeString(),
        ])
        ->assertSet('filters', [
            'reportType' => $defaultReportType,
            'dateRange' => $defaultDateRange,
            'asOfDate' => $defaultAsOfDate->toDateString(),
        ])
        ->call('applyFilters')
        ->assertSeeTextInOrder($accountCategoryPluralLabels)
        ->assertDontSeeText('Retained Earnings')
        ->assertSeeTextInOrder([
            $formattedBalances->debitBalance,
            $formattedBalances->creditBalance,
        ]);

    /** @var ExportableReport $report */
    $report = $component->report;

    $columnLabels = array_map(static fn ($column) => $column->getLabel(), $report->getColumns());

    $component->assertSeeTextInOrder($columnLabels);

    $categories = $report->getCategories();

    foreach ($categories as $category) {
        $header = $category->header;
        $data = $category->data;
        $summary = $category->summary;

        $component->assertSeeTextInOrder($header);

        foreach ($data as $row) {
            $flatRow = [];

            foreach ($row as $value) {
                if (is_array($value)) {
                    $flatRow[] = $value['name'];
                } else {
                    $flatRow[] = $value;
                }
            }

            $component->assertSeeTextInOrder($flatRow);
        }

        $component->assertSeeTextInOrder($summary);
    }

    $overallTotals = $report->getOverallTotals();
    $component->assertSeeTextInOrder($overallTotals);
});

it('correctly builds a post-closing trial balance report', function () {
    $testUser = $this->testUser;
    $testCompany = $this->testCompany;
    $defaultBankAccount = $testCompany->default->bankAccount;

    $fiscalYearStartDate = $testCompany->locale->fiscalYearStartDate();
    $fiscalYearEndDate = $testCompany->locale->fiscalYearEndDate();
    $defaultEndDate = Carbon::parse($fiscalYearEndDate);
    $defaultAsOfDate = $defaultEndDate->isFuture() ? now()->endOfDay() : $defaultEndDate->endOfDay();

    $defaultDateRange = 'FY-' . now()->year;
    $defaultReportType = 'postClosing';

    // Create transactions for the company
    Transaction::factory()
        ->forCompanyAndBankAccount($testCompany, $defaultBankAccount)
        ->count(10)
        ->create();

    // Instantiate services
    $accountService = app(AccountService::class);
    $reportService = app(ReportService::class);

    // Calculate balances
    $asOfStartDate = $accountService->getEarliestTransactionDate();
    $netMovement = $accountService->getNetMovement($defaultBankAccount->account, $asOfStartDate, $defaultAsOfDate);
    $netMovementAmount = $netMovement->getAmount();

    // Verify trial balance calculations
    $trialBalance = $reportService->calculateTrialBalance($defaultBankAccount->account->category, $netMovementAmount);
    expect($trialBalance)->toMatchArray([
        'debit_balance' => max($netMovementAmount, 0),
        'credit_balance' => $netMovementAmount < 0 ? abs($netMovementAmount) : 0,
    ]);

    $formattedBalances = $reportService->formatBalances($trialBalance);

    $accountCategoryPluralLabels = array_map(fn ($category) => $category->getPluralLabel(), AccountCategory::getOrderedCategories());

    $retainedEarningsAmount = $reportService->calculateRetainedEarnings($asOfStartDate, $defaultAsOfDate)->getAmount();

    $isCredit = $retainedEarningsAmount >= 0;

    $retainedEarningsDebitAmount = $isCredit ? 0 : abs($retainedEarningsAmount);
    $retainedEarningsCreditAmount = $isCredit ? $retainedEarningsAmount : 0;

    $formattedRetainedEarnings = $reportService->formatBalances([
        'debit_balance' => $retainedEarningsDebitAmount,
        'credit_balance' => $retainedEarningsCreditAmount,
    ]);

    $retainedEarningsRow = [
        'RE',
        'Retained Earnings',
        $formattedRetainedEarnings->debitBalance,
        $formattedRetainedEarnings->creditBalance,
    ];

    Filament::setTenant($testCompany);

    $component = livewire(TrialBalance::class)
        ->set('deferredFilters.reportType', $defaultReportType)
        ->call('applyFilters')
        ->assertFormSet([
            'deferredFilters.reportType' => $defaultReportType,
            'deferredFilters.dateRange' => $defaultDateRange,
            'deferredFilters.asOfDate' => $defaultAsOfDate->toDateTimeString(),
        ])
        ->assertSet('filters', [
            'reportType' => $defaultReportType,
            'dateRange' => $defaultDateRange,
            'asOfDate' => $defaultAsOfDate->toDateString(),
        ])
        ->call('applyFilters')
        ->assertSeeTextInOrder($retainedEarningsRow)
        ->assertSeeTextInOrder([
            'Total Revenue',
            '$0.00',
            '$0.00',
        ])
        ->assertSeeTextInOrder([
            'Total Expenses',
            '$0.00',
            '$0.00',
        ])
        ->assertSeeTextInOrder($accountCategoryPluralLabels)
        ->assertSeeTextInOrder([
            $formattedBalances->debitBalance,
            $formattedBalances->creditBalance,
        ]);

    /** @var ExportableReport $report */
    $report = $component->report;

    $columnLabels = array_map(static fn ($column) => $column->getLabel(), $report->getColumns());

    $component->assertSeeTextInOrder($columnLabels);

    $categories = $report->getCategories();

    foreach ($categories as $category) {
        $header = $category->header;
        $data = $category->data;
        $summary = $category->summary;

        $component->assertSeeTextInOrder($header);

        foreach ($data as $row) {
            $flatRow = [];

            foreach ($row as $value) {
                if (is_array($value)) {
                    $flatRow[] = $value['name'];
                } else {
                    $flatRow[] = $value;
                }
            }

            $component->assertSeeTextInOrder($flatRow);
        }

        $component->assertSeeTextInOrder($summary);
    }

    $overallTotals = $report->getOverallTotals();
    $component->assertSeeTextInOrder($overallTotals);
});
