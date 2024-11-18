@php
    use Filament\Support\Enums\MaxWidth;
@endphp

<x-filament-panels::layout.base :livewire="$livewire">
    @props([
        'after' => null,
        'heading' => null,
        'subheading' => null,
    ])

    <div class="fi-simple-layout flex min-h-screen">
        @if (($hasTopbar ?? true) && filament()->auth()->check())
            <div
                class="absolute end-0 top-0 flex h-16 items-center gap-x-4 pe-4 md:pe-6 lg:pe-8"
            >
                @if (filament()->hasDatabaseNotifications())
                    @livewire(Filament\Livewire\DatabaseNotifications::class, [
                        'lazy' => filament()->hasLazyLoadedDatabaseNotifications()
                    ])
                @endif

                <x-filament-panels::user-menu/>
            </div>
        @endif

        <div class="relative hidden w-0 flex-1 lg:block">
            <img class="absolute inset-0 size-full object-cover"
                 src="https://img.freepik.com/free-vector/abstract-cyber-background-with-flowing-particles-design_1048-15243.jpg?t=st=1731895585~exp=1731899185~hmac=3397976be08b5dabed940a75fb1fd957cee7f1a32cd1da1f5951435265cf36f9&w=2000"
                 alt="">
        </div>

        <div
            class="fi-simple-main-ctn flex flex-1 flex-col justify-center px-4 py-12 sm:px-6 lg:flex-none lg:px-20 xl:px-24 w-1/2 bg-white"
        >
            <main
                @class([
                    'fi-simple-main mx-auto w-full',
                    match ($maxWidth ?? null) {
                        MaxWidth::ExtraSmall, 'xs' => 'sm:max-w-xs',
                        MaxWidth::Small, 'sm' => 'sm:max-w-sm',
                        MaxWidth::Medium, 'md' => 'sm:max-w-md',
                        MaxWidth::ExtraLarge, 'xl' => 'sm:max-w-xl',
                        MaxWidth::TwoExtraLarge, '2xl' => 'sm:max-w-2xl',
                        MaxWidth::ThreeExtraLarge, '3xl' => 'sm:max-w-3xl',
                        MaxWidth::FourExtraLarge, '4xl' => 'sm:max-w-4xl',
                        MaxWidth::FiveExtraLarge, '5xl' => 'sm:max-w-5xl',
                        MaxWidth::SixExtraLarge, '6xl' => 'sm:max-w-6xl',
                        MaxWidth::SevenExtraLarge, '7xl' => 'sm:max-w-7xl',
                        default => 'sm:max-w-lg',
                    },
                ])
            >
                {{ $slot }}
            </main>
        </div>

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::FOOTER, scopes: $livewire->getRenderHookScopes()) }}
    </div>
</x-filament-panels::layout.base>
