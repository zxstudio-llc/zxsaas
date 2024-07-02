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
        $formattedStartDate = Carbon::parse($startDate)->format('Y-m-d');
        $formattedEndDate = Carbon::parse($endDate)->format('Y-m-d');

        $timestamp = Carbon::now()->format('Y-m-d-H_i');

        $filename = $company->name . ' ' . $report->getTitle() . ' ' . $formattedStartDate . ' to ' . $formattedEndDate . ' ' . $timestamp . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($report, $company, $formattedStartDate, $formattedEndDate) {
            $file = fopen('php://output', 'wb');

            fputcsv($file, [$report->getTitle()]);
            fputcsv($file, [$company->name]);
            fputcsv($file, ['Date Range: ' . $formattedStartDate . ' to ' . $formattedEndDate]);
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
        $formattedStartDate = Carbon::parse($startDate)->format('Y-m-d');
        $formattedEndDate = Carbon::parse($endDate)->format('Y-m-d');

        $timestamp = Carbon::now()->format('Y-m-d-H_i');

        $filename = $company->name . ' ' . $report->getTitle() . ' ' . $formattedStartDate . ' to ' . $formattedEndDate . ' ' . $timestamp . '.pdf';

        $pdf = Pdf::loadView('components.company.reports.report-pdf', [
            'company' => $company,
            'report' => $report,
            'startDate' => Carbon::parse($startDate)->format('M d, Y'),
            'endDate' => Carbon::parse($endDate)->format('M d, Y'),
        ])->setPaper('letter');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->stream();
        }, $filename);
    }
}
