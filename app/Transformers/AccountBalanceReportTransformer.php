<?php

namespace App\Transformers;

use App\DTO\AccountDTO;
use App\DTO\ReportCategoryDTO;
use App\Support\Column;

class AccountBalanceReportTransformer extends BaseReportTransformer
{
    public function getTitle(): string
    {
        return 'Account Balances';
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
                        'account_name' => $account->accountName,
                        'starting_balance' => $account->balance->startingBalance ?? '',
                        'debit_balance' => $account->balance->debitBalance,
                        'credit_balance' => $account->balance->creditBalance,
                        'net_movement' => $account->balance->netMovement ?? '',
                        'ending_balance' => $account->balance->endingBalance ?? '',
                        default => '',
                    };
                }

                return $row;
            }, $accountCategory->accounts);

            $summary = [];

            foreach ($this->getColumns() as $column) {
                $summary[] = match ($column->getName()) {
                    'account_name' => 'Total ' . $accountCategoryName,
                    'starting_balance' => $accountCategory->summary->startingBalance ?? '',
                    'debit_balance' => $accountCategory->summary->debitBalance,
                    'credit_balance' => $accountCategory->summary->creditBalance,
                    'net_movement' => $accountCategory->summary->netMovement ?? '',
                    'ending_balance' => $accountCategory->summary->endingBalance ?? '',
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
                'account_name' => 'Total for all accounts',
                'debit_balance' => $this->report->overallTotal->debitBalance,
                'credit_balance' => $this->report->overallTotal->creditBalance,
                default => '',
            };
        }

        return $totals;
    }
}
