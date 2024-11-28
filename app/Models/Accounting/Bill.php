<?php

namespace App\Models\Accounting;

use App\Casts\MoneyCast;
use App\Concerns\Blamable;
use App\Concerns\CompanyOwned;
use App\Enums\Accounting\BillStatus;
use App\Models\Banking\Payment;
use App\Models\Common\Vendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Bill extends Model
{
    use Blamable;
    use CompanyOwned;
    use HasFactory;

    protected $table = 'bills';

    protected $fillable = [
        'company_id',
        'vendor_id',
        'bill_number',
        'order_number',
        'date',
        'due_date',
        'status',
        'currency_code',
        'subtotal',
        'tax_total',
        'discount_total',
        'total',
        'amount_paid',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'date' => 'date',
        'due_date' => 'date',
        'status' => BillStatus::class,
        'subtotal' => MoneyCast::class,
        'tax_total' => MoneyCast::class,
        'discount_total' => MoneyCast::class,
        'total' => MoneyCast::class,
        'amount_paid' => MoneyCast::class,
        'amount_due' => MoneyCast::class,
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function lineItems(): MorphMany
    {
        return $this->morphMany(DocumentLineItem::class, 'documentable');
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }
}
