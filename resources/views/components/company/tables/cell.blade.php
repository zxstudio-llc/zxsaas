@props([
    'alignmentClass',
    'indent' => false,
    'bold' => false,
])

<td
    @class([
        $alignmentClass,
        'last-of-type:pe-1 sm:last-of-type:pe-3',
        'ps-3 sm:ps-6' => $indent,
        'p-0 first-of-type:ps-1 sm:first-of-type:ps-3' => ! $indent,
    ])
>
    <div
        @class([
            'px-3 py-4 text-sm leading-6 text-gray-950 dark:text-white',
            'font-semibold' => $bold,
        ])
    >
        {{ $slot }}
    </div>
</td>
