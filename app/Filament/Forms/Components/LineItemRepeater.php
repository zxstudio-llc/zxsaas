<?php

namespace App\Filament\Forms\Components;

use Awcodes\TableRepeater\Components\TableRepeater;
use Closure;
use Filament\Forms\ComponentContainer;
use Filament\Forms\Components\Component;

class LineItemRepeater extends TableRepeater
{
    protected array | Closure $nestedSchema = [];

    protected ?string $nestedColumn = null;

    /**
     * Set nested schema and optionally the column it belongs to.
     *
     * @param  array<Component> | Closure  $components
     */
    public function withNestedSchema(array | Closure $components, ?string $underColumn = null): static
    {
        $this->nestedSchema = $components;
        $this->nestedColumn = $underColumn;

        return $this;
    }

    /**
     * Get the nested schema.
     *
     * @return array<Component>
     */
    public function getNestedSchema(): array
    {
        return $this->evaluate($this->nestedSchema);
    }

    /**
     * Get the column under which the nested schema should be rendered.
     */
    public function getNestedColumn(): ?string
    {
        return $this->nestedColumn;
    }

    /**
     * Determine if there is a nested schema defined.
     */
    public function hasNestedSchema(): bool
    {
        return ! empty($this->getNestedSchema());
    }

    public function getNestedSchemaMap(): array
    {
        return collect($this->getNestedSchema())
            ->keyBy(fn ($component) => $component->getKey())
            ->all();
    }

    /**
     * Get all child components, including nested schema.
     *
     * @return array<Component>
     */
    public function getChildComponents(): array
    {
        $components = parent::getChildComponents();

        if ($this->hasNestedSchema()) {
            $components = array_merge($components, $this->getNestedSchema());
        }

        return $components;
    }

    public function getChildComponentContainers(bool $withHidden = false): array
    {
        if ((! $withHidden) && $this->isHidden()) {
            return [];
        }

        $relationship = $this->getRelationship();

        $records = $relationship ? $this->getCachedExistingRecords() : null;

        $containers = [];

        foreach ($this->getState() ?? [] as $itemKey => $itemData) {
            $containers[$itemKey] = $this
                ->getChildComponentContainer()
                ->statePath($itemKey)
                ->model($relationship ? $records[$itemKey] ?? $this->getRelatedModel() : null)
                ->inlineLabel(false)
                ->getClone();
        }

        return $containers;
    }

    public function getChildComponentContainersWithoutNestedSchema(bool $withHidden = false): array
    {
        if ((! $withHidden) && $this->isHidden()) {
            return [];
        }

        $relationship = $this->getRelationship();
        $records = $relationship ? $this->getCachedExistingRecords() : null;

        $containers = [];

        $childComponentsWithoutNestedSchema = $this->getChildComponentsWithoutNestedSchema();

        foreach ($this->getState() ?? [] as $itemKey => $itemData) {
            $containers[$itemKey] = ComponentContainer::make($this->getLivewire())
                ->parentComponent($this)
                ->statePath($itemKey)
                ->model($relationship ? $records[$itemKey] ?? $this->getRelatedModel() : null)
                ->components($childComponentsWithoutNestedSchema)
                ->inlineLabel(false)
                ->getClone();
        }

        return $containers;
    }

    public function getChildComponentContainer($key = null): ComponentContainer
    {
        if (filled($key) && array_key_exists($key, $containers = $this->getChildComponentContainers())) {
            return $containers[$key];
        }

        return ComponentContainer::make($this->getLivewire())
            ->parentComponent($this)
            ->components($this->getChildComponents());
    }

    public function getChildComponentsWithoutNestedSchema(): array
    {
        // Fetch the nested schema components.
        $nestedSchema = $this->getNestedSchema();

        // Filter out the nested schema components.
        return array_filter($this->getChildComponents(), function ($component) use ($nestedSchema) {
            return ! in_array($component, $nestedSchema, true);
        });
    }

    public function getNestedComponents(): array
    {
        // Fetch the nested schema components.
        $nestedSchema = $this->getNestedSchema();

        // Separate and return only the nested schema components.
        return array_filter($this->getChildComponents(), function ($component) use ($nestedSchema) {
            return in_array($component, $nestedSchema, true);
        });
    }

    public function getView(): string
    {
        return 'filament.forms.components.line-item-repeater';
    }
}
