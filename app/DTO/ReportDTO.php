<?php

namespace App\DTO;

class ReportDTO
{
    public function __construct(
        /**
         * @var AccountCategoryDTO[]
         */
        public array $categories,
        public ?AccountBalanceDTO $overallTotal = null,
        public array $fields = [],
        public ?string $reportType = null,
    ) {}
}
