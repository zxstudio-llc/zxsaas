<?php

namespace App\Models\Common;

use App\Concerns\CompanyOwned;
use App\Models\Accounting\Account;
use App\Models\Setting\Discount;
use App\Models\Setting\Tax;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Offering extends Model
{
    use CompanyOwned;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'type',
        'price',
        'income_account_id',
        'expense_account_id',
    ];

    public function incomeAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'income_account_id');
    }

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'expense_account_id');
    }

    public function taxes(): MorphToMany
    {
        return $this->morphToMany(Tax::class, 'adjustmentable', 'adjustmentables')
            ->wherePivot('adjustment_type', Tax::class);
    }

    public function discounts(): MorphToMany
    {
        return $this->morphToMany(Discount::class, 'adjustmentable', 'adjustmentables')
            ->wherePivot('adjustment_type', Discount::class);
    }
}
