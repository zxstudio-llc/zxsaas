<?php

namespace App\Filament\Company\Pages\Reports;

use App\Contracts\ExportableReport;
use App\DTO\ReportDTO;
use App\Services\ExportService;
use App\Services\ReportService;
use App\Support\Column;
use App\Transformers\AccountBalanceReportTransformer;
use App\Transformers\AccountTransactionReportTransformer;
use Filament\Forms\Form;
use Filament\Support\Enums\Alignment;
use Guava\FilamentClusters\Forms\Cluster;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountTransactions extends BaseReportPage
{
    protected static string $view = 'filament.company.pages.reports.account-transactions';

    protected static ?string $slug = 'reports/account-transactions';

    protected static bool $shouldRegisterNavigation = false;

    protected ReportService $reportService;

    protected ExportService $exportService;

    public function boot(ReportService $reportService, ExportService $exportService): void
    {
        $this->reportService = $reportService;
        $this->exportService = $exportService;
    }

    /**
     * @return array<Column>
     */
    public function getTable(): array
    {
        return [
            Column::make('date')
                ->label('Date')
                ->alignment(Alignment::Left),
            Column::make('description')
                ->label('Description')
                ->alignment(Alignment::Left),
            Column::make('debit')
                ->label('Debit')
                ->alignment(Alignment::Right),
            Column::make('credit')
                ->label('Credit')
                ->alignment(Alignment::Right),
            Column::make('balance')
                ->label('Balance')
                ->alignment(Alignment::Right),
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->inlineLabel()
            ->columns([
                'lg' => 1,
                '2xl' => 2,
            ])
            ->live()
            ->schema([
                $this->getDateRangeFormComponent(),
                Cluster::make([
                    $this->getStartDateFormComponent(),
                    $this->getEndDateFormComponent(),
                ])->hiddenLabel(),
            ]);
    }

    protected function buildReport(array $columns): ReportDTO
    {
        return $this->reportService->buildAccountTransactionsReport($this->startDate, $this->endDate, $columns);
    }

    protected function getTransformer(ReportDTO $reportDTO): ExportableReport
    {
        return new AccountTransactionReportTransformer($reportDTO);
    }

    public function exportCSV(): StreamedResponse
    {
        return $this->exportService->exportToCsv($this->company, $this->report, $this->startDate, $this->endDate);
    }

    public function exportPDF(): StreamedResponse
    {
        return $this->exportService->exportToPdf($this->company, $this->report, $this->startDate, $this->endDate);
    }
}
