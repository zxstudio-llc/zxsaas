<?php

namespace App\Filament\Company\Pages\Concerns;

use Filament\Actions\Action;
use Filament\Forms\Form;
use Filament\Support\Enums\ActionSize;
use Filament\Support\Facades\FilamentIcon;

trait HasToggleTableColumnForm
{
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

    public function toggleColumnsAction(): Action
    {
        return Action::make('toggleColumns')
            ->label(__('filament-tables::table.actions.toggle_columns.label'))
            ->iconButton()
            ->size(ActionSize::Large)
            ->icon(FilamentIcon::resolve('tables::actions.toggle-columns') ?? 'heroicon-m-view-columns')
            ->color('gray');
    }
}
