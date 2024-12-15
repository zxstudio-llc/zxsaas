@props([
    'preview' => false,
])

<div class="inv-container flex justify-center p-6">
    <div
        @class([
            'inv-paper bg-[#ffffff] dark:bg-gray-800 rounded-sm shadow-xl',
            'w-full max-w-[820px] min-h-[1024px]' => $preview === false,
            'w-[38.25rem] h-[49.5rem] overflow-hidden' => $preview === true,
        ])
    >
        {{ $slot }}
    </div>
</div>
