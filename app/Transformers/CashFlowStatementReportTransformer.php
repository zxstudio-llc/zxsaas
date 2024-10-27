<?php

namespace App\Transformers;

use App\DTO\AccountDTO;
use App\DTO\ReportCategoryDTO;
use App\DTO\ReportDTO;
use App\DTO\ReportTypeDTO;
use App\Utilities\Currency\CurrencyAccessor;

class CashFlowStatementReportTransformer extends SummaryReportTransformer
{
    protected string $totalOperatingActivities;

    protected string $totalInvestingActivities;

    protected string $totalFinancingActivities;

    protected string $grossCashInflow;

    protected string $grossCashOutflow;

    public function __construct(ReportDTO $report)
    {
        parent::__construct($report);

        $this->calculateTotals();
    }

    public function getTitle(): string
    {
        return 'Cash Flow Statement';
    }

    public function calculateTotals(): void
    {
        $cashInflow = 0;
        $cashOutflow = 0;

        foreach ($this->report->categories as $categoryName => $category) {
            $netMovement = (float) money($category->summary->netMovement, CurrencyAccessor::getDefaultCurrency())->getAmount();

            match ($categoryName) {
                'Operating Activities' => $this->totalOperatingActivities = $netMovement,
                'Investing Activities' => $this->totalInvestingActivities = $netMovement,
                'Financing Activities' => $this->totalFinancingActivities = $netMovement,
            };

            // Sum inflows and outflows separately
            if ($netMovement > 0) {
                $cashInflow += $netMovement;
            } else {
                $cashOutflow += $netMovement;
            }
        }

        // Store gross totals
        $this->grossCashInflow = money($cashInflow, CurrencyAccessor::getDefaultCurrency())->format();
        $this->grossCashOutflow = money($cashOutflow, CurrencyAccessor::getDefaultCurrency())->format();
    }

    public function getCategories(): array
    {
        $categories = [];

        foreach ($this->report->categories as $accountCategoryName => $accountCategory) {
            // Header for the main category
            $header = [];

            foreach ($this->getColumns() as $column) {
                $header[$column->getName()] = $column->getName() === 'account_name' ? $accountCategoryName : '';
            }

            // Category-level summary
            $categorySummary = [];
            foreach ($this->getColumns() as $column) {
                $categorySummary[$column->getName()] = match ($column->getName()) {
                    'account_name' => 'Total ' . $accountCategoryName,
                    'net_movement' => $accountCategory->summary->netMovement ?? '',
                    default => '',
                };
            }

            // Accounts directly under the main category
            $data = array_map(function (AccountDTO $account) {
                $row = [];

                foreach ($this->getColumns() as $column) {
                    $row[$column->getName()] = match ($column->getName()) {
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
            }, $accountCategory->accounts ?? []);

            // Subcategories (types) under the main category
            $types = [];
            ray($accountCategory->types);
            foreach ($accountCategory->types as $typeName => $type) {
                // Header for subcategory (type)
                $typeHeader = [];
                foreach ($this->getColumns() as $column) {
                    $typeHeader[$column->getName()] = $column->getName() === 'account_name' ? $typeName : '';
                }

                ray($typeHeader);

                // Account data for the subcategory
                $typeData = array_map(function (AccountDTO $account) {
                    $row = [];

                    foreach ($this->getColumns() as $column) {
                        $row[$column->getName()] = match ($column->getName()) {
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
                }, $type->accounts ?? []);

                ray($typeData);

                // Subcategory (type) summary
                $typeSummary = [];
                foreach ($this->getColumns() as $column) {
                    $typeSummary[$column->getName()] = match ($column->getName()) {
                        'account_name' => 'Total ' . $typeName,
                        'net_movement' => $type->summary->netMovement ?? '',
                        default => '',
                    };
                }

                // Add subcategory (type) to the list
                $types[$typeName] = new ReportTypeDTO(
                    header: $typeHeader,
                    data: $typeData,
                    summary: $typeSummary,
                );
            }

            // Add the category to the final array with its direct accounts and subcategories (types)
            $categories[$accountCategoryName] = new ReportCategoryDTO(
                header: $header,
                data: $data, // Direct accounts under the category
                summary: $categorySummary,
                types: $types, // Subcategories (types) under the category
            );
        }

        return $categories;
    }

    public function getSummaryCategories(): array
    {
        $summaryCategories = [];

        $columns = $this->getSummaryColumns();

        foreach ($this->report->categories as $accountCategoryName => $accountCategory) {
            $categoryHeader = [];

            foreach ($columns as $column) {
                $categoryHeader[$column->getName()] = $column->getName() === 'account_name' ? $accountCategoryName : '';
            }

            $categorySummary = [];
            foreach ($columns as $column) {
                $categorySummary[$column->getName()] = match ($column->getName()) {
                    'account_name' => 'Total ' . $accountCategoryName,
                    'net_movement' => $accountCategory->summary->netMovement ?? '',
                    default => '',
                };
            }

            $types = [];

            // Iterate through each account type and calculate type summaries
            foreach ($accountCategory->types as $typeName => $type) {
                $typeSummary = [];

                foreach ($columns as $column) {
                    $typeSummary[$column->getName()] = match ($column->getName()) {
                        'account_name' => 'Total ' . $typeName,
                        'net_movement' => $type->summary->netMovement ?? '',
                        default => '',
                    };
                }

                $types[$typeName] = new ReportTypeDTO(
                    header: [],
                    data: [],
                    summary: $typeSummary,
                );
            }

            // Add the category with its types and summary to the final array
            $summaryCategories[$accountCategoryName] = new ReportCategoryDTO(
                header: $categoryHeader,
                data: [],
                summary: $categorySummary,
                types: $types,
            );
        }

        return $summaryCategories;
    }

    public function getOverallTotals(): array
    {
        return [];
    }

    public function getSummaryOverallTotals(): array
    {
        return [];
    }

    public function getSummary(): array
    {
        return [
            [
                'label' => 'Gross Cash Inflow',
                'value' => $this->grossCashInflow,
            ],
            [
                'label' => 'Gross Cash Outflow',
                'value' => $this->grossCashOutflow,
            ],
            [
                'label' => 'Net Cash Flow',
                'value' => $this->report->overallTotal->netMovement ?? '',
            ],
        ];
    }
}
