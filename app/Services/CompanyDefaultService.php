<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Setting\CompanyDefault;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CompanyDefaultService
{
    public function createCompanyDefaults(Company $company, User $user, string $currencyCode, string $countryCode, string $language): void
    {
        DB::transaction(function () use ($user, $company, $currencyCode, $countryCode, $language) {
            // Create the company defaults
            $companyDefaultFactory = CompanyDefault::factory()->withDefault($user, $company, $currencyCode, $countryCode, $language);
            $companyDefaults = $companyDefaultFactory->make()->toArray();

            $companyDefault = CompanyDefault::create($companyDefaults);

            // Create Chart of Accounts
            $chartOfAccountsService = app()->make(ChartOfAccountsService::class);
            $chartOfAccountsService->createChartOfAccounts($company);

            // Get the default bank account and update the company default record
            $defaultBankAccount = $chartOfAccountsService->getDefaultBankAccount($company);

            $companyDefault->update([
                'bank_account_id' => $defaultBankAccount?->id,
            ]);
        }, 5);
    }
}
