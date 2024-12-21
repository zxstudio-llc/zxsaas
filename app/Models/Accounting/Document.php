<?php

namespace App\Models\Accounting;

use App\Casts\MoneyCast;
use App\Casts\RateCast;
use App\Collections\Accounting\DocumentCollection;
use App\Concerns\Blamable;
use App\Concerns\CompanyOwned;
use App\Enums\Accounting\AdjustmentComputation;
use App\Enums\Accounting\DocumentDiscountMethod;
use App\Enums\Accounting\DocumentType;
use App\Enums\Accounting\TransactionType;
use App\Models\Banking\BankAccount;
use App\Models\Setting\Currency;
use App\Models\Setting\DocumentDefault;
use App\Utilities\Currency\CurrencyAccessor;
use App\Utilities\Currency\CurrencyConverter;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

abstract class Document extends Model
{
    use Blamable;
    use CompanyOwned;
    use HasFactory;

    protected const COMMON_FILLABLE = [
        'company_id',
        'order_number',
        'date',
        'due_date',
        'paid_at',
        'status',
        'currency_code',
        'discount_method',
        'discount_computation',
        'discount_rate',
        'subtotal',
        'tax_total',
        'discount_total',
        'total',
        'amount_paid',
        'created_by',
        'updated_by',
    ];

    protected $fillable = self::COMMON_FILLABLE;

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'due_date' => 'date',
            'paid_at' => 'datetime',
            'discount_method' => DocumentDiscountMethod::class,
            'discount_computation' => AdjustmentComputation::class,
            'discount_rate' => RateCast::class,
            'subtotal' => MoneyCast::class,
            'tax_total' => MoneyCast::class,
            'discount_total' => MoneyCast::class,
            'total' => MoneyCast::class,
            'amount_paid' => MoneyCast::class,
            'amount_due' => MoneyCast::class,
        ];
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    public function lineItems(): MorphMany
    {
        return $this->morphMany(DocumentLineItem::class, 'documentable');
    }

    public function transactions(): MorphMany
    {
        return $this->morphMany(Transaction::class, 'transactionable');
    }

    public function payments(): MorphMany
    {
        return $this->transactions()->where('is_payment', true);
    }

    public function deposits(): MorphMany
    {
        return $this->transactions()->where('type', TransactionType::Deposit)->where('is_payment', true);
    }

    public function withdrawals(): MorphMany
    {
        return $this->transactions()->where('type', TransactionType::Withdrawal)->where('is_payment', true);
    }

    public function hasPayments(): bool
    {
        return $this->payments->isNotEmpty();
    }

    protected function isCurrentlyOverdue(): Attribute
    {
        return Attribute::get(function () {
            return $this->due_date->isBefore(today()) && $this->canBeOverdue();
        });
    }

    public static function getNextDocumentNumber(): string
    {
        $company = auth()->user()->currentCompany;

        if (! $company) {
            throw new \RuntimeException('No current company is set for the user.');
        }

        $settings = static::getDocumentSettings();

        $prefix = $settings->number_prefix;
        $digits = $settings->number_digits;

        $latestDocument = static::query()
            ->whereNotNull(static::documentNumberColumn())
            ->latest(static::documentNumberColumn())
            ->first();

        $lastNumber = $latestDocument
            ? (int) substr($latestDocument->{static::documentNumberColumn()}, strlen($prefix))
            : 0;

        $numberNext = $lastNumber + 1;

        return $settings->getNumberNext(
            padded: true,
            format: true,
            prefix: $prefix,
            digits: $digits,
            next: $numberNext
        );
    }

    protected function recordTransaction(array $data, string $transactionType, string $transactionDescription, int $accountId): void
    {
        $formattedAmount = $this->prepareAmountForTransaction($data);

        $this->transactions()->create([
            'company_id' => $this->company_id,
            'type' => $transactionType,
            'is_payment' => true,
            'posted_at' => $data['posted_at'],
            'amount' => $formattedAmount,
            'payment_method' => $data['payment_method'],
            'bank_account_id' => $data['bank_account_id'],
            'account_id' => $accountId,
            'description' => $transactionDescription,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    protected function prepareAmountForTransaction(array $data): string
    {
        $bankAccount = BankAccount::findOrFail($data['bank_account_id']);
        $bankAccountCurrency = $bankAccount->account->currency_code ?? CurrencyAccessor::getDefaultCurrency();

        $requiresConversion = $this->currency_code !== $bankAccountCurrency;

        if ($requiresConversion) {
            $amountInCents = CurrencyConverter::convertToCents($data['amount'], $this->currency_code);
            $convertedAmount = CurrencyConverter::convertBalance(
                $amountInCents,
                $this->currency_code,
                $bankAccountCurrency
            );

            return CurrencyConverter::convertCentsToFormatSimple($convertedAmount, $bankAccountCurrency);
        }

        return $data['amount'];
    }

    public function convertAmountToDefaultCurrency(int $amountCents): int
    {
        $defaultCurrency = CurrencyAccessor::getDefaultCurrency();
        $needsConversion = $this->currency_code !== $defaultCurrency;

        if ($needsConversion) {
            return CurrencyConverter::convertBalance($amountCents, $this->currency_code, $defaultCurrency);
        }

        return $amountCents;
    }

    public function formatAmountToDefaultCurrency(int $amountCents): string
    {
        $convertedCents = $this->convertAmountToDefaultCurrency($amountCents);

        return CurrencyConverter::convertCentsToFormatSimple($convertedCents);
    }

    /**
     * Create a new Eloquent Collection instance.
     *
     * @param  array<int, Model>  $models
     * @return Collection<int, Model>
     */
    public function newCollection(array $models = []): Collection
    {
        return new DocumentCollection($models);
    }

    abstract public static function documentType(): DocumentType;

    abstract public static function documentNumberColumn(): string;

    abstract public static function getDocumentSettings(): DocumentDefault;

    abstract public function canBeOverdue(): bool;
}
