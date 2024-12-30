<?php

namespace App\Filament\Forms\Components;

use Closure;
use Filament\Forms\Components\Component;
use Illuminate\Contracts\Support\Htmlable;

class LabeledField extends Component
{
    protected string $view = 'filament.forms.components.labeled-field';

    protected string | Htmlable | Closure | null $prefixLabel = null;

    protected string | Htmlable | Closure | null $suffixLabel = null;

    public static function make(): static
    {
        return app(static::class);
    }

    public function prefix(string | Htmlable | Closure | null $label): static
    {
        $this->prefixLabel = $label;

        return $this;
    }

    public function suffix(string | Htmlable | Closure | null $label): static
    {
        $this->suffixLabel = $label;

        return $this;
    }

    public function getPrefixLabel(): string | Htmlable | null
    {
        return $this->evaluate($this->prefixLabel);
    }

    public function getSuffixLabel(): string | Htmlable | null
    {
        return $this->evaluate($this->suffixLabel);
    }
}
