<?php

namespace App\Contracts;

use App\DTO\ReportCategoryDTO;

interface ExportableReport
{
    public function getTitle(): string;

    public function getHeaders(): array;

    /**
     * @return ReportCategoryDTO[]
     */
    public function getCategories(): array;

    public function getOverallTotals(): array;

    public function getColumns(): array;
}
