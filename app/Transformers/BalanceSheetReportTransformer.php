<?php

namespace App\Transformers;

use App\DTO\AccountDTO;
use App\DTO\ReportCategoryDTO;
use App\DTO\ReportTypeDTO;
use App\Support\Column;

class BalanceSheetReportTransformer extends BaseReportTransformer
{
    protected string $totalAssets = '$0.00';

    protected string $totalLiabilities = '$0.00';

    protected string $totalEquity = '$0.00';

    public function getTitle(): string
    {
        return 'Balance Sheet';
    }

    public function getHeaders(): array
    {
        return array_map(fn (Column $column) => $column->getLabel(), $this->getColumns());
    }

    public function calculateTotals(): void
    {
        foreach ($this->report->categories as $accountCategoryName => $accountCategory) {
            match ($accountCategoryName) {
                'Assets' => $this->totalAssets = $accountCategory->summary->endingBalance ?? '',
                'Liabilities' => $this->totalLiabilities = $accountCategory->summary->endingBalance ?? '',
                'Equity' => $this->totalEquity = $accountCategory->summary->endingBalance ?? '',
            };
        }
    }

    public function getCategories(): array
    {
        $categories = [];

        foreach ($this->report->categories as $accountCategoryName => $accountCategory) {
            $header = [];

            foreach ($this->getColumns() as $index => $column) {
                if ($column->getName() === 'account_name') {
                    $header[$index] = $accountCategoryName;
                } else {
                    $header[$index] = '';
                }
            }

            // Category-level summary
            $categorySummary = [];
            foreach ($this->getColumns() as $column) {
                $categorySummary[] = match ($column->getName()) {
                    'account_name' => 'Total ' . $accountCategoryName,
                    'ending_balance' => $accountCategory->summary->endingBalance ?? '',
                    default => '',
                };
            }

            // Subcategories (types) under the main category
            $types = [];
            foreach ($accountCategory->types as $typeName => $type) {
                // Header for subcategory (type)
                $typeHeader = [];
                foreach ($this->getColumns() as $index => $column) {
                    $typeHeader[$index] = $column->getName() === 'account_name' ? $typeName : '';
                }

                // Account data for the subcategory
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
                            'ending_balance' => $account->balance->endingBalance ?? '',
                            default => '',
                        };
                    }

                    return $row;
                }, $type->accounts);

                // Subcategory (type) summary
                $typeSummary = [];
                foreach ($this->getColumns() as $column) {
                    $typeSummary[] = match ($column->getName()) {
                        'account_name' => 'Total ' . $typeName,
                        'ending_balance' => $type->summary->endingBalance ?? '',
                        default => '',
                    };
                }

                // Add subcategory (type) to the list
                $types[$typeName] = new ReportTypeDTO(
                    header: $typeHeader,
                    data: $data,
                    summary: $typeSummary,
                );
            }

            // Add the category to the final array with its subcategories (types)
            $categories[$accountCategoryName] = new ReportCategoryDTO(
                header: $header,
                data: [],
                summary: $categorySummary,
                types: $types,
            );
        }

        return $categories;
    }

    public function getOverallTotals(): array
    {
        return [];
    }

    public function getSummary(): array
    {
        $this->calculateTotals();

        return [
            [
                'label' => 'Total Assets',
                'value' => $this->totalAssets,
            ],
            [
                'label' => 'Total Liabilities',
                'value' => $this->totalLiabilities,
            ],
            [
                'label' => 'Net Assets',
                'value' => $this->report->overallTotal->endingBalance ?? '',
            ],
        ];
    }
}
