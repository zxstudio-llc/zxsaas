<table class="w-full table-auto divide-y divide-gray-200 dark:divide-white/5">
    <thead class="divide-y divide-gray-200 dark:divide-white/5">
    <tr class="bg-gray-50 dark:bg-white/5">
        @foreach($report->getHeaders() as $index => $header)
            <th class="px-3 py-3.5 sm:first-of-type:ps-6 sm:last-of-type:pe-6 {{ $report->getAlignmentClass($index) }}">
                <span class="text-sm font-semibold text-gray-950 dark:text-white">
                    {{ $header }}
                </span>
            </th>
        @endforeach
    </tr>
    </thead>
    @foreach($report->getCategories() as $categoryIndex => $category)
        <tbody class="divide-y divide-gray-200 whitespace-nowrap dark:divide-white/5">
        <tr class="bg-gray-50 dark:bg-white/5">
            @foreach($category->header as $headerIndex => $header)
                <x-filament-tables::cell class="{{ $report->getAlignmentClass($headerIndex) }}">
                    <div class="px-3 py-2 text-sm font-semibold text-gray-950 dark:text-white">
                        {{ $header }}
                    </div>
                </x-filament-tables::cell>
            @endforeach
        </tr>
        @foreach($category->data as $dataIndex => $account)
            <tr>
                @foreach($account as $cellIndex => $cell)
                    <x-filament-tables::cell class="{{ $report->getAlignmentClass($cellIndex) }}">
                        <div class="px-3 py-4 text-sm leading-6 text-gray-950 dark:text-white">
                            @if(is_array($cell) && isset($cell['name']))
                                @if($cell['name'] === 'Retained Earnings' && isset($cell['start_date']) && isset($cell['end_date']))
                                    <x-filament::link
                                        color="primary"
                                        target="_blank"
                                        icon="heroicon-o-arrow-top-right-on-square"
                                        :icon-position="\Filament\Support\Enums\IconPosition::After"
                                        :icon-size="\Filament\Support\Enums\IconSize::Small"
                                        href="{{ \App\Filament\Company\Pages\Reports\IncomeStatement::getUrl([
                                            'startDate' => $cell['start_date'],
                                            'endDate' => $cell['end_date']
                                        ]) }}"
                                    >
                                        {{ $cell['name'] }}
                                    </x-filament::link>
                                @elseif(isset($cell['id']) && isset($cell['start_date']) && isset($cell['end_date']))
                                    <x-filament::link
                                        color="primary"
                                        target="_blank"
                                        icon="heroicon-o-arrow-top-right-on-square"
                                        :icon-position="\Filament\Support\Enums\IconPosition::After"
                                        :icon-size="\Filament\Support\Enums\IconSize::Small"
                                        href="{{ \App\Filament\Company\Pages\Reports\AccountTransactions::getUrl([
                                            'startDate' => $cell['start_date'],
                                            'endDate' => $cell['end_date'],
                                            'selectedAccount' => $cell['id']
                                        ]) }}"
                                    >
                                        {{ $cell['name'] }}
                                    </x-filament::link>
                                @else
                                    {{ $cell['name'] }}
                                @endif
                            @else
                                {{ $cell }}
                            @endif
                        </div>
                    </x-filament-tables::cell>
                @endforeach
            </tr>
        @endforeach
        <tr>
            @foreach($category->summary as $summaryIndex => $cell)
                <x-filament-tables::cell class="{{ $report->getAlignmentClass($summaryIndex) }}">
                    <div class="px-3 py-2 text-sm leading-6 font-semibold text-gray-950 dark:text-white">
                        {{ $cell }}
                    </div>
                </x-filament-tables::cell>
            @endforeach
        </tr>
        <tr>
            <x-filament-tables::cell colspan="{{ count($report->getHeaders()) }}">
                <div class="px-3 py-2 leading-6 invisible">Hidden Text</div>
            </x-filament-tables::cell>
        </tr>
        </tbody>
    @endforeach
    @if(! empty($report->getOverallTotals()))
        <tfoot>
        <tr class="bg-gray-50 dark:bg-white/5">
            @foreach($report->getOverallTotals() as $index => $total)
                <x-filament-tables::cell class="{{ $report->getAlignmentClass($index) }}">
                    <div class="px-3 py-2 text-sm leading-6 font-semibold text-gray-950 dark:text-white">
                        {{ $total }}
                    </div>
                </x-filament-tables::cell>
            @endforeach
        </tr>
        </tfoot>
    @endif
</table>
