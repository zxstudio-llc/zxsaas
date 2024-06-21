<?php

namespace App\Transformers;

use App\Contracts\ExportableReport;
use App\DTO\ReportDTO;
use Filament\Support\Enums\Alignment;
use Livewire\Wireable;

abstract class BaseReportTransformer implements ExportableReport, Wireable
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

    public function toLivewire(): array
    {
        return [
            'report' => $this->report->toLivewire(),
        ];
    }

    public static function fromLivewire($value): static
    {
        return new static(
            ReportDTO::fromLivewire($value['report']),
        );
    }
}
