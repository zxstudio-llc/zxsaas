<?php

namespace App\Models\Accounting;

use App\Casts\MoneyCast;
use App\Concerns\Blamable;
use App\Concerns\CompanyOwned;
use App\Enums\Accounting\AdjustmentCategory;
use App\Models\Common\Offering;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class DocumentLineItem extends Model
{
    use Blamable;
    use CompanyOwned;
    use HasFactory;

    protected $table = 'document_line_items';

    protected $fillable = [
        'company_id',
        'document_id',
        'offering_id',
        'description',
        'quantity',
        'unit_price',
        'total',
        'tax_total',
        'discount_total',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'unit_price' => MoneyCast::class,
        'total' => MoneyCast::class,
        'tax_total' => MoneyCast::class,
        'discount_total' => MoneyCast::class,
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function offering(): BelongsTo
    {
        return $this->belongsTo(Offering::class);
    }

    public function adjustments(): MorphToMany
    {
        return $this->morphToMany(Adjustment::class, 'adjustmentable', 'adjustmentables');
    }

    public function taxes(): MorphToMany
    {
        return $this->adjustments()->where('category', AdjustmentCategory::Tax);
    }

    public function discounts(): MorphToMany
    {
        return $this->adjustments()->where('category', AdjustmentCategory::Discount);
    }
}
