@php
    $prefixLabel = $getPrefixLabel();
    $suffixLabel = $getSuffixLabel();

    $childComponentContainer = $getChildComponentContainer();
    $childComponents = $childComponentContainer->getComponents();
@endphp

<div
    {{
        $attributes->class([
            'flex items-center gap-x-4',
        ])
    }}
>
    @if($prefixLabel)
        <span class="whitespace-nowrap text-sm font-medium leading-6 text-gray-950 dark:text-white">{{ $prefixLabel }}</span>
    @endif

    @foreach($childComponents as $component)
        @if(count($component->getChildComponents()) > 1)
            <div>
                {{ $component }}
            </div>
        @else
            <div class="min-w-28 [&_.fi-fo-field-wrp]:m-0 [&_.grid]:!grid-cols-1 [&_.sm\:grid-cols-3]:!grid-cols-1 [&_.sm\:col-span-2]:!col-span-1">
                {{ $component }}
            </div>
        @endif
    @endforeach

    @if($suffixLabel)
        <span class="whitespace-nowrap text-sm font-medium leading-6 text-gray-950 dark:text-white">{{ $suffixLabel }}</span>
    @endif
</div>
