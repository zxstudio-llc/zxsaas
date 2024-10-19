<?php

namespace App\Transformers;

use App\DTO\AccountDTO;
use App\DTO\ReportCategoryDTO;
use App\Support\Column;
use App\Utilities\Currency\CurrencyAccessor;

class IncomeStatementReportTransformer extends BaseReportTransformer
{
    protected string $totalRevenue = '0';

    protected string $totalCogs = '0';

    protected string $totalExpenses = '0';

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
                            'start_date' => $account->startDate,
                            'end_date' => $account->endDate,
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

    public function getSummaryCategories(): array
    {
        $summaryCategories = [];

        $columns = [
            'account_name',
            'net_movement',
        ];

        foreach ($this->report->categories as $accountCategoryName => $accountCategory) {
            // Header for the main category
            $categoryHeader = [];

            foreach ($columns as $index => $column) {
                $categoryHeader[$index] = $column === 'account_name' ? $accountCategoryName : '';
            }

            // Category-level summary
            $categorySummary = [];
            foreach ($columns as $column) {
                $categorySummary[] = match ($column) {
                    'account_name' => $accountCategoryName,
                    'net_movement' => $accountCategory->summary->netMovement ?? '',
                    default => '',
                };
            }

            // Add the category summary to the final array
            $summaryCategories[$accountCategoryName] = new ReportCategoryDTO(
                header: $categoryHeader,
                data: [], // No direct accounts are needed here, only summaries
                summary: $categorySummary,
                types: [] // No types for the income statement
            );
        }

        return $summaryCategories;
    }

    public function getGrossProfit(): array
    {
        $this->calculateTotals();

        $grossProfit = [];

        $columns = [
            'account_name',
            'net_movement',
        ];

        $revenue = money($this->totalRevenue, CurrencyAccessor::getDefaultCurrency())->getAmount();
        $cogs = money($this->totalCogs, CurrencyAccessor::getDefaultCurrency())->getAmount();

        $grossProfitAmount = $revenue - $cogs;
        $grossProfitFormatted = money($grossProfitAmount, CurrencyAccessor::getDefaultCurrency(), true)->format();

        foreach ($columns as $column) {
            $grossProfit[] = match ($column) {
                'account_name' => 'Gross Profit',
                'net_movement' => $grossProfitFormatted,
                default => '',
            };
        }

        return $grossProfit;
    }

    public function getSummaryTotals(): array
    {
        $totals = [];
        $columns = [
            'account_name',
            'net_movement',
        ];

        foreach ($columns as $column) {
            $totals[] = match ($column) {
                'account_name' => 'Net Earnings',
                'net_movement' => $this->report->overallTotal->netMovement ?? '',
                default => '',
            };
        }

        return $totals;
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
