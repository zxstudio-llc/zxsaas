<?php

namespace App\Filament\Company\Pages\Reports;

use App\Contracts\ExportableReport;
use App\DTO\ReportDTO;
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
use Filament\Support\Enums\ActionSize;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Enums\IconSize;
use Filament\Support\Facades\FilamentIcon;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Session;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;

abstract class BaseReportPage extends Page
{
    /**
     * @var array<string, mixed> | null
     */
    #[Url]
    public ?array $filters = null;

    /**
     * @var array<string, mixed> | null
     */
    public ?array $deferredFilters = null;

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

        $this->initializeDefaultFilters();

        $this->initializeFilters();

        $this->loadDefaultTableColumnToggleState();
    }

    protected function initializeDefaultFilters(): void
    {
        //
    }

    public function initializeFilters(): void
    {
        if (! count($this->filters ?? [])) {
            $this->filters = null;
        }

        $filtersForForm = $this->filters !== null
            ? $this->convertDatesToDateTimeString($this->filters)
            : [];

        $this->getFiltersForm()->fill($filtersForForm);

        if ($this->filters !== null) {
            $this->filters = $this->normalizeFilters($this->filters);
        }

        ray($this->filters);
    }

    protected function convertDatesToDateTimeString(array $filters): array
    {
        if (isset($filters['startDate'])) {
            $filters['startDate'] = Carbon::parse($filters['startDate'])->startOfDay()->toDateTimeString();
        }

        if (isset($filters['endDate'])) {
            $filters['endDate'] = Carbon::parse($filters['endDate'])->endOfDay()->toDateTimeString();
        }

        return $filters;
    }

    protected function getForms(): array
    {
        return [
            'toggleTableColumnForm',
            'filtersForm' => $this->getFiltersForm(),
        ];
    }

    public function filtersForm(Form $form): Form
    {
        return $form;
    }

    public function getFiltersForm(): Form
    {
        return $this->filtersForm($this->makeForm()
            ->statePath('deferredFilters'));
    }

    public function updatedFilters(): void
    {
        $this->deferredFilters = $this->filters;

        $this->handleFilterUpdates();
    }

    protected function isValidDate($date): bool
    {
        return strtotime($date) !== false;
    }

    protected function handleFilterUpdates(): void
    {
        //
    }

    public function applyFilters(): void
    {
        $this->filters = $this->normalizeFilters($this->deferredFilters);

        $this->handleFilterUpdates();

        $this->loadReportData();
    }

    protected function normalizeFilters(array $filters): array
    {
        foreach ($filters as $name => &$value) {
            if ($name === 'dateRange') {
                unset($filters[$name]);
            } elseif ($this->isValidDate($value)) {
                $value = Carbon::parse($value)->toDateString();
            }
        }

        return $filters;
    }

    public function getFiltersApplyAction(): Action
    {
        return Action::make('applyFilters')
            ->label('Update Report')
            ->action('applyFilters')
            ->keyBindings(['mod+s'])
            ->button();
    }

    public function getFilterState(string $name): mixed
    {
        return Arr::get($this->filters, $name);
    }

    public function setFilterState(string $name, mixed $value): void
    {
        Arr::set($this->filters, $name, $value);
    }

    public function getDeferredFilterState(string $name): mixed
    {
        return Arr::get($this->deferredFilters, $name);
    }

    public function setDeferredFilterState(string $name, mixed $value): void
    {
        Arr::set($this->deferredFilters, $name, $value);
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

    public function toggleColumnsAction(): Action
    {
        return Action::make('toggleColumns')
            ->label(__('filament-tables::table.actions.toggle_columns.label'))
            ->iconButton()
            ->size(ActionSize::Large)
            ->icon(FilamentIcon::resolve('tables::actions.toggle-columns') ?? 'heroicon-m-view-columns')
            ->color('gray');
    }

    public function toggleTableColumnForm(Form $form): Form
    {
        return $form
            ->schema($this->getTableColumnToggleFormSchema())
            ->statePath('toggledTableColumns');
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
