<?php

namespace App\Transformers;

use App\DTO\AccountDTO;
use App\DTO\AccountTransactionDTO;
use App\DTO\ReportCategoryDTO;
use App\Support\Column;

class AccountTransactionReportTransformer extends BaseReportTransformer
{
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
                [
                    'column' => 'category',
                    'value' => $categoryData['category'],
                ],
                [
                    'column' => 'under',
                    'value' => $categoryData['under'],
                ],
            ];

            // Map transaction data
            $data = array_map(function (AccountTransactionDTO $transaction) {
                $row = [];

                foreach ($this->getColumns() as $column) {
                    $row[] = match ($column->getName()) {
                        'date' => $transaction->date,
                        'description' => $transaction->description,
                        'debit' => $transaction->debit,
                        'credit' => $transaction->credit,
                        'balance' => $transaction->balance,
                        default => '',
                    };
                }

                return $row;
            }, $categoryData['transactions']);

            // Extract summary from the last transaction if it's "Totals and Ending Balance"
            $summary = [];
            if (count($data) > 1) {
                $summaryTransaction = end($data);
                $summary = array_slice($summaryTransaction, 1); // Skip the first element, which is the date
            }

            $categories[] = new ReportCategoryDTO(
                header: $header,
                data: $data,
                summary: $summary
            );
        }

        return $categories;
    }

    public function getOverallTotals(): array
    {
        return [];
    }
}
