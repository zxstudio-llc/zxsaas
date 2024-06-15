<?php

namespace App\Transformers;

use App\DTO\AccountDTO;
use App\DTO\ReportCategoryDTO;

class AccountBalanceReportTransformer extends BaseReportTransformer
{
    public function getTitle(): string
    {
        return 'Account Balances';
    }

    public function getHeaders(): array
    {
        $headers = ['Account', 'Starting Balance', 'Debit', 'Credit', 'Net Movement', 'Ending Balance'];

        if ($this->options['showAccountCode'] ?? false) {
            array_unshift($headers, 'Account Code');
        }

        return $headers;
    }

    public function getRightAlignedColumns(): array
    {
        $columns = [1, 2, 3, 4, 5];

        if ($this->options['showAccountCode'] ?? false) {
            $columns = [2, 3, 4, 5, 6];
        }

        return $columns;
    }

    public function getLeftAlignedColumns(): array
    {
        $columns = [0];

        if ($this->options['showAccountCode'] ?? false) {
            $columns = [1];
        }

        return $columns;
    }

    public function getCenterAlignedColumns(): array
    {
        if ($this->options['showAccountCode'] ?? false) {
            return [0];
        }

        return [];
    }

    /**
     * @return ReportCategoryDTO[]
     */
    public function getCategories(): array
    {
        $categories = [];

        foreach ($this->report->categories as $accountCategoryName => $accountCategory) {
            $header = [$accountCategoryName, '', '', '', '', ''];

            if ($this->options['showAccountCode'] ?? false) {
                array_unshift($header, '');
            }

            $data = array_map(function (AccountDTO $account) {
                $row = [
                    $account->accountName,
                    $account->balance->startingBalance ?? '',
                    $account->balance->debitBalance,
                    $account->balance->creditBalance,
                    $account->balance->netMovement ?? '',
                    $account->balance->endingBalance ?? '',
                ];

                if ($this->options['showAccountCode'] ?? false) {
                    array_unshift($row, $account->accountCode);
                }

                return $row;
            }, $accountCategory->accounts);

            $summary = [
                'Total ' . $accountCategoryName,
                $accountCategory->summary->startingBalance ?? '',
                $accountCategory->summary->debitBalance,
                $accountCategory->summary->creditBalance,
                $accountCategory->summary->netMovement ?? '',
                $accountCategory->summary->endingBalance ?? '',
            ];

            if ($this->options['showAccountCode'] ?? false) {
                array_unshift($summary, '');
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
        $totals = [
            'Total for all accounts',
            '',
            $this->report->overallTotal->debitBalance,
            $this->report->overallTotal->creditBalance,
            '',
            '',
        ];

        if ($this->options['showAccountCode'] ?? false) {
            array_unshift($totals, '');
        }

        return $totals;
    }
}
