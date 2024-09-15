<?php

namespace App\Filament\Company\Pages\Reports;

use App\Contracts\ExportableReport;
use App\DTO\ReportDTO;
use App\Filament\Forms\Components\DateRangeSelect;
use App\Services\ExportService;
use App\Services\ReportService;
use App\Support\Column;
use App\Transformers\TrialBalanceReportTransformer;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Support\Enums\Alignment;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TrialBalance extends BaseReportPage
{
    protected static string $view = 'filament.company.pages.reports.trial-balance';

    protected static ?string $slug = 'reports/trial-balance';

    protected static bool $shouldRegisterNavigation = false;

    protected ReportService $reportService;

    protected ExportService $exportService;

    public function boot(ReportService $reportService, ExportService $exportService): void
    {
        $this->reportService = $reportService;
        $this->exportService = $exportService;
    }

    protected function initializeDefaultFilters(): void
    {
        if (empty($this->getFilterState('trialBalanceType'))) {
            $this->setFilterState('trialBalanceType', 'regular');
        }
    }

    public function getTable(): array
    {
        return [
            Column::make('account_code')
                ->label('Account Code')
                ->toggleable()
                ->alignment(Alignment::Center),
            Column::make('account_name')
                ->label('Account')
                ->alignment(Alignment::Left),
            Column::make('debit_balance')
                ->label('Debit')
                ->alignment(Alignment::Right),
            Column::make('credit_balance')
                ->label('Credit')
                ->alignment(Alignment::Right),
        ];
    }

    public function filtersForm(Form $form): Form
    {
        return $form
            ->columns(4)
            ->schema([
                Select::make('trialBalanceType')
                    ->label('Trial Balance Type')
                    ->options([
                        'regular' => 'Regular',
                        'postClosing' => 'Post-Closing',
                    ])
                    ->selectablePlaceholder(false),
                DateRangeSelect::make('dateRange')
                    ->label('As of Date')
                    ->selectablePlaceholder(false)
                    ->endDateField('asOfDate'),
                $this->getAsOfDateFormComponent(),
            ]);
    }

    protected function buildReport(array $columns): ReportDTO
    {
        return $this->reportService->buildTrialBalanceReport($this->getFilterState('trialBalanceType'), $this->getFormattedAsOfDate(), $columns);
    }

    protected function getTransformer(ReportDTO $reportDTO): ExportableReport
    {
        return new TrialBalanceReportTransformer($reportDTO);
    }

    public function exportCSV(): StreamedResponse
    {
        return $this->exportService->exportToCsv($this->company, $this->report, $this->getFilterState('startDate'), $this->getFilterState('endDate'));
    }

    public function exportPDF(): StreamedResponse
    {
        return $this->exportService->exportToPdf($this->company, $this->report, $this->getFilterState('startDate'), $this->getFilterState('endDate'));
    }
}
