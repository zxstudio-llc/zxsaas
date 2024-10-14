<table class="w-full table-auto divide-y divide-gray-200 dark:divide-white/5">
    <thead class="divide-y divide-gray-200 dark:divide-white/5">
    <tr class="bg-gray-50 dark:bg-white/5">
        <th class="px-3 py-3.5 sm:first-of-type:ps-6 sm:last-of-type:pe-6 text-left">
            <span class="text-sm font-semibold text-gray-950 dark:text-white">
                Accounts
            </span>
        </th>
        <th class="px-3 py-3.5 sm:first-of-type:ps-6 sm:last-of-type:pe-6 text-right">
            <span class="text-sm font-semibold text-gray-950 dark:text-white">
                Amount
            </span>
        </th>
    </tr>
    </thead>
    @foreach($report->getSummaryCategories() as $accountCategory)
        <tbody class="divide-y divide-gray-200 whitespace-nowrap dark:divide-white/5">
        <tr class="bg-gray-50 dark:bg-white/5">
            @foreach($accountCategory->header as $accountCategoryHeaderIndex => $accountCategoryHeaderCell)
                <x-filament-tables::cell class="{{ $accountCategoryHeaderIndex === 0 ? 'text-left' : 'text-right' }}">
                    <div class="px-3 py-2 text-sm font-semibold text-gray-950 dark:text-white">
                        {{ $accountCategoryHeaderCell }}
                    </div>
                </x-filament-tables::cell>
            @endforeach
        </tr>
        @foreach($accountCategory->types as $accountType)
            <tr>
                @foreach($accountType->summary as $accountTypeSummaryIndex => $accountTypeSummaryCell)
                    <x-filament-tables::cell
                        class="{{ $accountTypeSummaryIndex === 0 ? 'text-left' : 'text-right' }} ps-8">
                        <div class="px-3 py-2 text-sm leading-6 font-normal text-gray-950 dark:text-white">
                            {{ $accountTypeSummaryCell }}
                        </div>
                    </x-filament-tables::cell>
                @endforeach
            </tr>
        @endforeach
        <tr>
            @foreach($accountCategory->summary as $accountCategorySummaryIndex => $accountCategorySummaryCell)
                <x-filament-tables::cell class="{{ $accountCategorySummaryIndex === 0 ? 'text-left' : 'text-right' }}">
                    <div class="px-3 py-2 text-sm leading-6 font-semibold text-gray-950 dark:text-white">
                        {{ $accountCategorySummaryCell }}
                    </div>
                </x-filament-tables::cell>
            @endforeach
        </tr>
        <tr>
            <x-filament-tables::cell colspan="2">
                <div class="px-3 py-2 leading-6 invisible">Hidden Text</div>
            </x-filament-tables::cell>
        </tr>
        </tbody>
    @endforeach
</table>
