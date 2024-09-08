<?php

namespace App\Filament\Company\Pages\Reports;

use App\Contracts\ExportableReport;
use App\DTO\ReportDTO;
use App\Filament\Company\Pages\Concerns\HasDeferredFiltersForm;
use App\Filament\Company\Pages\Concerns\HasToggleTableColumnForm;
use App\Filament\Forms\Components\DateRangeSelect;
use App\Models\Company;
use App\Services\DateRangeService;
use App\Support\Column;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Pages\Page;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Enums\IconSize;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Session;
use Symfony\Component\HttpFoundation\StreamedResponse;

abstract class BaseReportPage extends Page
{
    use HasDeferredFiltersForm;
    use HasToggleTableColumnForm;

    public string $fiscalYearStartDate;

    public string $fiscalYearEndDate;

    public Company $company;

    public bool $reportLoaded = false;

    #[Session]
    public array $toggledTableColumns = [];

    abstract protected function buildReport(array $columns): ReportDTO;

    abstract public function exportCSV(): StreamedResponse;

    abstract public function exportPDF(): StreamedResponse;

    abstract protected function getTransformer(ReportDTO $reportDTO): ExportableReport;

    /**
     * @return array<Column>
     */
    abstract public function getTable(): array;

    public function mount(): void
    {
        $this->initializeProperties();

        $this->loadDefaultDateRange();

        $this->loadDefaultTableColumnToggleState();
    }

    protected function initializeProperties(): void
    {
        $this->company = auth()->user()->currentCompany;
        $this->fiscalYearStartDate = $this->company->locale->fiscalYearStartDate();
        $this->fiscalYearEndDate = $this->company->locale->fiscalYearEndDate();
    }

    protected function loadDefaultDateRange(): void
    {
        $startDate = $this->getFilterState('startDate');
        $endDate = $this->getFilterState('endDate');

        if ($this->isValidDate($startDate) && $this->isValidDate($endDate)) {
            $matchingDateRange = app(DateRangeService::class)->getMatchingDateRangeOption(Carbon::parse($startDate), Carbon::parse($endDate));
            $this->setFilterState('dateRange', $matchingDateRange);
        } else {
            $this->setFilterState('dateRange', $this->getDefaultDateRange());
            $this->setDateRange(Carbon::parse($this->fiscalYearStartDate), Carbon::parse($this->fiscalYearEndDate));
        }
    }

    public function loadReportData(): void
    {
        unset($this->report);

        $this->reportLoaded = true;
    }

    protected function loadDefaultTableColumnToggleState(): void
    {
        $tableColumns = $this->getTable();

        foreach ($tableColumns as $column) {
            $columnName = $column->getName();

            if (empty($this->toggledTableColumns)) {
                if ($column->isToggleable()) {
                    $this->toggledTableColumns[$columnName] = ! $column->isToggledHiddenByDefault();
                } else {
                    $this->toggledTableColumns[$columnName] = true;
                }
            }

            // Handle cases where the toggle state needs to be reset
            if (! $column->isToggleable()) {
                $this->toggledTableColumns[$columnName] = true;
            } elseif ($column->isToggleable() && $column->isToggledHiddenByDefault() && isset($this->toggledTableColumns[$columnName]) && $this->toggledTableColumns[$columnName]) {
                $this->toggledTableColumns[$columnName] = false;
            }
        }
    }

    public function getDefaultDateRange(): string
    {
        return 'FY-' . now()->year;
    }

    protected function getToggledColumns(): array
    {
        return array_values(
            array_filter(
                $this->getTable(),
                fn (Column $column) => $this->toggledTableColumns[$column->getName()] ?? false,
            )
        );
    }

    #[Computed(persist: true)]
    public function report(): ?ExportableReport
    {
        if ($this->reportLoaded === false) {
            return null;
        }

        $columns = $this->getToggledColumns();
        $reportDTO = $this->buildReport($columns);

        return $this->getTransformer($reportDTO);
    }

    public function setDateRange(Carbon $start, Carbon $end): void
    {
        $this->setFilterState('startDate', $start->startOfDay()->toDateTimeString());
        $this->setFilterState('endDate', $end->isFuture() ? now()->endOfDay()->toDateTimeString() : $end->endOfDay()->toDateTimeString());
    }

    public function getFormattedStartDate(): string
    {
        return Carbon::parse($this->getFilterState('startDate'))->startOfDay()->toDateTimeString();
    }

    public function getFormattedEndDate(): string
    {
        return Carbon::parse($this->getFilterState('endDate'))->endOfDay()->toDateTimeString();
    }

    public function toggleTableColumnForm(Form $form): Form
    {
        return $form
            ->schema($this->getTableColumnToggleFormSchema());
    }

    protected function hasToggleableColumns(): bool
    {
        return ! empty($this->getTableColumnToggleFormSchema());
    }

    /**
     * @return array<Checkbox>
     */
    protected function getTableColumnToggleFormSchema(): array
    {
        $schema = [];

        foreach ($this->getTable() as $column) {
            if ($column->isToggleable()) {
                $schema[] = Checkbox::make($column->getName())
                    ->label($column->getLabel());
            }
        }

        return $schema;
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

    protected function getDateRangeFormComponent(): Component
    {
        return DateRangeSelect::make('dateRange')
            ->label('Date Range')
            ->selectablePlaceholder(false)
            ->startDateField('startDate')
            ->endDateField('endDate');
    }

    protected function getStartDateFormComponent(): Component
    {
        return DatePicker::make('startDate')
            ->label('Start Date')
            ->live()
            ->afterStateUpdated(static function ($state, Set $set) {
                $set('dateRange', 'Custom');
            });
    }

    protected function getEndDateFormComponent(): Component
    {
        return DatePicker::make('endDate')
            ->label('End Date')
            ->live()
            ->afterStateUpdated(static function (Set $set) {
                $set('dateRange', 'Custom');
            });
    }
}
