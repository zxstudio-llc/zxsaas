<?php

namespace App\Transformers;

use App\DTO\AccountDTO;

class AccountBalanceReportTransformer extends BaseReportTransformer
{
    public function getTitle(): string
    {
        return 'Account Balances';
    }

    public function getHeaders(): array
    {
        return ['', 'Account', 'Starting Balance', 'Debit', 'Credit', 'Net Movement', 'Ending Balance'];
    }

    public function getRightAlignedColumns(): array
    {
        return [2, 3, 4, 5, 6];
    }

    public function getLeftAlignedColumns(): array
    {
        return [1];
    }

    public function getCenterAlignedColumns(): array
    {
        return [0];
    }

    public function getCategories(): array
    {
        $categories = [];

        foreach ($this->report->categories as $accountCategoryName => $accountCategory) {
            $categories[] = [
                'header' => ['', $accountCategoryName, '', '', '', '', ''],
                'data' => array_map(static function (AccountDTO $account) {
                    return [
                        $account->accountCode,
                        $account->accountName,
                        $account->balance->startingBalance ?? '',
                        $account->balance->debitBalance,
                        $account->balance->creditBalance,
                        $account->balance->netMovement,
                        $account->balance->endingBalance ?? '',
                    ];
                }, $accountCategory->accounts),
                'summary' => [
                    '',
                    'Total ' . $accountCategoryName,
                    $accountCategory->summary->startingBalance ?? '',
                    $accountCategory->summary->debitBalance,
                    $accountCategory->summary->creditBalance,
                    $accountCategory->summary->netMovement,
                    $accountCategory->summary->endingBalance ?? '',
                ],
            ];
        }

        return $categories;
    }

    public function getOverallTotals(): array
    {
        return [
            '',
            'Total for all accounts',
            '',
            $this->report->overallTotal->debitBalance,
            $this->report->overallTotal->creditBalance,
            '',
            '',
        ];
    }
}
