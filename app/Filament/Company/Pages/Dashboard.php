<?php

namespace App\Filament\Company\Pages;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Pages\Dashboard\Actions\FilterAction;
use Filament\Pages\Dashboard\Concerns\HasFiltersAction;

class Dashboard extends \Filament\Pages\Dashboard
{
    use HasFiltersAction;

    //    public function filtersForm(Form $form): Form
    //    {
    //        return $form
    //            ->schema([
    //                Section::make()
    //                    ->schema([
    //                        DatePicker::make('startDate'),
    //                        DatePicker::make('endDate'),
    //                        // ...
    //                    ])
    //                    ->columns(3),
    //            ]);
    //    }

    protected function getHeaderActions(): array
    {
        return [
            FilterAction::make()
                ->form([
                    DatePicker::make('startDate')
                        ->live()
                        ->afterStateUpdated(fn (Set $set) => $set('endDate', now()->toDateTimeString())),
                    DatePicker::make('endDate'),
                    // ...
                ]),
        ];
    }
}
