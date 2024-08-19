<x-filament-panels::page>
    <x-filament::section>
        <form wire:submit="loadReportData">
            {{ $this->form }}
        </form>
    </x-filament::section>
    
    <x-filament-tables::container>
        <div class="es-table__header-ctn"></div>
        <div
            class="relative divide-y divide-gray-200 overflow-x-auto dark:divide-white/10 dark:border-t-white/10 min-h-64">
            <div wire:init="loadReportData" class="flex items-center justify-center w-full h-full absolute">
                <div wire:loading wire:target="loadReportData">
                    <x-filament::loading-indicator class="p-6 text-primary-700 dark:text-primary-300"/>
                </div>
            </div>

            @if($this->reportLoaded)
                <div wire:loading.remove wire:target="loadReportData">
                    @if($this->report && !$this->tableHasEmptyState())
                        <x-company.tables.reports.account-transactions :report="$this->report"/>
                    @else
                        <x-filament-tables::empty-state
                            :actions="$this->getEmptyStateActions()"
                            :description="$this->getEmptyStateDescription()"
                            :heading="$this->getEmptyStateHeading()"
                            :icon="$this->getEmptyStateIcon()"
                        />
                    @endif
                </div>
            @endif
        </div>
        <div class="es-table__footer-ctn border-t border-gray-200"></div>
    </x-filament-tables::container>
</x-filament-panels::page>
