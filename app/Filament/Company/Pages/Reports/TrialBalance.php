<?php

namespace App\Filament\Company\Pages\Reports;

use App\Contracts\ExportableReport;
use App\Services\ExportService;
use App\Services\ReportService;
use App\Transformers\TrialBalanceReportTransformer;
use Filament\Forms\Components\Split;
use Filament\Forms\Form;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TrialBalance extends BaseReportPage
{
    protected static string $view = 'filament.company.pages.reports.trial-balance';

    protected static ?string $slug = 'reports/trial-balance';

    protected static bool $shouldRegisterNavigation = false;

    protected ReportService $reportService;

    public ExportableReport $trialBalanceReport;

    protected ExportService $exportService;

    public function boot(ReportService $reportService, ExportService $exportService): void
    {
        $this->reportService = $reportService;
        $this->exportService = $exportService;
    }

    public function loadReportData(): void
    {
        $reportDTO = $this->reportService->buildTrialBalanceReport($this->startDate, $this->endDate);
        $this->trialBalanceReport = new TrialBalanceReportTransformer($reportDTO);
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
        return $this->exportService->exportToCsv($this->company, $this->trialBalanceReport, $this->startDate, $this->endDate);
    }

    public function exportPDF(): StreamedResponse
    {
        return $this->exportService->exportToPdf($this->company, $this->trialBalanceReport, $this->startDate, $this->endDate);
    }
}
