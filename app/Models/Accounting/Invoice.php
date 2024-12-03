<?php

namespace App\Models\Accounting;

use App\Casts\MoneyCast;
use App\Collections\Accounting\InvoiceCollection;
use App\Concerns\Blamable;
use App\Concerns\CompanyOwned;
use App\Enums\Accounting\InvoiceStatus;
use App\Enums\Accounting\JournalEntryType;
use App\Enums\Accounting\TransactionType;
use App\Models\Common\Client;
use App\Observers\InvoiceObserver;
use Illuminate\Database\Eloquent\Attributes\CollectedBy;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

#[ObservedBy(InvoiceObserver::class)]
#[CollectedBy(InvoiceCollection::class)]
class Invoice extends Model
{
    use Blamable;
    use CompanyOwned;
    use HasFactory;

    protected $table = 'invoices';

    protected $fillable = [
        'company_id',
        'client_id',
        'logo',
        'header',
        'subheader',
        'invoice_number',
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
        'terms',
        'footer',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'date' => 'date',
        'due_date' => 'date',
        'status' => InvoiceStatus::class,
        'subtotal' => MoneyCast::class,
        'tax_total' => MoneyCast::class,
        'discount_total' => MoneyCast::class,
        'total' => MoneyCast::class,
        'amount_paid' => MoneyCast::class,
        'amount_due' => MoneyCast::class,
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
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

    public function approvalTransaction(): MorphOne
    {
        return $this->morphOne(Transaction::class, 'transactionable')
            ->where('type', TransactionType::Journal);
    }

    public function isDraft(): bool
    {
        return $this->status === InvoiceStatus::Draft;
    }

    public function canRecordPayment(): bool
    {
        return ! in_array($this->status, [
            InvoiceStatus::Draft,
            InvoiceStatus::Paid,
            InvoiceStatus::Void,
        ]);
    }

    public function canBulkRecordPayment(): bool
    {
        return ! in_array($this->status, [
            InvoiceStatus::Draft,
            InvoiceStatus::Paid,
            InvoiceStatus::Void,
            InvoiceStatus::Overpaid,
        ]);
    }

    public function canBeOverdue(): bool
    {
        return in_array($this->status, InvoiceStatus::canBeOverdue());
    }

    public static function getNextDocumentNumber(): string
    {
        $company = auth()->user()->currentCompany;

        if (! $company) {
            throw new \RuntimeException('No current company is set for the user.');
        }

        $defaultInvoiceSettings = $company->defaultInvoice;

        $numberPrefix = $defaultInvoiceSettings->number_prefix;
        $numberDigits = $defaultInvoiceSettings->number_digits;

        $latestDocument = static::query()
            ->whereNotNull('invoice_number')
            ->latest('invoice_number')
            ->first();

        $lastNumberNumericPart = $latestDocument
            ? (int) substr($latestDocument->invoice_number, strlen($numberPrefix))
            : 0;

        $numberNext = $lastNumberNumericPart + 1;

        return $defaultInvoiceSettings->getNumberNext(
            padded: true,
            format: true,
            prefix: $numberPrefix,
            digits: $numberDigits,
            next: $numberNext
        );
    }

    public function recordPayment(array $data): void
    {
        $isRefund = $this->status === InvoiceStatus::Overpaid;

        if ($isRefund) {
            $transactionType = TransactionType::Withdrawal;
            $transactionDescription = 'Refund for Overpayment on Invoice #' . $this->invoice_number;
        } else {
            $transactionType = TransactionType::Deposit;
            $transactionDescription = 'Payment for Invoice #' . $this->invoice_number;
        }

        // Create transaction
        $this->transactions()->create([
            'company_id' => $this->company_id,
            'type' => $transactionType,
            'is_payment' => true,
            'posted_at' => $data['posted_at'],
            'amount' => $data['amount'],
            'payment_method' => $data['payment_method'],
            'bank_account_id' => $data['bank_account_id'],
            'account_id' => Account::getAccountsReceivableAccount()->id,
            'description' => $transactionDescription,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    public function approveDraft(): void
    {
        if (! $this->isDraft()) {
            throw new \RuntimeException('Invoice is not in draft status.');
        }

        $transaction = $this->transactions()->create([
            'company_id' => $this->company_id,
            'type' => TransactionType::Journal,
            'posted_at' => now(),
            'amount' => $this->total,
            'description' => 'Invoice Approval for Invoice #' . $this->invoice_number,
        ]);

        $transaction->journalEntries()->create([
            'company_id' => $this->company_id,
            'type' => JournalEntryType::Debit,
            'account_id' => Account::getAccountsReceivableAccount()->id,
            'amount' => $this->total,
            'description' => $transaction->description,
        ]);

        foreach ($this->lineItems as $lineItem) {
            $transaction->journalEntries()->create([
                'company_id' => $this->company_id,
                'type' => JournalEntryType::Credit,
                'account_id' => $lineItem->offering->income_account_id,
                'amount' => $lineItem->subtotal,
                'description' => $transaction->description,
            ]);

            foreach ($lineItem->adjustments as $adjustment) {
                $transaction->journalEntries()->create([
                    'company_id' => $this->company_id,
                    'type' => $adjustment->category->isDiscount() ? JournalEntryType::Debit : JournalEntryType::Credit,
                    'account_id' => $adjustment->account_id,
                    'amount' => $lineItem->calculateAdjustmentTotal($adjustment)->getAmount(),
                    'description' => $transaction->description,
                ]);
            }
        }

        $this->update([
            'status' => InvoiceStatus::Unsent,
        ]);
    }
}
