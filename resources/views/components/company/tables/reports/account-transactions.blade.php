<table class="w-full table-auto divide-y divide-gray-200 dark:divide-white/5">
    <thead class="divide-y divide-gray-200 dark:divide-white/5">
    <tr class="bg-gray-50 dark:bg-white/5">
        @foreach($report->getHeaders() as $index => $header)
            <th wire:key="header-{{ $index }}"
                class="px-3 py-3.5 sm:first-of-type:ps-6 sm:last-of-type:pe-6 {{ $report->getAlignmentClass($index) }}">
                    <span class="text-sm font-semibold text-gray-950 dark:text-white">
                        {{ $header }}
                    </span>
            </th>
        @endforeach
    </tr>
    </thead>
    @foreach($report->getCategories() as $categoryIndex => $category)
        <tbody wire:key="category-{{ $categoryIndex }}"
               class="divide-y divide-gray-200 whitespace-nowrap dark:divide-white/5">
        <!-- Category Header -->
        <tr class="bg-gray-50 dark:bg-white/5">
            <x-filament-tables::cell colspan="{{ count($report->getHeaders()) }}" class="text-left">
                <div class="px-3 py-2">
                    @foreach ($category->header as $headerRow)
                        <div
                            class="text-sm {{ $loop->first ? 'font-semibold text-gray-950 dark:text-white' : 'text-gray-500 dark:text-white/50' }}">
                            @foreach ($headerRow as $headerValue)
                                @if (!empty($headerValue))
                                    {{ $headerValue }}
                                @endif
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </x-filament-tables::cell>
        </tr>
        <!-- Transactions Data -->
        @foreach($category->data as $dataIndex => $transaction)
            <tr wire:key="category-{{ $categoryIndex }}-data-{{ $dataIndex }}"
                @class([
                    'bg-gray-50 dark:bg-white/5' => $loop->first || $loop->last || $loop->remaining === 1,
                ])
            >
                @foreach($transaction as $cellIndex => $cell)
                    <x-filament-tables::cell
                        wire:key="category-{{ $categoryIndex }}-data-{{ $dataIndex }}-cell-{{ $cellIndex }}"
                        @class([
                           $report->getAlignmentClass($cellIndex),
                           'whitespace-normal' => $cellIndex === 1,
                       ])
                    >
                        <div
                            @class([
                                'px-3 py-4 text-sm leading-6 text-gray-950 dark:text-white',
                                'font-semibold' => $loop->parent->first || $loop->parent->last || $loop->parent->remaining === 1,
                            ])
                        >
                            @if(is_array($cell) && isset($cell['description']))
                                @if(isset($cell['id']) && $cell['tableAction'])
                                    <x-filament::link
                                        :href="\App\Filament\Company\Pages\Accounting\Transactions::getUrl(parameters: [
                                            'tableAction' => $cell['tableAction'],
                                            'tableActionRecord' => $cell['id'],
                                        ])"
                                        target="_blank"
                                        color="primary"
                                        icon="heroicon-o-arrow-top-right-on-square"
                                        :icon-position="\Filament\Support\Enums\IconPosition::After"
                                        icon-size="w-4 h-4 min-w-4 min-h-4"
                                    >
                                        {{ $cell['description'] }}
                                    </x-filament::link>
                                @else
                                    {{ $cell['description'] }}
                                @endif
                            @else
                                {{ $cell }}
                            @endif
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
</table>
