<table class="w-full table-auto divide-y divide-gray-200 dark:divide-white/5">
    <thead class="divide-y divide-gray-200 dark:divide-white/5">
        <tr class="bg-gray-50 dark:bg-white/5">
            @foreach($report->getHeaders() as $index => $header)
                <th wire:key="header-{{ $index }}" class="px-3 py-3.5 sm:first-of-type:ps-6 sm:last-of-type:pe-6 {{ $report->getAlignmentClass($index) }}">
                    <span class="text-sm font-semibold text-gray-950 dark:text-white">
                        {{ $header }}
                    </span>
                </th>
            @endforeach
        </tr>
    </thead>
    @foreach($report->getCategories() as $categoryIndex => $category)
        <tbody wire:key="category-{{ $categoryIndex }}" class="divide-y divide-gray-200 whitespace-nowrap dark:divide-white/5">
            <!-- Category Header -->
            <tr class="bg-gray-50 dark:bg-white/5">
                <x-filament-tables::cell colspan="{{ count($report->getHeaders()) }}" class="text-left">
                    <div class="px-3 py-2">
                        <div class="text-sm font-semibold text-gray-950 dark:text-white">
                            {{ $category->header[0]['value'] }}
                        </div>
                        <div class="text-sm text-gray-700 dark:text-gray-300">
                            {{ $category->header[1]['value'] }}
                        </div>
                    </div>
                </x-filament-tables::cell>
            </tr>
            <!-- Transactions Data -->
            @foreach($category->data as $dataIndex => $transaction)
                <tr wire:key="category-{{ $categoryIndex }}-data-{{ $dataIndex }}"
                    @class([
                        'bg-gray-50 dark:bg-white/5' => $loop->first || $loop->last || $loop->remaining === 1,
                    ])>
                    @foreach($transaction as $cellIndex => $cell)
                        <x-filament-tables::cell wire:key="category-{{ $categoryIndex }}-data-{{ $dataIndex }}-cell-{{ $cellIndex }}" class="{{ $report->getAlignmentClass($cellIndex) }}">
                            <div
                                @class([
                                    'px-3 py-4 text-sm leading-6 text-gray-950 dark:text-white',
                                    'font-semibold' => $loop->parent->first || $loop->parent->last || $loop->parent->remaining === 1,
                                ])>
                                {{ $cell }}
                            </div>
                        </x-filament-tables::cell>
                    @endforeach
                </tr>
            @endforeach
            <!-- Spacer Row -->
            @unless($loop->last)
                <tr wire:key="category-{{ $categoryIndex }}-spacer">
                    <x-filament-tables::cell colspan="{{ count($report->getHeaders()) }}">
                        <div class="px-3 py-2 leading-6 invisible">Hidden Text</div>
                    </x-filament-tables::cell>
                </tr>
            @endunless
        </tbody>
    @endforeach
    @if (filled($report->getOverallTotals()))
        <tfoot>
            <tr class="bg-gray-50 dark:bg-white/5">
                @foreach($report->getOverallTotals() as $index => $total)
                    <x-filament-tables::cell wire:key="footer-total-{{ $index }}" class="{{ $report->getAlignmentClass($index) }}">
                        <div class="px-3 py-2 text-sm leading-6 font-semibold text-gray-950 dark:text-white">
                            {{ $total }}
                        </div>
                    </x-filament-tables::cell>
                @endforeach
            </tr>
        </tfoot>
    @endif
</table>
