<?php

namespace App\Contracts;

interface ExportableReport
{
    public function getTitle(): string;

    public function getHeaders(): array;

    public function getCategories(): array;

    public function getOverallTotals(): array;

    public function getRightAlignedColumns(): array;

    public function getLeftAlignedColumns(): array;

    public function getCenterAlignedColumns(): array;
}
