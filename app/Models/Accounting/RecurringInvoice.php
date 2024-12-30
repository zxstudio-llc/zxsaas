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
use Illuminate\Database\Eloquent\Attributes\CollectedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[CollectedBy(DocumentCollection::class)]
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
}
