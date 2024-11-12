@props([
    'activeTab' => 'summary',
    'tabLabels' => ['summary' => 'Summary', 'details' => 'Details'],
])

<x-filament::tabs>
    @foreach ($tabLabels as $tabKey => $label)
        <x-filament::tabs.item
            :active="$activeTab === $tabKey"
            wire:click="$set('activeTab', '{{ $tabKey }}')"
        >
            {{ $label }}
        </x-filament::tabs.item>
    @endforeach
</x-filament::tabs>
