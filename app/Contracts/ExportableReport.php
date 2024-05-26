<?php

namespace App\Contracts;

interface ExportableReport
{
    public function getTitle(): string;

    public function getHeaders(): array;

    public function getData(): array;

    public function getOverallTotals(): array;
}
