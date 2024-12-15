<?php

namespace App\Filament\Forms\Components;

use App\Enums\Accounting\AdjustmentComputation;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;

class BillTotals extends Grid
{
    protected string $view = 'filament.forms.components.bill-totals';

    protected function setUp(): void
    {
        parent::setUp();

        $this->schema([
            TextInput::make('discount_rate')
                ->label('Discount Rate')
                ->hiddenLabel()
                ->live()
                ->rate(computation: static fn (Get $get) => $get('discount_computation'), showAffix: false),
            Select::make('discount_computation')
                ->label('Discount Computation')
                ->hiddenLabel()
                ->options([
                    'percentage' => '%',
                    'fixed' => '$',
                ])
                ->default(AdjustmentComputation::Percentage)
                ->selectablePlaceholder(false)
                ->live(),
        ]);
    }
}
