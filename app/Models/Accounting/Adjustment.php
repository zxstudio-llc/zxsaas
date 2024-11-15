<?php

namespace App\Models\Accounting;

use App\Casts\RateCast;
use App\Concerns\Blamable;
use App\Concerns\CompanyOwned;
use App\Concerns\HasDefault;
use App\Enums\Accounting\AdjustmentCategory;
use App\Enums\Accounting\AdjustmentComputation;
use App\Enums\Accounting\AdjustmentScope;
use App\Enums\Accounting\AdjustmentType;
use Database\Factories\Accounting\AdjustmentFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Adjustment extends Model
{
    use Blamable;
    use CompanyOwned;
    use HasDefault;
    use HasFactory;

    protected $table = 'adjustments';

    protected $fillable = [
        'company_id',
        'account_id',
        'category',
        'type',
        'rate',
        'computation',
        'scope',
        'start_date',
        'end_date',
        'enabled',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'category' => AdjustmentCategory::class,
        'type' => AdjustmentType::class,
        'rate' => RateCast::class,
        'computation' => AdjustmentComputation::class,
        'scope' => AdjustmentScope::class,
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'enabled' => 'boolean',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function adjustmentables(): MorphTo
    {
        return $this->morphTo();
    }

    protected static function newFactory(): Factory
    {
        return AdjustmentFactory::new();
    }
}
