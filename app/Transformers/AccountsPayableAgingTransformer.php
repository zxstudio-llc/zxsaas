<?php

namespace App\Transformers;

use App\DTO\ReportCategoryDTO;
use App\DTO\VendorReportDTO;

class AccountsPayableAgingTransformer extends BaseReportTransformer
{
    public function getTitle(): string
    {
        return 'Accounts Payable Aging';
    }

    /**
     * @return ReportCategoryDTO[]
     */
    public function getCategories(): array
    {
        $categories = [];

        foreach ($this->report->categories as $accountCategory) {
            $data = array_map(function (VendorReportDTO $vendor) {
                $row = [];

                foreach ($this->getColumns() as $column) {
                    $columnName = $column->getName();

                    $row[$columnName] = match (true) {
                        $columnName === 'vendor_name' => [
                            'name' => $vendor->vendorName,
                            'id' => $vendor->vendorId,
                        ],
                        $columnName === 'current' => $vendor->aging->current,
                        str_starts_with($columnName, 'period_') => $vendor->aging->periods[$columnName] ?? null,
                        $columnName === 'over_periods' => $vendor->aging->overPeriods,
                        $columnName === 'total' => $vendor->aging->total,
                        default => '',
                    };
                }

                return $row;
            }, $accountCategory);

            $categories[] = new ReportCategoryDTO(
                header: null,
                data: $data,
                summary: null,
            );
        }

        return $categories;
    }

    public function getOverallTotals(): array
    {
        $totals = [];

        foreach ($this->getColumns() as $column) {
            $columnName = $column->getName();

            $totals[$columnName] = match (true) {
                $columnName === 'vendor_name' => 'Total',
                $columnName === 'current' => $this->report->agingSummary->current,
                str_starts_with($columnName, 'period_') => $this->report->agingSummary->periods[$columnName] ?? null,
                $columnName === 'over_periods' => $this->report->agingSummary->overPeriods,
                $columnName === 'total' => $this->report->agingSummary->total,
                default => '',
            };
        }

        return $totals;
    }
}
