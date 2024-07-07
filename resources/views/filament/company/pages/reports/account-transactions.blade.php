<x-filament-panels::page>
    <x-filament-tables::container>
        <form wire:submit="loadReportData" class="p-6">
            {{ $this->form }}
        </form>
        <div class="divide-y divide-gray-200 overflow-x-auto overflow-y-hidden dark:divide-white/10 dark:border-t-white/10">
            <div wire:init="loadReportData" class="flex items-center justify-center">
                <div wire:loading.delay wire:target="loadReportData">
                    <x-filament::loading-indicator class="p-6 text-primary-700 dark:text-primary-300" />
                </div>
            </div>

            <div wire:loading.remove wire:target="loadReportData">
                @if($this->report)
                    <x-company.tables.reports.account-transactions :report="$this->report" />
                @endif
            </div>
        </div>
        <div class="es-table__footer-ctn border-t border-gray-200"></div>
    </x-filament-tables::container>
</x-filament-panels::page>
