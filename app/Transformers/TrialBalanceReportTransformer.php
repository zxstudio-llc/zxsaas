<?php

namespace App\Transformers;

use App\Contracts\ExportableReport;
use App\DTO\ReportDTO;

class TrialBalanceReportTransformer implements ExportableReport
{
    protected ReportDTO $report;

    public function __construct(ReportDTO $report)
    {
        $this->report = $report;
    }

    public function getTitle(): string
    {
        return 'Trial Balance';
    }

    public function getHeaders(): array
    {
        return ['ACCOUNT CODE', 'ACCOUNT', 'DEBIT', 'CREDIT'];
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
                    $account->balance->debitBalance,
                    $account->balance->creditBalance,
                ];
            }

            // Category Summary row
            $data[] = [
                '',
                'Total ' . $accountCategoryName,
                $accountCategory->summary->debitBalance,
                $accountCategory->summary->creditBalance,
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
            $this->report->overallTotal->debitBalance,
            $this->report->overallTotal->creditBalance,
        ];
    }
}
