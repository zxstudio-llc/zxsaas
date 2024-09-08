<?php

namespace App\Filament\Company\Pages\Concerns;

use App\Support\Column;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Form;
use Filament\Support\Enums\ActionSize;
use Filament\Support\Facades\FilamentIcon;
use Livewire\Attributes\Session;

trait HasToggleTableColumnForm
{
    #[Session]
    public array $toggledTableColumns = [];

    public function mountHasToggleTableColumnForm(): void
    {
        $this->loadDefaultTableColumnToggleState();
    }

    protected function getHasToggleTableColumnFormForms(): array
    {
        return [
            'toggleTableColumnForm' => $this->getToggleTableColumnForm(),
        ];
    }

    public function getToggleTableColumnForm(): Form
    {
        return $this->toggleTableColumnForm($this->makeForm()
            ->statePath('toggledTableColumns'));
    }

    public function toggleTableColumnForm(Form $form): Form
    {
        return $form;
    }

    protected function hasToggleableColumns(): bool
    {
        return ! empty($this->getTableColumnToggleFormSchema());
    }

    /**
     * @return array<Checkbox>
     */
    protected function getTableColumnToggleFormSchema(): array
    {
        $schema = [];

        foreach ($this->getTable() as $column) {
            if ($column->isToggleable()) {
                $schema[] = Checkbox::make($column->getName())
                    ->label($column->getLabel());
            }
        }

        return $schema;
    }

    public function toggleColumnsAction(): Action
    {
        return Action::make('toggleColumns')
            ->label(__('filament-tables::table.actions.toggle_columns.label'))
            ->iconButton()
            ->size(ActionSize::Large)
            ->icon(FilamentIcon::resolve('tables::actions.toggle-columns') ?? 'heroicon-m-view-columns')
            ->color('gray');
    }

    protected function loadDefaultTableColumnToggleState(): void
    {
        $tableColumns = $this->getTable();

        foreach ($tableColumns as $column) {
            $columnName = $column->getName();

            if (empty($this->toggledTableColumns)) {
                if ($column->isToggleable()) {
                    $this->toggledTableColumns[$columnName] = ! $column->isToggledHiddenByDefault();
                } else {
                    $this->toggledTableColumns[$columnName] = true;
                }
            }

            // Handle cases where the toggle state needs to be reset
            if (! $column->isToggleable()) {
                $this->toggledTableColumns[$columnName] = true;
            } elseif ($column->isToggleable() && $column->isToggledHiddenByDefault() && isset($this->toggledTableColumns[$columnName]) && $this->toggledTableColumns[$columnName]) {
                $this->toggledTableColumns[$columnName] = false;
            }
        }
    }

    protected function getToggledColumns(): array
    {
        return array_values(
            array_filter(
                $this->getTable(),
                fn (Column $column) => $this->toggledTableColumns[$column->getName()] ?? false,
            )
        );
    }
}
