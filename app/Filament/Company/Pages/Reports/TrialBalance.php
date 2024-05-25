<?php

namespace App\Filament\Company\Pages\Reports;

use App\DTO\ReportDTO;
use App\Services\ReportService;

class TrialBalance extends BaseReportPage
{
    protected static string $view = 'filament.company.pages.reports.trial-balance';

    protected static ?string $slug = 'reports/trial-balance';

    protected static bool $shouldRegisterNavigation = false;

    protected ReportService $reportService;

    public ReportDTO $trialBalanceReport;

    public function boot(ReportService $reportService): void
    {
        $this->reportService = $reportService;
    }

    public function getDefaultDateRange(): string
    {
        return 'FY-' . now()->year;
    }

    public function loadReportData(): void
    {
        $this->trialBalanceReport = $this->reportService->buildTrialBalanceReport($this->startDate, $this->endDate);
    }
}
