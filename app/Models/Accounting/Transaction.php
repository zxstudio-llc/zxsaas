<?php

namespace App\Models\Accounting;

use App\Casts\MoneyCast;
use App\Concerns\Blamable;
use App\Concerns\CompanyOwned;
use App\Enums\Accounting\TransactionType;
use App\Models\Banking\BankAccount;
use App\Models\Common\Contact;
use App\Observers\TransactionObserver;
use Database\Factories\Accounting\TransactionFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(TransactionObserver::class)]
class Transaction extends Model
{
    use Blamable;
    use CompanyOwned;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'account_id', // Account from Chart of Accounts (Income/Expense accounts)
        'bank_account_id', // Cash/Bank Account
        'contact_id',
        'type', // 'deposit', 'withdrawal', 'journal'
        'payment_channel',
        'description',
        'notes',
        'reference',
        'amount',
        'pending',
        'reviewed',
        'posted_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'type' => TransactionType::class,
        'amount' => MoneyCast::class,
        'pending' => 'boolean',
        'reviewed' => 'boolean',
        'posted_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class, 'transaction_id');
    }

    public function isUncategorized(): bool
    {
        return $this->journalEntries->contains(fn (JournalEntry $entry) => $entry->account->isUncategorized());
    }

    protected static function newFactory(): Factory
    {
        return TransactionFactory::new();
    }
}
