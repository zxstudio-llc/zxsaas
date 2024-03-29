<?php

namespace App\Models\Accounting;

use App\Casts\MoneyCast;
use App\Enums\Accounting\JournalEntryType;
use App\Models\Banking\BankAccount;
use App\Observers\JournalEntryObserver;
use App\Traits\Blamable;
use App\Traits\CompanyOwned;
use Database\Factories\Accounting\JournalEntryFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Wallo\FilamentCompanies\FilamentCompanies;

#[ObservedBy(JournalEntryObserver::class)]
class JournalEntry extends Model
{
    use Blamable;
    use CompanyOwned;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'account_id',
        'transaction_id',
        'type',
        'amount',
        'description',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'type' => JournalEntryType::class,
        'amount' => MoneyCast::class,
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(FilamentCompanies::companyModel(), 'company_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->account()->where('accountable_type', BankAccount::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(FilamentCompanies::userModel(), 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(FilamentCompanies::userModel(), 'updated_by');
    }

    public function isUncategorized(): bool
    {
        return $this->account->isUncategorized();
    }

    public function scopeDebit(Builder $query): Builder
    {
        return $query->where('type', JournalEntryType::Debit);
    }

    public function scopeCredit(Builder $query): Builder
    {
        return $query->where('type', JournalEntryType::Credit);
    }

    protected static function newFactory(): Factory
    {
        return JournalEntryFactory::new();
    }
}
