<?php

namespace App\Models\Accounting;

use App\Casts\MoneyCast;
use App\Casts\RateCast;
use App\Collections\Accounting\DocumentCollection;
use App\Concerns\Blamable;
use App\Concerns\CompanyOwned;
use App\Enums\Accounting\AdjustmentComputation;
use App\Enums\Accounting\DayOfMonth;
use App\Enums\Accounting\DayOfWeek;
use App\Enums\Accounting\DocumentDiscountMethod;
use App\Enums\Accounting\EndType;
use App\Enums\Accounting\Frequency;
use App\Enums\Accounting\IntervalType;
use App\Enums\Accounting\Month;
use App\Enums\Accounting\RecurringInvoiceStatus;
use App\Enums\Setting\PaymentTerms;
use App\Models\Common\Client;
use App\Models\Setting\Currency;
use App\Observers\RecurringInvoiceObserver;
use Illuminate\Database\Eloquent\Attributes\CollectedBy;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

#[CollectedBy(DocumentCollection::class)]
#[ObservedBy(RecurringInvoiceObserver::class)]
class RecurringInvoice extends Model
{
    use Blamable;
    use CompanyOwned;
    use HasFactory;

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

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'recurring_invoice_id');
    }

    public function lineItems(): MorphMany
    {
        return $this->morphMany(DocumentLineItem::class, 'documentable');
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
        return $this->isDraft() && ! $this->wasApproved();
    }

    public function canBeEnded(): bool
    {
        return $this->isActive() && ! $this->wasEnded();
    }

    public function hasLineItems(): bool
    {
        return $this->lineItems()->exists();
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

            default => 'Schedule not configured'
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
    public function calculateNextDate(): ?\Carbon\Carbon
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

    public function markAsApproved(): void
    {
        $this->update([
            'approved_at' => now(),
            'status' => RecurringInvoiceStatus::Active,
        ]);
    }
}
