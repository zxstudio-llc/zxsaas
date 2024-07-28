<?php

namespace App\Transformers;

use App\DTO\AccountTransactionDTO;
use App\DTO\ReportCategoryDTO;
use App\Support\Column;

class AccountTransactionReportTransformer extends BaseReportTransformer
{
    public function getPdfView(): string
    {
        return 'components.company.reports.account-transactions-report-pdf';
    }

    public function getTitle(): string
    {
        return 'Account Transactions';
    }

    public function getHeaders(): array
    {
        return array_map(fn (Column $column) => $column->getLabel(), $this->getColumns());
    }

    /**
     * @return ReportCategoryDTO[]
     */
    public function getCategories(): array
    {
        $categories = [];

        foreach ($this->report->categories as $categoryData) {
            // Initialize header with account and category information

            $header = [
                array_fill(0, count($this->getColumns()), ''),
                array_fill(0, count($this->getColumns()), ''),
            ];

            foreach ($this->getColumns() as $index => $column) {
                if ($column->getName() === 'date') {
                    $header[0][$index] = $categoryData['category'];
                    $header[1][$index] = $categoryData['under'];
                }
            }

            // Map transaction data
            $data = array_map(function (AccountTransactionDTO $transaction) {
                $row = [];

                foreach ($this->getColumns() as $column) {
                    $row[] = match ($column->getName()) {
                        'date' => $transaction->date,
                        'description' => [
                            'id' => $transaction->id,
                            'description' => $transaction->description,
                            'tableAction' => $transaction->tableAction,
                        ],
                        'debit' => $transaction->debit,
                        'credit' => $transaction->credit,
                        'balance' => $transaction->balance,
                        default => '',
                    };
                }

                return $row;
            }, $categoryData['transactions']);

            $categories[] = new ReportCategoryDTO(
                header: $header,
                data: $data,
            );
        }

        return $categories;
    }

    public function getOverallTotals(): array
    {
        return [];
    }
}
