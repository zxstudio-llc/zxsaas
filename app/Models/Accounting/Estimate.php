<?php

namespace App\Models\Accounting;

use App\Casts\MoneyCast;
use App\Casts\RateCast;
use App\Collections\Accounting\DocumentCollection;
use App\Concerns\Blamable;
use App\Concerns\CompanyOwned;
use App\Enums\Accounting\AdjustmentComputation;
use App\Enums\Accounting\DocumentDiscountMethod;
use App\Enums\Accounting\EstimateStatus;
use App\Models\Common\Client;
use App\Models\Setting\Currency;
use Illuminate\Database\Eloquent\Attributes\CollectedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

#[CollectedBy(DocumentCollection::class)]
class Estimate extends Model
{
    use Blamable;
    use CompanyOwned;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'client_id',
        'logo',
        'header',
        'subheader',
        'estimate_number',
        'reference_number',
        'date',
        'expiration_date',
        'approved_at',
        'accepted_at',
        'declined_at',
        'last_sent_at',
        'last_viewed_at',
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
        'date' => 'date',
        'expiration_date' => 'date',
        'approved_at' => 'datetime',
        'accepted_at' => 'datetime',
        'declined_at' => 'datetime',
        'last_sent_at' => 'datetime',
        'last_viewed_at' => 'datetime',
        'status' => EstimateStatus::class,
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

    public function lineItems(): MorphMany
    {
        return $this->morphMany(DocumentLineItem::class, 'documentable');
    }

    protected function isCurrentlyExpired(): Attribute
    {
        return Attribute::get(function () {
            return $this->expiration_date?->isBefore(today()) && $this->canBeExpired();
        });
    }

    public function isDraft(): bool
    {
        return $this->status === EstimateStatus::Draft;
    }

    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function isDeclined(): bool
    {
        return $this->declined_at !== null;
    }

    public function isSent(): bool
    {
        return $this->last_sent_at !== null;
    }

    public function canBeExpired(): bool
    {
        return ! in_array($this->status, [
            EstimateStatus::Draft,
            EstimateStatus::Accepted,
            EstimateStatus::Declined,
            EstimateStatus::Converted,
        ]);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            EstimateStatus::Unsent,
            EstimateStatus::Sent,
            EstimateStatus::Viewed,
            EstimateStatus::Accepted,
        ]);
    }

    public static function getNextDocumentNumber(): string
    {
        $company = auth()->user()->currentCompany;

        if (! $company) {
            throw new \RuntimeException('No current company is set for the user.');
        }

        $defaultEstimateSettings = $company->defaultInvoice;

        $numberPrefix = 'EST-';
        $numberDigits = $defaultEstimateSettings->number_digits;

        $latestDocument = static::query()
            ->whereNotNull('estimate_number')
            ->latest('estimate_number')
            ->first();

        $lastNumberNumericPart = $latestDocument
            ? (int) substr($latestDocument->estimate_number, strlen($numberPrefix))
            : 0;

        $numberNext = $lastNumberNumericPart + 1;

        return $defaultEstimateSettings->getNumberNext(
            padded: true,
            format: true,
            prefix: $numberPrefix,
            digits: $numberDigits,
            next: $numberNext
        );
    }

    public function approveDraft(?Carbon $approvedAt = null): void
    {
        if (! $this->isDraft()) {
            throw new \RuntimeException('Invoice is not in draft status.');
        }

        $approvedAt ??= now();

        $this->update([
            'approved_at' => $approvedAt,
            'status' => EstimateStatus::Unsent,
        ]);
    }
}
