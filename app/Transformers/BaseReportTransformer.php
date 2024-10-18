<?php

namespace App\Transformers;

use App\Contracts\ExportableReport;
use App\DTO\ReportDTO;
use Filament\Support\Enums\Alignment;

abstract class BaseReportTransformer implements ExportableReport
{
    protected ReportDTO $report;

    public function __construct(ReportDTO $report)
    {
        $this->report = $report;
    }

    public function getColumns(): array
    {
        return $this->report->fields;
    }

    public function getPdfView(): string
    {
        return 'components.company.reports.report-pdf';
    }

    public function getAlignment(int $index): string
    {
        $column = $this->getColumns()[$index];

        if ($column->getAlignment() === Alignment::Right) {
            return 'right';
        }

        if ($column->getAlignment() === Alignment::Center) {
            return 'center';
        }

        return 'left';
    }

    public function getAlignmentClass(int $index): string
    {
        $column = $this->getColumns()[$index];

        if ($column->getAlignment() === Alignment::Right) {
            return 'text-right';
        }

        if ($column->getAlignment() === Alignment::Center) {
            return 'text-center';
        }

        return 'text-left';
    }
}
