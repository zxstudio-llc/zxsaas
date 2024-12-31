<?php

namespace App\Filament\Company\Resources\Sales\RecurringInvoiceResource\Pages;

use App\Enums\Accounting\DayOfMonth;
use App\Enums\Accounting\DayOfWeek;
use App\Enums\Accounting\EndType;
use App\Enums\Accounting\Frequency;
use App\Enums\Accounting\IntervalType;
use App\Enums\Accounting\Month;
use App\Filament\Company\Resources\Sales\RecurringInvoiceResource;
use App\Filament\Forms\Components\LabeledField;
use App\Models\Setting\CompanyProfile;
use App\Utilities\Localization\Timezone;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\MaxWidth;
use Guava\FilamentClusters\Forms\Cluster;

class ViewRecurringInvoice extends ViewRecord
{
    protected static string $resource = RecurringInvoiceResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['day_of_month'] ??= DayOfMonth::First;
        $data['start_date'] ??= now()->addMonth()->startOfMonth();

        return $data;
    }

    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::SixExtraLarge;
    }

    public function form(Form $form): Form
    {
        return $form
            ->disabled(false)
            ->schema([
                Forms\Components\Section::make('Scheduling')
                    ->schema([
                        Forms\Components\Group::make([
                            Forms\Components\Select::make('frequency')
                                ->label('Repeat this invoice')
                                ->inlineLabel()
                                ->options(Frequency::class)
                                ->softRequired()
                                ->live()
                                ->afterStateUpdated(function (Forms\Set $set, $state) {
                                    $frequency = Frequency::parse($state);

                                    if ($frequency->isDaily()) {
                                        $set('interval_value', null);
                                        $set('interval_type', null);
                                    }

                                    if ($frequency->isWeekly()) {
                                        $currentDayOfWeek = now()->dayOfWeek;
                                        $currentDayOfWeek = DayOfWeek::parse($currentDayOfWeek);
                                        $set('day_of_week', $currentDayOfWeek);
                                        $set('interval_value', null);
                                        $set('interval_type', null);
                                    }

                                    if ($frequency->isMonthly()) {
                                        $set('day_of_month', DayOfMonth::First);
                                        $set('interval_value', null);
                                        $set('interval_type', null);
                                    }

                                    if ($frequency->isYearly()) {
                                        $currentMonth = now()->month;
                                        $currentMonth = Month::parse($currentMonth);
                                        $set('month', $currentMonth);

                                        $currentDay = now()->dayOfMonth;
                                        $currentDay = DayOfMonth::parse($currentDay);
                                        $set('day_of_month', $currentDay);

                                        $set('interval_value', null);
                                        $set('interval_type', null);
                                    }

                                    if ($frequency->isCustom()) {
                                        $set('interval_value', 1);
                                        $set('interval_type', IntervalType::Month);

                                        $currentDay = now()->dayOfMonth;
                                        $currentDay = DayOfMonth::parse($currentDay);
                                        $set('day_of_month', $currentDay);
                                    }
                                }),

                            // Custom frequency fields

                            LabeledField::make()
                                ->prefix('every')
                                ->schema([
                                    Cluster::make([
                                        Forms\Components\TextInput::make('interval_value')
                                            ->label('every')
                                            ->numeric()
                                            ->default(1),
                                        Forms\Components\Select::make('interval_type')
                                            ->label('Interval Type')
                                            ->options(IntervalType::class)
                                            ->softRequired()
                                            ->default(IntervalType::Month)
                                            ->live()
                                            ->afterStateUpdated(function (Forms\Set $set, $state) {
                                                $intervalType = IntervalType::parse($state);

                                                if ($intervalType->isWeek()) {
                                                    $currentDayOfWeek = now()->dayOfWeek;
                                                    $currentDayOfWeek = DayOfWeek::parse($currentDayOfWeek);
                                                    $set('day_of_week', $currentDayOfWeek);
                                                }

                                                if ($intervalType->isMonth()) {
                                                    $currentDay = now()->dayOfMonth;
                                                    $currentDay = DayOfMonth::parse($currentDay);
                                                    $set('day_of_month', $currentDay);
                                                }

                                                if ($intervalType->isYear()) {
                                                    $currentMonth = now()->month;
                                                    $currentMonth = Month::parse($currentMonth);
                                                    $set('month', $currentMonth);

                                                    $currentDay = now()->dayOfMonth;
                                                    $currentDay = DayOfMonth::parse($currentDay);
                                                    $set('day_of_month', $currentDay);
                                                }
                                            }),
                                    ])
                                        ->live()
                                        ->hiddenLabel(),
                                ])
                                ->visible(fn (Forms\Get $get) => Frequency::parse($get('frequency'))->isCustom()),

                            LabeledField::make()
                                ->prefix(function (Forms\Get $get) {
                                    $frequency = Frequency::parse($get('frequency'));
                                    $intervalType = IntervalType::parse($get('interval_type'));

                                    if ($frequency->isYearly()) {
                                        return 'every';
                                    }

                                    if ($frequency->isCustom() && $intervalType?->isYear()) {
                                        return 'in';
                                    }

                                    return null;
                                })
                                ->schema([
                                    Forms\Components\Select::make('month')
                                        ->hiddenLabel()
                                        ->options(Month::class)
                                        ->live()
                                        ->softRequired(),
                                ])
                                ->visible(fn (Forms\Get $get) => Frequency::parse($get('frequency'))->isYearly() || IntervalType::parse($get('interval_type'))?->isYear()),

                            LabeledField::make()
                                ->prefix('on the')
                                ->suffix(function (Forms\Get $get) {
                                    $frequency = Frequency::parse($get('frequency'));
                                    $intervalType = IntervalType::parse($get('interval_type'));

                                    if ($frequency->isMonthly()) {
                                        return 'day of every month';
                                    }

                                    if ($frequency->isYearly() || ($frequency->isCustom() && $intervalType->isMonth()) || ($frequency->isCustom() && $intervalType->isYear())) {
                                        return 'day of the month';
                                    }

                                    return null;
                                })
                                ->schema([
                                    Forms\Components\Select::make('day_of_month')
                                        ->hiddenLabel()
                                        ->inlineLabel()
                                        ->options(DayOfMonth::class)
                                        ->live()
                                        ->softRequired(),
                                ])
                                ->visible(fn (Forms\Get $get) => Frequency::parse($get('frequency'))?->isMonthly() || Frequency::parse($get('frequency'))?->isYearly() || IntervalType::parse($get('interval_type'))?->isMonth() || IntervalType::parse($get('interval_type'))?->isYear()),

                            LabeledField::make()
                                ->prefix(function (Forms\Get $get) {
                                    $frequency = Frequency::parse($get('frequency'));
                                    $intervalType = IntervalType::parse($get('interval_type'));

                                    if ($frequency->isWeekly()) {
                                        return 'every';
                                    }

                                    if ($frequency->isCustom() && $intervalType->isWeek()) {
                                        return 'on';
                                    }

                                    return null;
                                })
                                ->schema([
                                    Forms\Components\Select::make('day_of_week')
                                        ->hiddenLabel()
                                        ->options(DayOfWeek::class)
                                        ->live()
                                        ->softRequired(),
                                ])
                                ->visible(fn (Forms\Get $get) => Frequency::parse($get('frequency'))?->isWeekly() || IntervalType::parse($get('interval_type'))?->isWeek()),
                        ])->columns(2),

                        Forms\Components\Group::make([
                            Forms\Components\DatePicker::make('start_date')
                                ->label('Create first invoice on')
                                ->inlineLabel()
                                ->softRequired(),

                            LabeledField::make()
                                ->prefix('and end')
                                ->suffix(function (Forms\Get $get) {
                                    $endType = EndType::parse($get('end_type'));

                                    if ($endType->isAfter()) {
                                        return 'invoices';
                                    }

                                    return null;
                                })
                                ->schema(function (Forms\Get $get) {
                                    $components = [];

                                    $components[] = Forms\Components\Select::make('end_type')
                                        ->hiddenLabel()
                                        ->options(EndType::class)
                                        ->softRequired()
                                        ->live()
                                        ->afterStateUpdated(function (Forms\Set $set, $state) {
                                            $endType = EndType::parse($state);

                                            if ($endType->isNever()) {
                                                $set('max_occurrences', null);
                                                $set('end_date', null);
                                            }

                                            if ($endType->isAfter()) {
                                                $set('max_occurrences', 1);
                                                $set('end_date', null);
                                            }

                                            if ($endType->isOn()) {
                                                $set('max_occurrences', null);
                                                $set('end_date', now()->addMonth()->startOfMonth());
                                            }
                                        });

                                    $endType = EndType::parse($get('end_type'));

                                    if ($endType->isAfter()) {
                                        $components[] = Forms\Components\TextInput::make('max_occurrences')
                                            ->numeric()
                                            ->live();
                                    }

                                    if ($endType->isOn()) {
                                        $components[] = Forms\Components\DatePicker::make('end_date')
                                            ->live();
                                    }

                                    return [
                                        Cluster::make($components)
                                            ->hiddenLabel(),
                                    ];
                                }),
                        ])->columns(2),

                        Forms\Components\Group::make([
                            LabeledField::make()
                                ->prefix('Create in')
                                ->suffix('time zone')
                                ->schema([
                                    Forms\Components\Select::make('timezone')
                                        ->softRequired()
                                        ->hiddenLabel()
                                        ->options(Timezone::getTimezoneOptions(CompanyProfile::first()->country))
                                        ->searchable(),
                                ])
                                ->columns(1),
                        ])->columns(2),
                    ])
                    ->headerActions([
                        Forms\Components\Actions\Action::make('save')
                            ->label('Save')
                            ->button()
                            ->successNotificationTitle('Scheduling saved')
                            ->action(function (Forms\Components\Actions\Action $action) {
                                $this->save();

                                $action->success();
                            }),
                    ]),
            ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();

        $this->getRecord()->update($state);
    }
}
