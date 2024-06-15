<?php

namespace App\Transformers;

use App\DTO\AccountDTO;
use App\DTO\ReportCategoryDTO;

class TrialBalanceReportTransformer extends BaseReportTransformer
{
    public function getTitle(): string
    {
        return 'Trial Balance';
    }

    public function getHeaders(): array
    {
        return ['', 'Account', 'Debit', 'Credit'];
    }

    public function getRightAlignedColumns(): array
    {
        return [2, 3];
    }

    public function getLeftAlignedColumns(): array
    {
        return [1];
    }

    public function getCenterAlignedColumns(): array
    {
        return [0];
    }

    /**
     * @return ReportCategoryDTO[]
     */
    public function getCategories(): array
    {
        $categories = [];

        foreach ($this->report->categories as $accountCategoryName => $accountCategory) {
            $categories[] = new ReportCategoryDTO(
                header: ['', $accountCategoryName, '', ''],
                data: array_map(static function (AccountDTO $account) {
                    return [
                        $account->accountCode,
                        $account->accountName,
                        $account->balance->debitBalance,
                        $account->balance->creditBalance,
                    ];
                }, $accountCategory->accounts),
                summary: [
                    '',
                    'Total ' . $accountCategoryName,
                    $accountCategory->summary->debitBalance,
                    $accountCategory->summary->creditBalance,
                ],
            );
        }

        return $categories;
    }

    public function getOverallTotals(): array
    {
        return [
            '',
            'Total for all accounts',
            $this->report->overallTotal->debitBalance,
            $this->report->overallTotal->creditBalance,
        ];
    }
}
