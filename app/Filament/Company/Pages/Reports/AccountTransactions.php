<?php

namespace App\Filament\Company\Pages\Reports;

use App\Contracts\ExportableReport;
use App\DTO\ReportDTO;
use App\Filament\Company\Pages\Accounting\Transactions;
use App\Models\Accounting\Account;
use App\Services\ExportService;
use App\Services\ReportService;
use App\Support\Column;
use App\Transformers\AccountTransactionReportTransformer;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Actions\Action;
use Guava\FilamentClusters\Forms\Cluster;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountTransactions extends BaseReportPage
{
    protected static string $view = 'filament.company.pages.reports.account-transactions';

    protected static ?string $slug = 'reports/account-transactions';

    protected static bool $shouldRegisterNavigation = false;

    protected ReportService $reportService;

    protected ExportService $exportService;

    #[Url]
    public ?string $account_id = 'all';

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
                ->markAsDate()
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
            ->columns(4)
            ->live()
            ->schema([
                Select::make('account_id')
                    ->label('Account')
                    ->options($this->getAccountOptions())
                    ->selectablePlaceholder(false)
                    ->searchable(),
                $this->getDateRangeFormComponent(),
                Cluster::make([
                    $this->getStartDateFormComponent(),
                    $this->getEndDateFormComponent(),
                ])->label("\u{200B}"), // its too bad hiddenLabel removes spacing of the label
                Actions::make([
                    Actions\Action::make('loadReportData')
                        ->label('Update Report')
                        ->submit('loadReportData')
                        ->keyBindings(['mod+s']),
                ])->alignEnd()->verticallyAlignEnd(),
            ]);
    }

    protected function getAccountOptions(): array
    {
        $accounts = Account::query()
            ->get()
            ->groupBy(fn (Account $account) => $account->category->getPluralLabel())
            ->map(fn (Collection $accounts) => $accounts->pluck('name', 'id'))
            ->toArray();

        $allAccountsOption = [
            'All Accounts' => ['all' => 'All Accounts'],
        ];

        return $allAccountsOption + $accounts;
    }

    protected function buildReport(array $columns): ReportDTO
    {
        return $this->reportService->buildAccountTransactionsReport($this->startDate, $this->endDate, $columns, $this->account_id);
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

    public function getEmptyStateHeading(): string | Htmlable
    {
        return 'No Transactions Found';
    }

    public function getEmptyStateDescription(): string | Htmlable | null
    {
        return 'Adjust the account or date range, or start by creating a transaction.';
    }

    public function getEmptyStateIcon(): string
    {
        return 'heroicon-o-x-mark';
    }

    public function getEmptyStateActions(): array
    {
        return [
            Action::make('createTransaction')
                ->label('Create Transaction')
                ->url(Transactions::getUrl()),
        ];
    }

    public function tableHasEmptyState(): bool
    {
        if ($this->report) {
            return empty($this->report->getCategories());
        } else {
            return true;
        }
    }
}
