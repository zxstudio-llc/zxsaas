<?php

namespace App\Models\Accounting;

use App\Casts\MoneyCast;
use App\Casts\RateCast;
use App\Concerns\Blamable;
use App\Concerns\CompanyOwned;
use App\Enums\Accounting\AdjustmentComputation;
use App\Enums\Accounting\DocumentDiscountMethod;
use App\Enums\Accounting\EstimateStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Estimate extends Model
{
    use Blamable;
    use CompanyOwned;
    use HasFactory;

    protected $fillable = [
        'client_id',
        'logo',
        'header',
        'subheader',
        'number',
        'reference_number',
        'date',
        'expiry_date',
        'approved_at',
        'accepted_at',
        'last_sent',
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
    ];

    protected $casts = [
        'date' => 'date',
        'expiry_date' => 'date',
        'approved_at' => 'datetime',
        'accepted_at' => 'datetime',
        'last_sent' => 'datetime',
        'status' => EstimateStatus::class,
        'discount_method' => DocumentDiscountMethod::class,
        'discount_computation' => AdjustmentComputation::class,
        'discount_rate' => RateCast::class,
        'subtotal' => MoneyCast::class,
        'tax_total' => MoneyCast::class,
        'discount_total' => MoneyCast::class,
        'total' => MoneyCast::class,
    ];
}
