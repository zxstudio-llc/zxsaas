<x-filament-panels::page>
    <x-filament::section>
        <div class="flex flex-col lg:flex-row items-start lg:items-end justify-between gap-4">
            <!-- Form Container -->
            @if(method_exists($this, 'filtersForm'))
                {{ $this->filtersForm }}
            @endif

            <!-- Grouping Button and Column Toggle -->
            @if($this->hasToggleableColumns())
                <div class="lg:mb-1">
                    <x-filament-tables::column-toggle.dropdown
                        :form="$this->getTableColumnToggleForm()"
                        :trigger-action="$this->getToggleColumnsTriggerAction()"
                    />
                </div>
            @endif

            <div class="inline-flex items-center min-w-0 lg:min-w-[9.5rem] justify-end">
                {{ $this->applyFiltersAction }}
            </div>
        </div>
    </x-filament::section>


    <x-filament::section>
        <!-- Summary Section -->
        @if($this->reportLoaded)
            <div
                class="flex flex-col md:flex-row items-center md:items-end text-center justify-center gap-4 md:gap-8">
                @foreach($this->report->getSummary() as $summary)
                    <div class="text-sm">
                        <div class="text-gray-600 font-medium mb-2">{{ $summary['label'] }}</div>

                        @php
                            $isNetAssets = $summary['label'] === 'Net Assets';
                            $isPositive = money($summary['value'], \App\Utilities\Currency\CurrencyAccessor::getDefaultCurrency())->isPositive();
                        @endphp

                        <strong
                            @class([
                                'text-lg',
                                'text-green-700' => $isNetAssets && $isPositive,
                                'text-danger-700' => $isNetAssets && ! $isPositive,
                            ])
                        >
                            {{ $summary['value'] }}
                        </strong>
                    </div>

                    @if(! $loop->last)
                        <div class="flex items-center justify-center px-2">
                            <strong class="text-lg">
                                {{ $loop->remaining === 1 ? '=' : '-' }}
                            </strong>
                        </div>
                    @endif
                @endforeach
            </div>
        @endif
    </x-filament::section>

    <x-filament-tables::container>
        <div class="es-table__header-ctn"></div>
        <div
            class="relative divide-y divide-gray-200 overflow-x-auto dark:divide-white/10 dark:border-t-white/10 min-h-64">
            <div wire:init="applyFilters" class="flex items-center justify-center w-full h-full absolute">
                <div wire:loading wire:target="applyFilters">
                    <x-filament::loading-indicator class="p-6 text-primary-700 dark:text-primary-300"/>
                </div>
            </div>

            @if($this->reportLoaded)
                <div wire:loading.remove wire:target="applyFilters">
                    @if($this->report)
                        <x-company.tables.reports.balance-sheet :report="$this->report"/>
                    @endif
                </div>
            @endif
        </div>
        <div class="es-table__footer-ctn border-t border-gray-200"></div>
    </x-filament-tables::container>
</x-filament-panels::page>

