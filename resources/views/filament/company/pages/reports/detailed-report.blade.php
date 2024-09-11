<x-filament-panels::page>
    <x-filament::section>
        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <!-- Form Container -->
            @if(method_exists($this, 'filtersForm'))
                {{ $this->filtersForm }}
            @endif

            <!-- Grouping Button and Column Toggle -->
            @if($this->hasToggleableColumns())
                <x-filament-tables::column-toggle.dropdown
                    :form="$this->getTableColumnToggleForm()"
                    :trigger-action="$this->getToggleColumnsTriggerAction()"
                />
            @endif

            <div class="inline-flex items-center min-w-0 md:min-w-[9.5rem] justify-end">
                {{ $this->applyFiltersAction }}
            </div>
        </div>
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
                        <x-company.tables.reports.detailed-report :report="$this->report"/>
                    @endif
                </div>
            @endif
        </div>
        <div class="es-table__footer-ctn border-t border-gray-200"></div>
    </x-filament-tables::container>
</x-filament-panels::page>
