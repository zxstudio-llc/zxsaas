<x-filament-panels::page>
    <x-filament::section>
        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 md:gap-8">
            <!-- Form Container -->
            <div class="flex-grow">
                {{ $this->getFiltersForm() }}
            </div>

            <!-- Grouping Button and Column Toggle -->
            <div class="flex flex-col md:flex-row items-start md:items-center gap-4 md:gap-8 flex-shrink-0">
                @if($this->hasToggleableColumns())
                    <x-filament-tables::column-toggle.dropdown
                        :form="$this->toggleTableColumnForm"
                        :trigger-action="$this->toggleColumnsAction"
                    />
                @endif
                {{ $this->getFiltersApplyAction() }}
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
