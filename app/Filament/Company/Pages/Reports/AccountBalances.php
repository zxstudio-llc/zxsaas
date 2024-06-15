<?php

namespace App\Filament\Company\Pages\Reports;

use App\Contracts\ExportableReport;
use App\Services\ExportService;
use App\Services\ReportService;
use App\Transformers\AccountBalanceReportTransformer;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Split;
use Filament\Forms\Form;
use Guava\FilamentClusters\Forms\Cluster;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountBalances extends BaseReportPage
{
    protected static string $view = 'filament.company.pages.reports.account-balances';

    protected static ?string $slug = 'reports/account-balances';

    protected static bool $shouldRegisterNavigation = false;

    protected ReportService $reportService;

    public ExportableReport $accountBalanceReport;

    protected ExportService $exportService;

    public function boot(ReportService $reportService, ExportService $exportService): void
    {
        $this->reportService = $reportService;
        $this->exportService = $exportService;
    }

    public function loadReportData(): void
    {
        $reportDTO = $this->reportService->buildAccountBalanceReport($this->startDate, $this->endDate);
        $options = array_fill_keys($this->options, true);
        $this->accountBalanceReport = new AccountBalanceReportTransformer($reportDTO, $options);
    }

    public function form(Form $form): Form
    {
        return $form
            ->inlineLabel()
            ->schema([
                Split::make([
                    $this->getDateRangeFormComponent(),
                    Cluster::make([
                        $this->getStartDateFormComponent(),
                        $this->getEndDateFormComponent(),
                    ])
                        ->hiddenLabel(),
                ])->live(),
                CheckboxList::make('options')
                    ->options([
                        'showAccountCode' => 'Show Account Code',
                        'showZeroBalances' => 'Show Zero Balances',
                    ])
                    ->columns(2),
            ]);
    }

    public function exportCSV(): StreamedResponse
    {
        return $this->exportService->exportToCsv($this->company, $this->accountBalanceReport, $this->startDate, $this->endDate);
    }

    public function exportPDF(): StreamedResponse
    {
        return $this->exportService->exportToPdf($this->company, $this->accountBalanceReport, $this->startDate, $this->endDate);
    }
}
