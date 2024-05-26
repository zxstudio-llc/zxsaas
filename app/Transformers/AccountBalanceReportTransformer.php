<?php

namespace App\Transformers;

use App\Contracts\ExportableReport;
use App\DTO\ReportDTO;

class AccountBalanceReportTransformer implements ExportableReport
{
    protected ReportDTO $report;

    public function __construct(ReportDTO $report)
    {
        $this->report = $report;
    }

    public function getTitle(): string
    {
        return 'Account Balances';
    }

    public function getHeaders(): array
    {
        return ['ACCOUNT CODE', 'ACCOUNT', 'STARTING BALANCE', 'DEBIT', 'CREDIT', 'NET MOVEMENT', 'ENDING BALANCE'];
    }

    public function getData(): array
    {
        $data = [];

        foreach ($this->report->categories as $accountCategoryName => $accountCategory) {
            // Category Header row
            $data[] = ['', $accountCategoryName];

            // Account rows
            foreach ($accountCategory->accounts as $account) {
                $data[] = [
                    $account->accountCode,
                    $account->accountName,
                    $account->balance->startingBalance ?? '',
                    $account->balance->debitBalance,
                    $account->balance->creditBalance,
                    $account->balance->netMovement,
                    $account->balance->endingBalance ?? '',
                ];
            }

            // Category Summary row
            $data[] = [
                '',
                'Total ' . $accountCategoryName,
                $accountCategory->summary->startingBalance ?? '',
                $accountCategory->summary->debitBalance,
                $accountCategory->summary->creditBalance,
                $accountCategory->summary->netMovement,
                $accountCategory->summary->endingBalance ?? '',
            ];

            // Add an empty row after each category
            $data[] = [''];
        }

        return $data;
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
