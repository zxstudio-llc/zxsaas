<?php

namespace App\Services;

use App\Contracts\ExportableReport;
use App\Models\Company;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportService
{
    public function exportToCsv(Company $company, ExportableReport $report, string $startDate, string $endDate): StreamedResponse
    {
        $filename = $company->name . ' ' . $report->getTitle() . ' ' . $startDate . ' to ' . $endDate . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($report, $company, $startDate, $endDate) {
            $file = fopen('php://output', 'wb');

            fputcsv($file, [$report->getTitle()]);
            fputcsv($file, [$company->name]);
            fputcsv($file, ['Date Range: ' . $startDate . ' to ' . $endDate]);
            fputcsv($file, []);

            fputcsv($file, $report->getHeaders());

            foreach ($report->getCategories() as $category) {
                fputcsv($file, $category->header);

                foreach ($category->data as $accountRow) {
                    fputcsv($file, $accountRow);
                }

                fputcsv($file, $category->summary);
                fputcsv($file, []); // Empty row for spacing
            }

            fputcsv($file, $report->getOverallTotals());

            fclose($file);
        };

        return response()->streamDownload($callback, $filename, $headers);
    }

    public function exportToPdf(Company $company, ExportableReport $report, string $startDate, string $endDate): StreamedResponse
    {
        $pdf = Pdf::loadView('components.company.reports.report-pdf', [
            'company' => $company,
            'report' => $report,
            'startDate' => Carbon::parse($startDate)->format('M d, Y'),
            'endDate' => Carbon::parse($endDate)->format('M d, Y'),
        ])->setPaper('a4');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->stream();
        }, strtolower(str_replace(' ', '-', $company->name . '-' . $report->getTitle())) . '.pdf');
    }
}
