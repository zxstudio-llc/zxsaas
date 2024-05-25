<?php

namespace App\Filament\Company\Pages\Reports;

use App\DTO\ReportDTO;
use App\Services\AccountBalancesExportService;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Enums\IconSize;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountBalances extends BaseReportPage
{
    protected static string $view = 'filament.company.pages.reports.account-balances';

    protected static ?string $slug = 'reports/account-balances';

    protected static bool $shouldRegisterNavigation = false;

    protected ReportService $reportService;

    public ReportDTO $accountBalanceReport;

    protected AccountBalancesExportService $accountBalancesExportService;

    public function boot(ReportService $reportService, AccountBalancesExportService $accountBalancesExportService): void
    {
        $this->reportService = $reportService;
        $this->accountBalancesExportService = $accountBalancesExportService;
    }

    public function loadReportData(): void
    {
        $this->accountBalanceReport = $this->reportService->buildAccountBalanceReport($this->startDate, $this->endDate);
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('exportCSV')
                    ->label('CSV')
                    ->action(fn () => $this->exportCSV()),
                Action::make('exportPDF')
                    ->label('PDF')
                    ->action(fn () => $this->exportPDF()),
            ])
                ->label('Export')
                ->button()
                ->outlined()
                ->dropdownWidth('max-w-[7rem]')
                ->dropdownPlacement('bottom-end')
                ->icon('heroicon-c-chevron-down')
                ->iconSize(IconSize::Small)
                ->iconPosition(IconPosition::After),
        ];
    }

    public function exportCSV(): StreamedResponse
    {
        return $this->accountBalancesExportService->exportToCsv($this->company, $this->accountBalanceReport, $this->startDate, $this->endDate);
    }

    public function exportPDF(): StreamedResponse
    {
        $pdf = Pdf::loadView('components.company.reports.account-balances', [
            'accountBalanceReport' => $this->accountBalanceReport,
            'startDate' => Carbon::parse($this->startDate)->format('M d, Y'),
            'endDate' => Carbon::parse($this->endDate)->format('M d, Y'),
        ])->setPaper('a4');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->stream();
        }, 'account-balances.pdf');
    }
}
