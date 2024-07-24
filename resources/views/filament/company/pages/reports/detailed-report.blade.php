<x-filament-panels::page>
    <x-filament-tables::container>
        <form wire:submit="loadReportData" class="p-6">
            <div class="flex flex-col md:flex-row items-start md:items-center justify-center gap-4 md:gap-12">
                {{ $this->form }}
                @if($this->hasToggleableColumns())
                    <x-filament-tables::column-toggle.dropdown
                        :form="$this->toggleTableColumnForm"
                        :trigger-action="$this->toggleColumnsAction"
                    />
                @endif
                <x-filament::button type="submit" wire:target="loadReportData" class="flex-shrink-0">
                    Update Report
                </x-filament::button>
            </div>
        </form>
        <div wire:init="loadReportData"
             class="relative divide-y divide-gray-200 overflow-x-auto dark:divide-white/10 dark:border-t-white/10 min-h-64">
            @if($this->reportLoaded)
                <div wire:loading.remove wire:target="loadReportData">
                    @if($this->report)
                        <x-company.tables.reports.detailed-report :report="$this->report"/>
                    @endif
                </div>
            @else
                <div class="absolute inset-0 flex items-center justify-center">
                    <div wire:loading wire:target="loadReportData">
                        <x-filament::loading-indicator class="p-6 text-primary-700 dark:text-primary-300"/>
                    </div>
                </div>
            @endif
        </div>
        <div class="es-table__footer-ctn border-t border-gray-200"></div>
    </x-filament-tables::container>
</x-filament-panels::page>
