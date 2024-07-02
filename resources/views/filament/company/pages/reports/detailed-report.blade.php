<x-filament-panels::page>
    <div class="flex flex-col gap-y-6">
        <x-filament-tables::container>
            <div class="p-6 divide-y divide-gray-200 dark:divide-white/5">
                <form wire:submit.prevent="loadReportData">
                    <div class="flex flex-col lg:flex-row items-start lg:items-center justify-center gap-4 lg:gap-12">
                        {{ $this->form }}
                        <x-filament-tables::column-toggle.dropdown
                            class="my-auto"
                            :form="$this->toggleTableColumnForm"
                            :trigger-action="$this->toggleColumnsAction"
                        />
                        <x-filament::button type="submit" class="flex-shrink-0">
                            Update Report
                        </x-filament::button>
                    </div>
                </form>
            </div>
            <div class="divide-y divide-gray-200 overflow-x-auto overflow-y-hidden dark:divide-white/10 dark:border-t-white/10">
                <div wire:init="loadReportData" class="flex items-center justify-center">
                    <div wire:loading.delay wire:target="loadReportData">
                        <x-filament::loading-indicator class="p-6 text-primary-700 dark:text-primary-300" />
                    </div>
                </div>

                <div wire:loading.remove wire:target="loadReportData">
                    @if($this->report)
                        <x-company.tables.reports.detailed-report :report="$this->report" />
                    @endif
                </div>
            </div>
            <div class="es-table__footer-ctn border-t border-gray-200"></div>
        </x-filament-tables::container>
    </div>
</x-filament-panels::page>
