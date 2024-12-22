<?php

namespace App\Filament\Company\Resources\Sales\EstimateResource\Widgets;

use App\Enums\Accounting\EstimateStatus;
use App\Filament\Company\Resources\Sales\EstimateResource\Pages\ListEstimates;
use App\Filament\Widgets\EnhancedStatsOverviewWidget;
use App\Utilities\Currency\CurrencyAccessor;
use App\Utilities\Currency\CurrencyConverter;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Illuminate\Support\Number;

class EstimateOverview extends EnhancedStatsOverviewWidget
{
    use InteractsWithPageTable;

    protected function getTablePage(): string
    {
        return ListEstimates::class;
    }

    protected function getStats(): array
    {
        // Active estimates: Draft, Unsent, Sent
        $activeEstimates = $this->getPageTableQuery()->active();

        $totalActiveCount = $activeEstimates->count();
        $totalActiveAmount = $activeEstimates->get()->sumMoneyInDefaultCurrency('total');

        // Accepted estimates
        $acceptedEstimates = $this->getPageTableQuery()
            ->where('status', EstimateStatus::Accepted);

        $totalAcceptedCount = $acceptedEstimates->count();
        $totalAcceptedAmount = $acceptedEstimates->get()->sumMoneyInDefaultCurrency('total');

        // Converted estimates
        $convertedEstimates = $this->getPageTableQuery()
            ->where('status', EstimateStatus::Converted);

        $totalConvertedCount = $convertedEstimates->count();
        $totalEstimatesCount = $this->getPageTableQuery()->count();

        // Use Number::percentage for formatted conversion rate
        $percentConverted = $totalEstimatesCount > 0
            ? Number::percentage(($totalConvertedCount / $totalEstimatesCount) * 100, maxPrecision: 1)
            : Number::percentage(0, maxPrecision: 1);

        // Average estimate total
        $totalEstimateAmount = $this->getPageTableQuery()
            ->get()
            ->sumMoneyInDefaultCurrency('total');

        $averageEstimateTotal = $totalEstimatesCount > 0
            ? (int) round($totalEstimateAmount / $totalEstimatesCount)
            : 0;

        return [
            // Stat 1: Total Active Estimates
            EnhancedStatsOverviewWidget\EnhancedStat::make('Active Estimates', CurrencyConverter::formatCentsToMoney($totalActiveAmount))
                ->suffix(CurrencyAccessor::getDefaultCurrency())
                ->description($totalActiveCount . ' active estimates'),

            // Stat 2: Total Accepted Estimates
            EnhancedStatsOverviewWidget\EnhancedStat::make('Accepted Estimates', CurrencyConverter::formatCentsToMoney($totalAcceptedAmount))
                ->suffix(CurrencyAccessor::getDefaultCurrency())
                ->description($totalAcceptedCount . ' accepted'),

            // Stat 3: Percent Converted
            EnhancedStatsOverviewWidget\EnhancedStat::make('Converted Estimates', $percentConverted)
                ->suffix('converted')
                ->description($totalConvertedCount . ' converted'),

            // Stat 4: Average Estimate Total
            EnhancedStatsOverviewWidget\EnhancedStat::make('Average Estimate Total', CurrencyConverter::formatCentsToMoney($averageEstimateTotal))
                ->suffix(CurrencyAccessor::getDefaultCurrency())
                ->description('Avg. value per estimate'),
        ];
    }
}
