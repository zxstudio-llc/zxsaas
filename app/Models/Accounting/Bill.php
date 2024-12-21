<?php

namespace App\Models\Accounting;

use App\Enums\Accounting\BillStatus;
use App\Enums\Accounting\DocumentType;
use App\Enums\Accounting\JournalEntryType;
use App\Enums\Accounting\TransactionType;
use App\Filament\Company\Resources\Purchases\BillResource;
use App\Models\Common\Vendor;
use App\Models\Setting\DocumentDefault;
use App\Observers\BillObserver;
use App\Utilities\Currency\CurrencyAccessor;
use App\Utilities\Currency\CurrencyConverter;
use Filament\Actions\MountableAction;
use Filament\Actions\ReplicateAction;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;

#[ObservedBy(BillObserver::class)]
class Bill extends Document
{
    protected $table = 'bills';

    protected $fillable = [
        ...self::COMMON_FILLABLE,
        ...self::BILL_FILLABLE,
    ];

    protected const BILL_FILLABLE = [
        'vendor_id',
        'bill_number',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'status' => BillStatus::class,
        ];
    }

    public static function documentNumberColumn(): string
    {
        return 'bill_number';
    }

    public static function documentType(): DocumentType
    {
        return DocumentType::Bill;
    }

    public static function getDocumentSettings(): DocumentDefault
    {
        return auth()->user()->currentCompany->defaultBill;
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function initialTransaction(): MorphOne
    {
        return $this->morphOne(Transaction::class, 'transactionable')
            ->where('type', TransactionType::Journal);
    }

    public function canBeOverdue(): bool
    {
        return in_array($this->status, BillStatus::canBeOverdue());
    }

    public function canRecordPayment(): bool
    {
        return ! in_array($this->status, [
            BillStatus::Paid,
            BillStatus::Void,
        ]) && $this->currency_code === CurrencyAccessor::getDefaultCurrency();
    }

    public function hasInitialTransaction(): bool
    {
        return $this->initialTransaction()->exists();
    }

    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->whereIn('status', [
            BillStatus::Unpaid,
            BillStatus::Partial,
            BillStatus::Overdue,
        ]);
    }

    public function recordPayment(array $data): void
    {
        $transactionDescription = "Bill #{$this->bill_number}: Payment to {$this->vendor->name}";

        $this->recordTransaction(
            $data,
            TransactionType::Withdrawal, // Always withdrawal for bills
            $transactionDescription,
            Account::getAccountsPayableAccount()->id // Account ID specific to bills
        );
    }

    public function createInitialTransaction(?Carbon $postedAt = null): void
    {
        $postedAt ??= $this->date;

        $total = $this->formatAmountToDefaultCurrency($this->getRawOriginal('total'));

        $transaction = $this->transactions()->create([
            'company_id' => $this->company_id,
            'type' => TransactionType::Journal,
            'posted_at' => $postedAt,
            'amount' => $total,
            'description' => 'Bill Creation for Bill #' . $this->bill_number,
        ]);

        $baseDescription = "{$this->vendor->name}: Bill #{$this->bill_number}";

        $transaction->journalEntries()->create([
            'company_id' => $this->company_id,
            'type' => JournalEntryType::Credit,
            'account_id' => Account::getAccountsPayableAccount()->id,
            'amount' => $total,
            'description' => $baseDescription,
        ]);

        $totalLineItemSubtotalCents = $this->convertAmountToDefaultCurrency((int) $this->lineItems()->sum('subtotal'));
        $billDiscountTotalCents = $this->convertAmountToDefaultCurrency((int) $this->getRawOriginal('discount_total'));
        $remainingDiscountCents = $billDiscountTotalCents;

        foreach ($this->lineItems as $index => $lineItem) {
            $lineItemDescription = "{$baseDescription} › {$lineItem->offering->name}";

            $lineItemSubtotal = $this->formatAmountToDefaultCurrency($lineItem->getRawOriginal('subtotal'));

            $transaction->journalEntries()->create([
                'company_id' => $this->company_id,
                'type' => JournalEntryType::Debit,
                'account_id' => $lineItem->offering->expense_account_id,
                'amount' => $lineItemSubtotal,
                'description' => $lineItemDescription,
            ]);

            foreach ($lineItem->adjustments as $adjustment) {
                $adjustmentAmount = $this->formatAmountToDefaultCurrency($lineItem->calculateAdjustmentTotalAmount($adjustment));

                if ($adjustment->isNonRecoverablePurchaseTax()) {
                    $transaction->journalEntries()->create([
                        'company_id' => $this->company_id,
                        'type' => JournalEntryType::Debit,
                        'account_id' => $lineItem->offering->expense_account_id,
                        'amount' => $adjustmentAmount,
                        'description' => "{$lineItemDescription} ({$adjustment->name})",
                    ]);
                } elseif ($adjustment->account_id) {
                    $transaction->journalEntries()->create([
                        'company_id' => $this->company_id,
                        'type' => $adjustment->category->isDiscount() ? JournalEntryType::Credit : JournalEntryType::Debit,
                        'account_id' => $adjustment->account_id,
                        'amount' => $adjustmentAmount,
                        'description' => $lineItemDescription,
                    ]);
                }
            }

            if ($this->discount_method->isPerDocument() && $totalLineItemSubtotalCents > 0) {
                $lineItemSubtotalCents = $this->convertAmountToDefaultCurrency((int) $lineItem->getRawOriginal('subtotal'));

                if ($index === $this->lineItems->count() - 1) {
                    $lineItemDiscount = $remainingDiscountCents;
                } else {
                    $lineItemDiscount = (int) round(
                        ($lineItemSubtotalCents / $totalLineItemSubtotalCents) * $billDiscountTotalCents
                    );
                    $remainingDiscountCents -= $lineItemDiscount;
                }

                if ($lineItemDiscount > 0) {
                    $transaction->journalEntries()->create([
                        'company_id' => $this->company_id,
                        'type' => JournalEntryType::Credit,
                        'account_id' => Account::getPurchaseDiscountAccount()->id,
                        'amount' => CurrencyConverter::convertCentsToFormatSimple($lineItemDiscount),
                        'description' => "{$lineItemDescription} (Proportional Discount)",
                    ]);
                }
            }
        }
    }

    public function updateInitialTransaction(): void
    {
        $transaction = $this->initialTransaction;

        if ($transaction) {
            $transaction->delete();
        }

        $this->createInitialTransaction();
    }

    public static function getReplicateAction(string $action = ReplicateAction::class): MountableAction
    {
        return $action::make()
            ->excludeAttributes([
                'status',
                'amount_paid',
                'amount_due',
                'created_by',
                'updated_by',
                'created_at',
                'updated_at',
                'bill_number',
                'date',
                'due_date',
                'paid_at',
            ])
            ->modal(false)
            ->beforeReplicaSaved(function (self $original, self $replica) {
                $replica->status = BillStatus::Unpaid;
                $replica->bill_number = self::getNextDocumentNumber();
                $replica->date = now();
                $replica->due_date = now()->addDays($original->company->defaultBill->payment_terms->getDays());
            })
            ->databaseTransaction()
            ->after(function (self $original, self $replica) {
                $original->lineItems->each(function (DocumentLineItem $lineItem) use ($replica) {
                    $replicaLineItem = $lineItem->replicate([
                        'documentable_id',
                        'documentable_type',
                        'subtotal',
                        'total',
                        'created_by',
                        'updated_by',
                        'created_at',
                        'updated_at',
                    ]);

                    $replicaLineItem->documentable_id = $replica->id;
                    $replicaLineItem->documentable_type = $replica->getMorphClass();

                    $replicaLineItem->save();

                    $replicaLineItem->adjustments()->sync($lineItem->adjustments->pluck('id'));
                });
            })
            ->successRedirectUrl(static function (self $replica) {
                return BillResource::getUrl('edit', ['record' => $replica]);
            });
    }
}
