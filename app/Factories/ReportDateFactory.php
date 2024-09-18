<?php

namespace App\Factories;

use App\DTO\ReportDatesDTO;
use App\Models\Company;
use Illuminate\Support\Carbon;

class ReportDateFactory
{
    public static function create(Company $company): ReportDatesDTO
    {
        $fiscalYearStartDate = Carbon::parse($company->locale->fiscalYearStartDate())->startOfDay();
        $fiscalYearEndDate = Carbon::parse($company->locale->fiscalYearEndDate())->endOfDay();
        $defaultDateRange = 'FY-' . now()->year;
        $defaultStartDate = $fiscalYearStartDate->startOfDay();
        $defaultEndDate = $fiscalYearEndDate->isFuture() ? now()->endOfDay() : $fiscalYearEndDate->endOfDay();

        // Return a new DTO with the calculated values
        return new ReportDatesDTO(
            $fiscalYearStartDate,
            $fiscalYearEndDate,
            $defaultDateRange,
            $defaultStartDate,
            $defaultEndDate
        );
    }
}
