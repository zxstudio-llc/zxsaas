<?php

namespace App\DTO;

class ReportCategoryDTO
{
    /**
     * @param  string[]  $header
     * @param  string[][]  $data
     * @param  string[]  $summary
     */
    public function __construct(
        public array $header,
        public array $data,
        public array $summary,
    ) {}
}
