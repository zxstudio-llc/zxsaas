<?php

namespace App\Services;

use App\Contracts\ExportableReport;
use App\Models\Company;
use App\Support\Column;
use Barryvdh\Snappy\Facades\SnappyPdf;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportService
{
    public function exportToCsv(Company $company, ExportableReport $report, string $startDate, string $endDate): StreamedResponse
    {
        $formattedStartDate = Carbon::parse($startDate)->toDateString();
        $formattedEndDate = Carbon::parse($endDate)->toDateString();

        $timestamp = Carbon::now()->format('Y-m-d_H-i-s');

        $filename = $company->name . ' ' . $report->getTitle() . ' ' . $formattedStartDate . ' to ' . $formattedEndDate . ' ' . $timestamp . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($startDate, $endDate, $report, $company) {
            $file = fopen('php://output', 'wb');

            $defaultStartDateFormat = Carbon::parse($startDate)->toDefaultDateFormat();
            $defaultEndDateFormat = Carbon::parse($endDate)->toDefaultDateFormat();

            fputcsv($file, [$report->getTitle()]);
            fputcsv($file, [$company->name]);
            fputcsv($file, ['Date Range: ' . $defaultStartDateFormat . ' to ' . $defaultEndDateFormat]);
            fputcsv($file, []);

            fputcsv($file, $report->getHeaders());

            foreach ($report->getCategories() as $category) {
                if (isset($category->header[0]) && is_array($category->header[0])) {
                    foreach ($category->header as $headerRow) {
                        fputcsv($file, $headerRow);
                    }
                } else {
                    fputcsv($file, $category->header);
                }

                foreach ($category->data as $accountRow) {
                    $row = [];
                    $columns = $report->getColumns();

                    /**
                     * @var Column $column
                     */
                    foreach ($columns as $index => $column) {
                        $cell = $accountRow[$index] ?? '';

                        if ($column->isDate()) {
                            try {
                                $row[] = Carbon::parse($cell)->toDateString();
                            } catch (InvalidFormatException) {
                                $row[] = $cell;
                            }
                        } elseif (is_array($cell)) {
                            // Handle array cells by extracting 'name' or 'description'
                            $row[] = $cell['name'] ?? $cell['description'] ?? '';
                        } else {
                            $row[] = $cell;
                        }
                    }

                    fputcsv($file, $row);
                }

                if (filled($category->summary)) {
                    fputcsv($file, $category->summary);
                }

                fputcsv($file, []); // Empty row for spacing
            }

            if (filled($report->getOverallTotals())) {
                fputcsv($file, $report->getOverallTotals());
            }

            fclose($file);
        };

        return response()->streamDownload($callback, $filename, $headers);
    }

    public function exportToPdf(Company $company, ExportableReport $report, string $startDate, string $endDate): StreamedResponse
    {
        $formattedStartDate = Carbon::parse($startDate)->toDateString();
        $formattedEndDate = Carbon::parse($endDate)->toDateString();

        $timestamp = Carbon::now()->format('Y-m-d_H-i-s');

        $filename = $company->name . ' ' . $report->getTitle() . ' ' . $formattedStartDate . ' to ' . $formattedEndDate . ' ' . $timestamp . '.pdf';

        $pdf = SnappyPdf::loadView($report->getPdfView(), [
            'company' => $company,
            'report' => $report,
            'startDate' => Carbon::parse($startDate)->toDefaultDateFormat(),
            'endDate' => Carbon::parse($endDate)->toDefaultDateFormat(),
        ]);

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->inline();
        }, $filename);
    }
}
