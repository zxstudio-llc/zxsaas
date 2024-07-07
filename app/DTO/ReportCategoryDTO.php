<?php

namespace App\DTO;

class ReportCategoryDTO
{
    public function __construct(
        public array $header,
        public array $data,
        public array $summary = [],
    ) {}
}
