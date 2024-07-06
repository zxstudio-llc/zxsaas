<?php

namespace App\Listeners;

use App\Events\CompanyGenerated;
use App\Services\ChartOfAccountsService;

class ConfigureChartOfAccounts
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(CompanyGenerated $event): void
    {
        $company = $event->company;

        $chartOfAccountsService = new ChartOfAccountsService();

        $chartOfAccountsService->createChartOfAccounts($company);
    }
}
