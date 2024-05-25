<?php

namespace App\Filament\Company\Pages\Reports;

use App\Filament\Forms\Components\DateRangeSelect;
use App\Models\Company;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Split;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

abstract class BaseReportPage extends Page
{
    public string $startDate = '';

    public string $endDate = '';

    public string $dateRange = '';

    public string $fiscalYearStartDate = '';

    public string $fiscalYearEndDate = '';

    public Company $company;

    public function mount(): void
    {
        $this->company = auth()->user()->currentCompany;
        $this->fiscalYearStartDate = $this->company->locale->fiscalYearStartDate();
        $this->fiscalYearEndDate = $this->company->locale->fiscalYearEndDate();
        $this->dateRange = $this->getDefaultDateRange();
        $this->setDateRange(Carbon::parse($this->fiscalYearStartDate), Carbon::parse($this->fiscalYearEndDate));

        $this->loadReportData();
    }

    abstract protected function loadReportData(): void;

    public function getDefaultDateRange(): string
    {
        return 'FY-' . now()->year;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Split::make([
                    DateRangeSelect::make('dateRange')
                        ->label('Date Range')
                        ->selectablePlaceholder(false)
                        ->startDateField('startDate')
                        ->endDateField('endDate'),
                    DatePicker::make('startDate')
                        ->label('Start Date')
                        ->displayFormat('Y-m-d')
                        ->afterStateUpdated(static function (Set $set) {
                            $set('dateRange', 'Custom');
                        }),
                    DatePicker::make('endDate')
                        ->label('End Date')
                        ->displayFormat('Y-m-d')
                        ->afterStateUpdated(static function (Set $set) {
                            $set('dateRange', 'Custom');
                        }),
                ])->live(),
            ]);
    }

    public function setDateRange(Carbon $start, Carbon $end): void
    {
        $this->startDate = $start->format('Y-m-d');
        $this->endDate = $end->isFuture() ? now()->format('Y-m-d') : $end->format('Y-m-d');
    }
}
