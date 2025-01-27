<?php

namespace App\Transformers;

use App\DTO\ClientReportDTO;
use App\DTO\ReportCategoryDTO;

class AccountsReceivableAgingTransformer extends BaseReportTransformer
{
    public function getTitle(): string
    {
        return 'Accounts Receivable Aging';
    }

    /**
     * @return ReportCategoryDTO[]
     */
    public function getCategories(): array
    {
        $categories = [];

        foreach ($this->report->categories as $accountCategory) {
            $data = array_map(function (ClientReportDTO $client) {
                $row = [];

                foreach ($this->getColumns() as $column) {
                    $columnName = $column->getName();

                    $row[$columnName] = match (true) {
                        $columnName === 'client_name' => [
                            'name' => $client->clientName,
                            'id' => $client->clientId,
                        ],
                        $columnName === 'current' => $client->aging->current,
                        str_starts_with($columnName, 'period_') => $client->aging->periods[$columnName] ?? null,
                        $columnName === 'over_periods' => $client->aging->overPeriods,
                        $columnName === 'total' => $client->aging->total,
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
                $columnName === 'client_name' => 'Total',
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
