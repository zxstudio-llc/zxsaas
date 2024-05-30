<?php

namespace App\Transformers;

use App\Contracts\ExportableReport;
use App\DTO\ReportDTO;
use Livewire\Wireable;

abstract class BaseReportTransformer implements ExportableReport, Wireable
{
    protected ReportDTO $report;

    public function __construct(ReportDTO $report)
    {
        $this->report = $report;
    }

    public function getAlignmentClass(int $index): string
    {
        if (in_array($index, $this->getRightAlignedColumns())) {
            return 'text-right';
        }

        if (in_array($index, $this->getCenterAlignedColumns())) {
            return 'text-center';
        }

        return 'text-left';
    }

    abstract public function getRightAlignedColumns(): array;

    abstract public function getCenterAlignedColumns(): array;

    abstract public function getLeftAlignedColumns(): array;

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
