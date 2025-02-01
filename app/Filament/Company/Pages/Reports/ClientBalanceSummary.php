<?php

namespace App\Filament\Company\Pages\Reports;

use App\Contracts\ExportableReport;
use App\DTO\ReportDTO;
use App\Services\ExportService;
use App\Services\ReportService;
use App\Support\Column;
use App\Transformers\ClientBalanceSummaryReportTransformer;
use Filament\Forms\Form;
use Filament\Support\Enums\Alignment;
use Guava\FilamentClusters\Forms\Cluster;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientBalanceSummary extends BaseReportPage
{
    protected static string $view = 'filament.company.pages.reports.detailed-report';

    protected ReportService $reportService;

    protected ExportService $exportService;

    public function boot(ReportService $reportService, ExportService $exportService): void
    {
        $this->reportService = $reportService;
        $this->exportService = $exportService;
    }

    public function getTable(): array
    {
        return [
            Column::make('client_name')
                ->label('Client')
                ->alignment(Alignment::Left),
            Column::make('total_balance')
                ->label('Total')
                ->toggleable()
                ->alignment(Alignment::Right),
            Column::make('paid_balance')
                ->label('Paid')
                ->toggleable()
                ->alignment(Alignment::Right),
            Column::make('unpaid_balance')
                ->label('Unpaid')
                ->toggleable()
                ->alignment(Alignment::Right),
        ];
    }

    public function filtersForm(Form $form): Form
    {
        return $form
            ->inlineLabel()
            ->columns()
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
        return $this->reportService->buildClientBalanceSummaryReport(
            $this->getFormattedStartDate(),
            $this->getFormattedEndDate(),
            $columns
        );
    }

    protected function getTransformer(ReportDTO $reportDTO): ExportableReport
    {
        return new ClientBalanceSummaryReportTransformer($reportDTO);
    }

    public function exportCSV(): StreamedResponse
    {
        return $this->exportService->exportToCsv(
            $this->company,
            $this->report,
            $this->getFilterState('startDate'),
            $this->getFilterState('endDate')
        );
    }

    public function exportPDF(): StreamedResponse
    {
        return $this->exportService->exportToPdf(
            $this->company,
            $this->report,
            $this->getFilterState('startDate'),
            $this->getFilterState('endDate')
        );
    }
}
