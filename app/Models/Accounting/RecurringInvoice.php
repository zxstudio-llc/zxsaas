<?php

namespace App\Models\Accounting;

use App\Casts\MoneyCast;
use App\Casts\RateCast;
use App\Collections\Accounting\DocumentCollection;
use App\Enums\Accounting\AdjustmentComputation;
use App\Enums\Accounting\DayOfMonth;
use App\Enums\Accounting\DayOfWeek;
use App\Enums\Accounting\DocumentDiscountMethod;
use App\Enums\Accounting\DocumentType;
use App\Enums\Accounting\EndType;
use App\Enums\Accounting\Frequency;
use App\Enums\Accounting\IntervalType;
use App\Enums\Accounting\Month;
use App\Enums\Accounting\RecurringInvoiceStatus;
use App\Enums\Setting\PaymentTerms;
use App\Filament\Forms\Components\CustomSection;
use App\Models\Common\Client;
use App\Models\Setting\CompanyProfile;
use App\Observers\RecurringInvoiceObserver;
use App\Utilities\Localization\Timezone;
use Filament\Actions\Action;
use Filament\Actions\MountableAction;
use Filament\Forms;
use Filament\Forms\Form;
use Guava\FilamentClusters\Forms\Cluster;
use Illuminate\Database\Eloquent\Attributes\CollectedBy;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[CollectedBy(DocumentCollection::class)]
#[ObservedBy(RecurringInvoiceObserver::class)]
class RecurringInvoice extends Document
{
    protected $table = 'recurring_invoices';

    protected $fillable = [
        'company_id',
        'client_id',
        'logo',
        'header',
        'subheader',
        'order_number',
        'payment_terms',
        'approved_at',
        'ended_at',
        'frequency',
        'interval_type',
        'interval_value',
        'month',
        'day_of_month',
        'day_of_week',
        'start_date',
        'end_type',
        'max_occurrences',
        'end_date',
        'occurrences_count',
        'timezone',
        'next_date',
        'last_date',
        'auto_send',
        'send_time',
        'status',
        'currency_code',
        'discount_method',
        'discount_computation',
        'discount_rate',
        'subtotal',
        'tax_total',
        'discount_total',
        'total',
        'terms',
        'footer',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'ended_at' => 'datetime',
        'start_date' => 'date',
        'end_date' => 'date',
        'next_date' => 'date',
        'last_date' => 'date',
        'auto_send' => 'boolean',
        'send_time' => 'datetime:H:i',
        'payment_terms' => PaymentTerms::class,
        'frequency' => Frequency::class,
        'interval_type' => IntervalType::class,
        'month' => Month::class,
        'day_of_month' => DayOfMonth::class,
        'day_of_week' => DayOfWeek::class,
        'end_type' => EndType::class,
        'status' => RecurringInvoiceStatus::class,
        'discount_method' => DocumentDiscountMethod::class,
        'discount_computation' => AdjustmentComputation::class,
        'discount_rate' => RateCast::class,
        'subtotal' => MoneyCast::class,
        'tax_total' => MoneyCast::class,
        'discount_total' => MoneyCast::class,
        'total' => MoneyCast::class,
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'recurring_invoice_id');
    }

    public function documentType(): DocumentType
    {
        return DocumentType::RecurringInvoice;
    }

    public function documentNumber(): ?string
    {
        return 'Auto-generated';
    }

    public function documentDate(): ?string
    {
        return $this->calculateNextDate()?->toDefaultDateFormat() ?? 'Auto-generated';
    }

    public function dueDate(): ?string
    {
        return $this->calculateNextDueDate()?->toDefaultDateFormat() ?? 'Auto-generated';
    }

    public function referenceNumber(): ?string
    {
        return $this->order_number;
    }

    public function amountDue(): ?string
    {
        return $this->total;
    }

    public function isDraft(): bool
    {
        return $this->status === RecurringInvoiceStatus::Draft;
    }

    public function isActive(): bool
    {
        return $this->status === RecurringInvoiceStatus::Active;
    }

    public function wasApproved(): bool
    {
        return $this->approved_at !== null;
    }

    public function wasEnded(): bool
    {
        return $this->ended_at !== null;
    }

    public function isNeverEnding(): bool
    {
        return $this->end_type === EndType::Never;
    }

    public function canBeApproved(): bool
    {
        return $this->isDraft() && $this->hasSchedule() && ! $this->wasApproved();
    }

    public function canBeEnded(): bool
    {
        return $this->isActive() && ! $this->wasEnded();
    }

    public function hasSchedule(): bool
    {
        return $this->start_date !== null;
    }

    public function getScheduleDescription(): string
    {
        $frequency = $this->frequency;

        return match (true) {
            $frequency->isDaily() => 'Repeat daily',

            $frequency->isWeekly() && $this->day_of_week => "Repeat weekly every {$this->day_of_week->getLabel()}",

            $frequency->isMonthly() && $this->day_of_month => "Repeat monthly on the {$this->day_of_month->getLabel()} day",

            $frequency->isYearly() && $this->month && $this->day_of_month => "Repeat yearly on {$this->month->getLabel()} {$this->day_of_month->getLabel()}",

            $frequency->isCustom() => $this->getCustomScheduleDescription(),

            default => 'Not Configured',
        };
    }

    private function getCustomScheduleDescription(): string
    {
        $interval = $this->interval_value > 1
            ? "{$this->interval_value} {$this->interval_type->getPluralLabel()}"
            : $this->interval_type->getSingularLabel();

        $dayDescription = match (true) {
            $this->interval_type->isWeek() && $this->day_of_week => " on {$this->day_of_week->getLabel()}",

            $this->interval_type->isMonth() && $this->day_of_month => " on the {$this->day_of_month->getLabel()} day",

            $this->interval_type->isYear() && $this->month && $this->day_of_month => " on {$this->month->getLabel()} {$this->day_of_month->getLabel()}",

            default => ''
        };

        return "Repeat every {$interval}{$dayDescription}";
    }

    /**
     * Get a human-readable description of when the schedule ends.
     */
    public function getEndDescription(): string
    {
        if (! $this->end_type) {
            return 'Not configured';
        }

        return match (true) {
            $this->end_type->isNever() => 'Never',

            $this->end_type->isAfter() && $this->max_occurrences => "After {$this->max_occurrences} " . str($this->max_occurrences === 1 ? 'invoice' : 'invoices'),

            $this->end_type->isOn() && $this->end_date => 'On ' . $this->end_date->toDefaultDateFormat(),

            default => 'Not configured'
        };
    }

    /**
     * Get the schedule timeline description.
     */
    public function getTimelineDescription(): string
    {
        $parts = [];

        if ($this->start_date) {
            $parts[] = 'First Invoice: ' . $this->start_date->toDefaultDateFormat();
        }

        if ($this->end_type) {
            $parts[] = 'Ends: ' . $this->getEndDescription();
        }

        return implode(', ', $parts);
    }

    /**
     * Get next occurrence date based on the schedule.
     */
    public function calculateNextDate(): ?Carbon
    {
        $lastDate = $this->last_date ?? $this->start_date;
        if (! $lastDate) {
            return null;
        }

        $nextDate = match (true) {
            $this->frequency->isDaily() => $lastDate->addDay(),

            $this->frequency->isWeekly() => $lastDate->addWeek(),

            $this->frequency->isMonthly() => $lastDate->addMonth(),

            $this->frequency->isYearly() => $lastDate->addYear(),

            $this->frequency->isCustom() => $this->calculateCustomNextDate($lastDate),

            default => null
        };

        // Check if we've reached the end
        if ($this->hasReachedEnd($nextDate)) {
            return null;
        }

        return $nextDate;
    }

    public function calculateNextDueDate(): ?Carbon
    {
        $nextDate = $this->calculateNextDate();
        if (! $nextDate) {
            return null;
        }

        $terms = $this->payment_terms;
        if (! $terms) {
            return $nextDate;
        }

        return $nextDate->addDays($terms->getDays());
    }

    /**
     * Calculate next date for custom intervals
     */
    protected function calculateCustomNextDate(Carbon $lastDate): ?\Carbon\Carbon
    {
        $value = $this->interval_value ?? 1;

        return match ($this->interval_type) {
            IntervalType::Day => $lastDate->addDays($value),
            IntervalType::Week => $lastDate->addWeeks($value),
            IntervalType::Month => $lastDate->addMonths($value),
            IntervalType::Year => $lastDate->addYears($value),
            default => null
        };
    }

    /**
     * Check if the schedule has reached its end
     */
    public function hasReachedEnd(?Carbon $nextDate = null): bool
    {
        if (! $this->end_type) {
            return false;
        }

        return match (true) {
            $this->end_type->isNever() => false,

            $this->end_type->isAfter() => ($this->occurrences_count ?? 0) >= ($this->max_occurrences ?? 0),

            $this->end_type->isOn() && $this->end_date && $nextDate => $nextDate->greaterThan($this->end_date),

            default => false
        };
    }

    public static function getUpdateScheduleAction(string $action = Action::class): MountableAction
    {
        return $action::make('updateSchedule')
            ->label(fn (self $record) => $record->hasSchedule() ? 'Update Schedule' : 'Set Schedule')
            ->icon('heroicon-o-calendar-date-range')
            ->slideOver()
            ->successNotificationTitle('Schedule Updated')
            ->mountUsing(function (self $record, Form $form) {
                $data = $record->attributesToArray();

                $data['day_of_month'] ??= DayOfMonth::First;
                $data['start_date'] ??= now()->addMonth()->startOfMonth();

                $form->fill($data);
            })
            ->form([
                CustomSection::make('Frequency')
                    ->contained(false)
                    ->schema([
                        Forms\Components\Select::make('frequency')
                            ->label('Repeats')
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

                        // Custom frequency fields in a nested grid
                        Cluster::make([
                            Forms\Components\TextInput::make('interval_value')
                                ->softRequired()
                                ->numeric()
                                ->default(1),
                            Forms\Components\Select::make('interval_type')
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
                            ->label('Every')
                            ->required()
                            ->markAsRequired(false)
                            ->visible(fn (Forms\Get $get) => Frequency::parse($get('frequency'))?->isCustom()),

                        // Specific schedule details
                        Forms\Components\Select::make('month')
                            ->label('Month')
                            ->options(Month::class)
                            ->softRequired()
                            ->visible(
                                fn (Forms\Get $get) => Frequency::parse($get('frequency'))->isYearly() ||
                                IntervalType::parse($get('interval_type'))?->isYear()
                            ),

                        Forms\Components\Select::make('day_of_month')
                            ->label('Day of Month')
                            ->options(DayOfMonth::class)
                            ->softRequired()
                            ->visible(
                                fn (Forms\Get $get) => Frequency::parse($get('frequency'))?->isMonthly() ||
                                Frequency::parse($get('frequency'))?->isYearly() ||
                                IntervalType::parse($get('interval_type'))?->isMonth() ||
                                IntervalType::parse($get('interval_type'))?->isYear()
                            ),

                        Forms\Components\Select::make('day_of_week')
                            ->label('Day of Week')
                            ->options(DayOfWeek::class)
                            ->softRequired()
                            ->visible(
                                fn (Forms\Get $get) => Frequency::parse($get('frequency'))?->isWeekly() ||
                                IntervalType::parse($get('interval_type'))?->isWeek()
                            ),
                    ])->columns(2),

                CustomSection::make('Dates & Time')
                    ->contained(false)
                    ->schema([
                        Forms\Components\DatePicker::make('start_date')
                            ->label('First Invoice Date')
                            ->softRequired(),

                        Forms\Components\Group::make(function (Forms\Get $get) {
                            $components = [];

                            $components[] = Forms\Components\Select::make('end_type')
                                ->label('End Schedule')
                                ->options(EndType::class)
                                ->softRequired()
                                ->live()
                                ->afterStateUpdated(function (Forms\Set $set, $state) {
                                    $endType = EndType::parse($state);

                                    if ($endType?->isNever()) {
                                        $set('max_occurrences', null);
                                        $set('end_date', null);
                                    }

                                    if ($endType?->isAfter()) {
                                        $set('max_occurrences', 1);
                                        $set('end_date', null);
                                    }

                                    if ($endType?->isOn()) {
                                        $set('max_occurrences', null);
                                        $set('end_date', now()->addMonth()->startOfMonth());
                                    }
                                });

                            $endType = EndType::parse($get('end_type'));

                            if ($endType?->isAfter()) {
                                $components[] = Forms\Components\TextInput::make('max_occurrences')
                                    ->numeric()
                                    ->suffix('invoices')
                                    ->live();
                            }

                            if ($endType?->isOn()) {
                                $components[] = Forms\Components\DatePicker::make('end_date')
                                    ->live();
                            }

                            return [
                                Cluster::make($components)
                                    ->label('Schedule Ends')
                                    ->required()
                                    ->markAsRequired(false),
                            ];
                        }),

                        Forms\Components\Select::make('timezone')
                            ->options(Timezone::getTimezoneOptions(CompanyProfile::first()->country))
                            ->searchable()
                            ->softRequired(),
                    ])
                    ->columns(2),
            ])
            ->action(function (self $record, array $data, MountableAction $action) {
                $record->update($data);

                $action->success();
            });
    }

    public static function getApproveDraftAction(string $action = Action::class): MountableAction
    {
        return $action::make('approveDraft')
            ->label('Approve')
            ->icon('heroicon-o-check-circle')
            ->visible(function (self $record) {
                return $record->canBeApproved();
            })
            ->databaseTransaction()
            ->successNotificationTitle('Recurring Invoice Approved')
            ->action(function (self $record, MountableAction $action) {
                $record->approveDraft();

                $action->success();
            });
    }

    public function approveDraft(?Carbon $approvedAt = null): void
    {
        if (! $this->isDraft()) {
            throw new \RuntimeException('Invoice is not in draft status.');
        }

        $approvedAt ??= now();

        $this->update([
            'approved_at' => $approvedAt,
            'status' => RecurringInvoiceStatus::Active,
        ]);
    }
}
