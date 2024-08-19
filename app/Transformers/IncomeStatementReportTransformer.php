<?php

namespace App\Transformers;

use App\DTO\AccountDTO;
use App\DTO\ReportCategoryDTO;
use App\Support\Column;

class IncomeStatementReportTransformer extends BaseReportTransformer
{
    protected string $totalRevenue = '$0.00';

    protected string $totalCogs = '$0.00';

    protected string $totalExpenses = '$0.00';

    public function getTitle(): string
    {
        return 'Income Statement';
    }

    public function getHeaders(): array
    {
        return array_map(fn (Column $column) => $column->getLabel(), $this->getColumns());
    }

    public function calculateTotals(): void
    {
        foreach ($this->report->categories as $accountCategoryName => $accountCategory) {
            match ($accountCategoryName) {
                'Revenue' => $this->totalRevenue = $accountCategory->summary->netMovement ?? '',
                'Cost of Goods Sold' => $this->totalCogs = $accountCategory->summary->netMovement ?? '',
                'Expenses' => $this->totalExpenses = $accountCategory->summary->netMovement ?? '',
            };
        }
    }

    public function getCategories(): array
    {
        $categories = [];

        foreach ($this->report->categories as $accountCategoryName => $accountCategory) {
            // Initialize header with empty strings
            $header = [];

            foreach ($this->getColumns() as $index => $column) {
                if ($column->getName() === 'account_name') {
                    $header[$index] = $accountCategoryName;
                } else {
                    $header[$index] = '';
                }
            }

            $data = array_map(function (AccountDTO $account) {
                $row = [];

                foreach ($this->getColumns() as $column) {
                    $row[] = match ($column->getName()) {
                        'account_code' => $account->accountCode,
                        'account_name' => [
                            'name' => $account->accountName,
                            'id' => $account->accountId ?? null,
                        ],
                        'net_movement' => $account->balance->netMovement ?? '',
                        default => '',
                    };
                }

                return $row;
            }, $accountCategory->accounts);

            $summary = [];

            foreach ($this->getColumns() as $column) {
                $summary[] = match ($column->getName()) {
                    'account_name' => 'Total ' . $accountCategoryName,
                    'net_movement' => $accountCategory->summary->netMovement ?? '',
                    default => '',
                };
            }

            $categories[] = new ReportCategoryDTO(
                header: $header,
                data: $data,
                summary: $summary,
            );
        }

        return $categories;
    }

    public function getOverallTotals(): array
    {
        $totals = [];

        foreach ($this->getColumns() as $column) {
            $totals[] = match ($column->getName()) {
                'account_name' => 'Net Earnings',
                'net_movement' => $this->report->overallTotal->netMovement ?? '',
                default => '',
            };
        }

        return $totals;
    }

    public function getSummary(): array
    {
        $this->calculateTotals();

        return [
            [
                'label' => 'Revenue',
                'value' => $this->totalRevenue,
            ],
            [
                'label' => 'Cost of Goods Sold',
                'value' => $this->totalCogs,
            ],
            [
                'label' => 'Expenses',
                'value' => $this->totalExpenses,
            ],
            [
                'label' => 'Net Earnings',
                'value' => $this->report->overallTotal->netMovement ?? '',
            ],
        ];
    }
}
