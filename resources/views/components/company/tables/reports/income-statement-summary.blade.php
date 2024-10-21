<table class="w-full table-auto divide-y divide-gray-200 dark:divide-white/5">
    <x-company.tables.header :headers="$report->getSummaryHeaders()" :alignment-class="[$report, 'getAlignmentClass']"/>
    @foreach($report->getSummaryCategories() as $accountCategory)
        <tbody class="divide-y divide-gray-200 whitespace-nowrap dark:divide-white/5">
        <tr>
            @foreach($accountCategory->summary as $accountCategorySummaryIndex => $accountCategorySummaryCell)
                <x-company.tables.cell :alignment-class="$report->getAlignmentClass($accountCategorySummaryIndex)">
                    {{ $accountCategorySummaryCell }}
                </x-company.tables.cell>
            @endforeach
        </tr>

        @if($accountCategory->header['account_name'] === 'Cost of Goods Sold')
            <tr class="bg-gray-50 dark:bg-white/5">
                @foreach($report->getGrossProfit() as $grossProfitIndex => $grossProfitCell)
                    <x-filament-tables::cell class="{{ $report->getAlignmentClass($grossProfitIndex) }}">
                        <div class="px-3 py-2 text-sm leading-6 font-semibold text-gray-950 dark:text-white">
                            {{ $grossProfitCell }}
                        </div>
                    </x-filament-tables::cell>
                @endforeach
            </tr>
        @endif
        </tbody>
    @endforeach
    <x-company.tables.footer :totals="$report->getSummaryOverallTotals()"
                             :alignment-class="[$report, 'getAlignmentClass']"/>
</table>
