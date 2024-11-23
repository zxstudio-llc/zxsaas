<?php

namespace App\Forms\Components;

use Filament\Forms\Components\Component;

class CustomSection extends Component
{
    protected string $view = 'forms.components.custom-section';

    public static function make(): static
    {
        return app(static::class);
    }
}
