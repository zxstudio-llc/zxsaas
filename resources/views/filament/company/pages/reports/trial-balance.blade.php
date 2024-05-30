<x-filament-panels::page>
    <div class="flex flex-col gap-y-6">
        <x-filament-tables::container>
            <div class="p-6 divide-y divide-gray-200 dark:divide-white/5">
                <form wire:submit.prevent="loadReportData" class="w-full">
                    <div class="flex flex-col md:flex-row items-end justify-center gap-4 md:gap-6">
                        <div class="flex-grow">
                            {{ $this->form }}
                        </div>
                        <x-filament::button type="submit" class="mt-4 md:mt-0">
                            Update Report
                        </x-filament::button>
                    </div>
                </form>
            </div>
            <div class="divide-y divide-gray-200 overflow-x-auto dark:divide-white/10 dark:border-t-white/10">
                <x-company.tables.reports.detailed-report :report="$trialBalanceReport" />
            </div>
            <div class="es-table__footer-ctn border-t border-gray-200"></div>
        </x-filament-tables::container>
    </div>
</x-filament-panels::page>

