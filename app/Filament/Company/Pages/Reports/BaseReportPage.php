<?php

namespace App\Filament\Company\Pages\Reports;

use App\Contracts\ExportableReport;
use App\DTO\ReportDTO;
use App\Filament\Forms\Components\DateRangeSelect;
use App\Models\Company;
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
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Session;
use Symfony\Component\HttpFoundation\StreamedResponse;

abstract class BaseReportPage extends Page
{
    public string $startDate = '';

    public string $endDate = '';

    public string $dateRange = '';

    public string $fiscalYearStartDate = '';

    public string $fiscalYearEndDate = '';

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

    protected function getForms(): array
    {
        return [
            'toggleTableColumnForm',
            'form',
        ];
    }

    protected function initializeProperties(): void
    {
        $this->company = auth()->user()->currentCompany;
        $this->fiscalYearStartDate = $this->company->locale->fiscalYearStartDate();
        $this->fiscalYearEndDate = $this->company->locale->fiscalYearEndDate();
    }

    protected function loadDefaultDateRange(): void
    {
        if (empty($this->dateRange)) {
            $this->dateRange = $this->getDefaultDateRange();
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

        if (empty($this->toggledTableColumns)) {
            foreach ($tableColumns as $column) {
                if ($column->isToggleable()) {
                    if ($column->isToggledHiddenByDefault()) {
                        $this->toggledTableColumns[$column->getName()] = false;
                    } else {
                        $this->toggledTableColumns[$column->getName()] = true;
                    }
                } else {
                    $this->toggledTableColumns[$column->getName()] = true;
                }
            }
        }

        foreach ($tableColumns as $column) {
            $columnName = $column->getName();
            if (! $column->isToggleable()) {
                $this->toggledTableColumns[$columnName] = true;
            }

            if ($column->isToggleable() && $column->isToggledHiddenByDefault() && isset($this->toggledTableColumns[$columnName]) && $this->toggledTableColumns[$columnName]) {
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
        $this->startDate = $start->startOfDay()->toDateTimeString();
        $this->endDate = $end->isFuture() ? now()->endOfDay()->toDateTimeString() : $end->endOfDay()->toDateTimeString();
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

    protected function resetDateRange(): void
    {
        $this->dateRange = $this->getDefaultDateRange();
        $this->setDateRange(Carbon::parse($this->fiscalYearStartDate), Carbon::parse($this->fiscalYearEndDate));
    }

    protected function getStartDateFormComponent(): Component
    {
        return DatePicker::make('startDate')
            ->label('Start Date')
            ->afterStateUpdated(static function (Set $set) {
                $set('dateRange', 'Custom');
            });
    }

    protected function getEndDateFormComponent(): Component
    {
        return DatePicker::make('endDate')
            ->label('End Date')
            ->afterStateUpdated(static function (Set $set) {
                $set('dateRange', 'Custom');
            });
    }
}
