<?php

namespace App\Filament\Company\Pages\Reports;

use App\DTO\ReportDTO;
use App\Services\ExportService;
use App\Services\ReportService;
use App\Transformers\AccountBalanceReportTransformer;
use Filament\Forms\Components\Split;
use Filament\Forms\Form;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountBalances extends BaseReportPage
{
    protected static string $view = 'filament.company.pages.reports.account-balances';

    protected static ?string $slug = 'reports/account-balances';

    protected static bool $shouldRegisterNavigation = false;

    protected ReportService $reportService;

    public ReportDTO $accountBalanceReport;

    protected ExportService $exportService;

    public function boot(ReportService $reportService, ExportService $exportService): void
    {
        $this->reportService = $reportService;
        $this->exportService = $exportService;
    }

    public function loadReportData(): void
    {
        $this->accountBalanceReport = $this->reportService->buildAccountBalanceReport($this->startDate, $this->endDate);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Split::make([
                    $this->getDateRangeFormComponent(),
                    $this->getStartDateFormComponent(),
                    $this->getEndDateFormComponent(),
                ])->live(),
            ]);
    }

    public function exportCSV(): StreamedResponse
    {
        $transformer = new AccountBalanceReportTransformer($this->accountBalanceReport);

        return $this->exportService->exportToCsv($this->company, $transformer, $this->startDate, $this->endDate);
    }

    public function exportPDF(): StreamedResponse
    {
        $transformer = new AccountBalanceReportTransformer($this->accountBalanceReport);

        return $this->exportService->exportToPdf($this->company, $transformer, $this->startDate, $this->endDate);
    }
}
