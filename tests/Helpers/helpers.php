<?php

use App\DTO\AccountBalanceDTO;
use App\Enums\Setting\EntityType;
use App\Filament\Company\Pages\CreateCompany;
use App\Models\Company;
use App\Services\ReportService;

use function Pest\Livewire\livewire;

function createCompany(string $name): Company
{
    livewire(CreateCompany::class)
        ->fillForm([
            'name' => $name,
            'profile.email' => 'company@gmail.com',
            'profile.entity_type' => EntityType::LimitedLiabilityCompany,
            'profile.country' => 'US',
            'locale.language' => 'en',
            'currencies.code' => 'USD',
        ])
        ->call('register')
        ->assertHasNoErrors();

    return auth()->user()->currentCompany;
}

function calculateRetainedEarningsBalances(ReportService $reportService, $startDate, $endDate): AccountBalanceDTO
{
    $retainedEarningsAmount = $reportService->calculateRetainedEarnings($startDate, $endDate)->getAmount();

    $isCredit = $retainedEarningsAmount >= 0;
    $retainedEarningsDebitAmount = $isCredit ? 0 : abs($retainedEarningsAmount);
    $retainedEarningsCreditAmount = $isCredit ? $retainedEarningsAmount : 0;

    return $reportService->formatBalances([
        'debit_balance' => $retainedEarningsDebitAmount,
        'credit_balance' => $retainedEarningsCreditAmount,
    ]);
}
