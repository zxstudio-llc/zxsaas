<?php

namespace App\Transformers;

use App\DTO\ClientReportDTO;
use App\DTO\ReportCategoryDTO;

class ClientBalanceSummaryReportTransformer extends BaseReportTransformer
{
    public function getTitle(): string
    {
        return 'Client Balance Summary';
    }

    /**
     * @return ReportCategoryDTO[]
     */
    public function getCategories(): array
    {
        $categories = [];

        foreach ($this->report->categories as $categoryName => $category) {
            $data = array_map(function (ClientReportDTO $client) {
                $row = [];

                foreach ($this->getColumns() as $column) {
                    $row[$column->getName()] = match ($column->getName()) {
                        'client_name' => [
                            'name' => $client->clientName,
                            'id' => $client->clientId,
                        ],
                        'total_balance' => $client->balance->totalBalance,
                        'paid_balance' => $client->balance->paidBalance,
                        'unpaid_balance' => $client->balance->unpaidBalance,
                        default => '',
                    };
                }

                return $row;
            }, $category);

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
            $totals[$column->getName()] = match ($column->getName()) {
                'client_name' => 'Total for all clients',
                'total_balance' => $this->report->clientBalanceTotal->totalBalance,
                'paid_balance' => $this->report->clientBalanceTotal->paidBalance,
                'unpaid_balance' => $this->report->clientBalanceTotal->unpaidBalance,
                default => '',
            };
        }

        return $totals;
    }
}
