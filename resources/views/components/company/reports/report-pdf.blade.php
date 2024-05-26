<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $report->getTitle() }}</title>
    <style>
        @page {
            size: A4;
            margin: 8.5mm 8.5mm 30mm 8.5mm;
        }

        .header {
            color: #374151;
        }

        .table-class th,
        .table-class td {
            text-align: right;
            color: #374151;
        }

        /* Align the first column header and data to the left */
        .table-class th:first-child, .table-class td:first-child,
        .table-class th:nth-child(2), .table-class td:nth-child(2) {
            text-align: left;
        }

        .header {
            margin-bottom: 1rem; /* Space between header and table */
        }

        /* Ensure category name is left-aligned */
        .category-name-cell {
            text-align: left;
        }

        .header .title,
        .header .company-name,
        .header .date-range {
            margin-bottom: 0.125rem; /* Uniform space between header elements */
        }

        .title { font-size: 1.5rem; }
        .company-name { font-size: 1.125rem; font-weight: 600; }
        .date-range { font-size: 0.875rem; }

        .table-class {
            width: 100%;
            border-collapse: collapse;
        }

        .table-class th,
        .table-class td {
            padding: 0.75rem;
            font-size: 0.75rem;
            line-height: 1rem;
            border-bottom: 1px solid #d1d5db; /* Gray border for all rows */
        }

        .category-row > td {
            background-color: #f3f4f6; /* Gray background for category names */
            font-weight: 600;
        }

        .table-body tr { background-color: #ffffff; /* White background for other rows */ }

        .spacer-row > td { height: 0.75rem; }

        .table-footer-row > td {
            font-weight: 600;
            background-color: #ffffff; /* White background for footer */
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">{{ $report->getTitle() }}</div>
        <div class="company-name">{{ $company->name }}</div>
        <div class="date-range">Date Range: {{ $startDate }} to {{ $endDate }}</div>
    </div>
    <table class="table-class">
        <thead class="table-head" style="display: table-row-group;">
            <tr>
                @foreach($report->getHeaders() as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($report->getData() as $row)
                @if (count($row) === 2 && empty($row[0]))
                    <tr class="category-row">
                        <td></td>
                        <td class="category-name-cell">{{ $row[1] }}</td>
                        @for ($i = 2; $i < count($report->getHeaders()); $i++)
                            <td></td>
                        @endfor
                    </tr>
                @elseif (count($row) > 2)
                    <tr>
                        @foreach ($row as $cell)
                            <td>{{ $cell }}</td>
                        @endforeach
                    </tr>
                @elseif ($row[0] === '')
                    <tr class="spacer-row">
                        <td colspan="{{ count($report->getHeaders()) }}"></td>
                    </tr>
                @endif
            @endforeach
        </tbody>
        <tfoot>
            <tr class="table-footer-row">
                @foreach ($report->getOverallTotals() as $total)
                    <td>{{ $total }}</td>
                @endforeach
            </tr>
        </tfoot>
    </table>
</body>
</html>
