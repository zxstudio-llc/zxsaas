<?php

namespace App\DTO;

use Illuminate\Support\Carbon;

class ReportDatesDTO
{
    public function __construct(
        public Carbon $fiscalYearStartDate,
        public Carbon $fiscalYearEndDate,
        public string $defaultDateRange,
        public Carbon $defaultStartDate,
        public Carbon $defaultEndDate,
    ) {}
}
