<table class="w-full table-auto divide-y divide-gray-200 dark:divide-white/5">
    <thead class="divide-y divide-gray-200 dark:divide-white/5">
    <tr class="bg-gray-50 dark:bg-white/5">
        @foreach($report->getHeaders() as $reportHeaderIndex => $reportHeaderCell)
            <th class="px-3 py-3.5 sm:first-of-type:ps-6 sm:last-of-type:pe-6 {{ $report->getAlignmentClass($reportHeaderIndex) }}">
                <span class="text-sm font-semibold text-gray-950 dark:text-white">
                    {{ $reportHeaderCell }}
                </span>
            </th>
        @endforeach
    </tr>
    </thead>
    @foreach($report->getCategories() as $accountCategory)
        <tbody class="divide-y divide-gray-200 whitespace-nowrap dark:divide-white/5">
        <tr class="bg-gray-50 dark:bg-white/5">
            @foreach($accountCategory->header as $accountCategoryHeaderIndex => $accountCategoryHeaderCell)
                <x-filament-tables::cell class="{{ $report->getAlignmentClass($accountCategoryHeaderIndex) }}">
                    <div class="px-3 py-2 text-sm font-semibold text-gray-950 dark:text-white">
                        {{ $accountCategoryHeaderCell }}
                    </div>
                </x-filament-tables::cell>
            @endforeach
        </tr>
        @foreach($accountCategory->data as $categoryAccount)
            <tr>
                @foreach($categoryAccount as $accountIndex => $categoryAccountCell)
                    <x-filament-tables::cell class="{{ $report->getAlignmentClass($accountIndex) }}"
                                             style="padding-left: 1.5rem;">
                        <div class="px-3 py-4 text-sm leading-6 text-gray-950 dark:text-white">
                            @if(is_array($categoryAccountCell) && isset($categoryAccountCell['name']))
                                @if($categoryAccountCell['name'] === 'Retained Earnings' && isset($categoryAccountCell['start_date']) && isset($categoryAccountCell['end_date']))
                                    <x-filament::link
                                        color="primary"
                                        target="_blank"
                                        icon="heroicon-o-arrow-top-right-on-square"
                                        :icon-position="\Filament\Support\Enums\IconPosition::After"
                                        :icon-size="\Filament\Support\Enums\IconSize::Small"
                                        href="{{ \App\Filament\Company\Pages\Reports\IncomeStatement::getUrl([
                                            'startDate' => $categoryAccountCell['start_date'],
                                            'endDate' => $categoryAccountCell['end_date']
                                        ]) }}"
                                    >
                                        {{ $categoryAccountCell['name'] }}
                                    </x-filament::link>
                                @elseif(isset($categoryAccountCell['id']) && isset($categoryAccountCell['start_date']) && isset($categoryAccountCell['end_date']))
                                    <x-filament::link
                                        color="primary"
                                        target="_blank"
                                        icon="heroicon-o-arrow-top-right-on-square"
                                        :icon-position="\Filament\Support\Enums\IconPosition::After"
                                        :icon-size="\Filament\Support\Enums\IconSize::Small"
                                        href="{{ \App\Filament\Company\Pages\Reports\AccountTransactions::getUrl([
                                            'startDate' => $categoryAccountCell['start_date'],
                                            'endDate' => $categoryAccountCell['end_date'],
                                            'selectedAccount' => $categoryAccountCell['id']
                                        ]) }}"
                                    >
                                        {{ $categoryAccountCell['name'] }}
                                    </x-filament::link>
                                @else
                                    {{ $categoryAccountCell['name'] }}
                                @endif
                            @else
                                {{ $categoryAccountCell }}
                            @endif
                        </div>
                    </x-filament-tables::cell>
                @endforeach
            </tr>
        @endforeach
        @foreach($accountCategory->types as $accountType)
            <tr class="bg-gray-50 dark:bg-white/5">
                @foreach($accountType->header as $accountTypeHeaderIndex => $accountTypeHeaderCell)
                    <x-filament-tables::cell class="{{ $report->getAlignmentClass($accountTypeHeaderIndex) }}"
                                             style="padding-left: 1.5rem;">
                        <div class="px-3 py-2 text-sm font-semibold text-gray-950 dark:text-white">
                            {{ $accountTypeHeaderCell }}
                        </div>
                    </x-filament-tables::cell>
                @endforeach
            </tr>
            @foreach($accountType->data as $typeAccount)
                <tr>
                    @foreach($typeAccount as $accountIndex => $typeAccountCell)
                        <x-filament-tables::cell class="{{ $report->getAlignmentClass($accountIndex) }}"
                                                 style="padding-left: 1.5rem;">
                            <div class="px-3 py-4 text-sm leading-6 text-gray-950 dark:text-white">
                                @if(is_array($typeAccountCell) && isset($typeAccountCell['name']))
                                    @if($typeAccountCell['name'] === 'Retained Earnings' && isset($typeAccountCell['start_date']) && isset($typeAccountCell['end_date']))
                                        <x-filament::link
                                            color="primary"
                                            target="_blank"
                                            icon="heroicon-o-arrow-top-right-on-square"
                                            :icon-position="\Filament\Support\Enums\IconPosition::After"
                                            :icon-size="\Filament\Support\Enums\IconSize::Small"
                                            href="{{ \App\Filament\Company\Pages\Reports\IncomeStatement::getUrl([
                                            'startDate' => $typeAccountCell['start_date'],
                                            'endDate' => $typeAccountCell['end_date']
                                        ]) }}"
                                        >
                                            {{ $typeAccountCell['name'] }}
                                        </x-filament::link>
                                    @elseif(isset($typeAccountCell['id']) && isset($typeAccountCell['start_date']) && isset($typeAccountCell['end_date']))
                                        <x-filament::link
                                            color="primary"
                                            target="_blank"
                                            icon="heroicon-o-arrow-top-right-on-square"
                                            :icon-position="\Filament\Support\Enums\IconPosition::After"
                                            :icon-size="\Filament\Support\Enums\IconSize::Small"
                                            href="{{ \App\Filament\Company\Pages\Reports\AccountTransactions::getUrl([
                                            'startDate' => $typeAccountCell['start_date'],
                                            'endDate' => $typeAccountCell['end_date'],
                                            'selectedAccount' => $typeAccountCell['id']
                                        ]) }}"
                                        >
                                            {{ $typeAccountCell['name'] }}
                                        </x-filament::link>
                                    @else
                                        {{ $typeAccountCell['name'] }}
                                    @endif
                                @else
                                    {{ $typeAccountCell }}
                                @endif
                            </div>
                        </x-filament-tables::cell>
                    @endforeach
                </tr>
            @endforeach
            <tr>
                @foreach($accountType->summary as $accountTypeSummaryIndex => $accountTypeSummaryCell)
                    <x-filament-tables::cell class="{{ $report->getAlignmentClass($accountTypeSummaryIndex) }}"
                                             style="padding-left: 1.5rem;">
                        <div class="px-3 py-2 text-sm leading-6 font-semibold text-gray-950 dark:text-white">
                            {{ $accountTypeSummaryCell }}
                        </div>
                    </x-filament-tables::cell>
                @endforeach
            </tr>
        @endforeach
        <tr>
            @foreach($accountCategory->summary as $accountCategorySummaryIndex => $accountCategorySummaryCell)
                <x-filament-tables::cell class="{{ $report->getAlignmentClass($accountCategorySummaryIndex) }}">
                    <div class="px-3 py-2 text-sm leading-6 font-semibold text-gray-950 dark:text-white">
                        {{ $accountCategorySummaryCell }}
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
            @foreach($report->getOverallTotals() as $reportOverallTotalIndex => $reportOverallTotalCell)
                <x-filament-tables::cell class="{{ $report->getAlignmentClass($reportOverallTotalIndex) }}">
                    <div class="px-3 py-2 text-sm leading-6 font-semibold text-gray-950 dark:text-white">
                        {{ $reportOverallTotalCell }}
                    </div>
                </x-filament-tables::cell>
            @endforeach
        </tr>
        </tfoot>
    @endif
</table>
