@props(['totals', 'alignmentClass'])

@if(!empty($totals))
    <tfoot>
    <tr class="bg-gray-50 dark:bg-white/5">
        @foreach($totals as $totalIndex => $totalCell)
            <x-filament-tables::cell class="{{ $alignmentClass($totalIndex) }}">
                <div class="px-3 py-3 text-sm leading-6 font-semibold text-gray-950 dark:text-white">
                    {{ $totalCell }}
                </div>
            </x-filament-tables::cell>
        @endforeach
    </tr>
    </tfoot>
@endif
