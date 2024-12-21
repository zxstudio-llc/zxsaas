<?php

namespace App\Models\Accounting;

use App\Enums\Accounting\DocumentType;
use App\Enums\Accounting\InvoiceStatus;
use App\Enums\Accounting\JournalEntryType;
use App\Enums\Accounting\TransactionType;
use App\Filament\Company\Resources\Sales\InvoiceResource;
use App\Models\Common\Client;
use App\Models\Setting\DocumentDefault;
use App\Observers\InvoiceObserver;
use App\Utilities\Currency\CurrencyAccessor;
use App\Utilities\Currency\CurrencyConverter;
use Filament\Actions\Action;
use Filament\Actions\MountableAction;
use Filament\Actions\ReplicateAction;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;

#[ObservedBy(InvoiceObserver::class)]
class Invoice extends Document
{
    protected $table = 'invoices';

    protected $fillable = [
        ...self::COMMON_FILLABLE,
        ...self::INVOICE_FILLABLE,
    ];

    protected const INVOICE_FILLABLE = [
        'client_id',
        'logo',
        'header',
        'subheader',
        'invoice_number',
        'approved_at',
        'last_sent',
        'terms',
        'footer',
    ];

    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'approved_at' => 'datetime',
            'last_sent' => 'datetime',
            'status' => InvoiceStatus::class,
        ];
    }

    public static function documentNumberColumn(): string
    {
        return 'invoice_number';
    }

    public static function documentType(): DocumentType
    {
        return DocumentType::Invoice;
    }

    public static function getDocumentSettings(): DocumentDefault
    {
        return auth()->user()->currentCompany->defaultInvoice;
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function approvalTransaction(): MorphOne
    {
        return $this->morphOne(Transaction::class, 'transactionable')
            ->where('type', TransactionType::Journal);
    }

    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            InvoiceStatus::Paid,
            InvoiceStatus::Void,
            InvoiceStatus::Draft,
            InvoiceStatus::Overpaid,
        ]);
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
        ]) && $this->currency_code === CurrencyAccessor::getDefaultCurrency();
    }

    public function canBeOverdue(): bool
    {
        return in_array($this->status, InvoiceStatus::canBeOverdue());
    }

    public function recordPayment(array $data): void
    {
        $isRefund = $this->status === InvoiceStatus::Overpaid;

        $transactionType = $isRefund
            ? TransactionType::Withdrawal // Refunds are withdrawals
            : TransactionType::Deposit;  // Payments are deposits

        $transactionDescription = $isRefund
            ? "Invoice #{$this->invoice_number}: Refund to {$this->client->name}"
            : "Invoice #{$this->invoice_number}: Payment from {$this->client->name}";

        $this->recordTransaction(
            $data,
            $transactionType,
            $transactionDescription,
            Account::getAccountsReceivableAccount()->id // Account ID specific to invoices
        );
    }

    public function approveDraft(?Carbon $approvedAt = null): void
    {
        if (! $this->isDraft()) {
            throw new \RuntimeException('Invoice is not in draft status.');
        }

        $this->createApprovalTransaction();

        $approvedAt ??= now();

        $this->update([
            'approved_at' => $approvedAt,
            'status' => InvoiceStatus::Unsent,
        ]);
    }

    public function createApprovalTransaction(): void
    {
        $total = $this->formatAmountToDefaultCurrency($this->getRawOriginal('total'));

        $transaction = $this->transactions()->create([
            'company_id' => $this->company_id,
            'type' => TransactionType::Journal,
            'posted_at' => $this->date,
            'amount' => $total,
            'description' => 'Invoice Approval for Invoice #' . $this->invoice_number,
        ]);

        $baseDescription = "{$this->client->name}: Invoice #{$this->invoice_number}";

        $transaction->journalEntries()->create([
            'company_id' => $this->company_id,
            'type' => JournalEntryType::Debit,
            'account_id' => Account::getAccountsReceivableAccount()->id,
            'amount' => $total,
            'description' => $baseDescription,
        ]);

        $totalLineItemSubtotalCents = $this->convertAmountToDefaultCurrency((int) $this->lineItems()->sum('subtotal'));
        $invoiceDiscountTotalCents = $this->convertAmountToDefaultCurrency((int) $this->getRawOriginal('discount_total'));
        $remainingDiscountCents = $invoiceDiscountTotalCents;

        foreach ($this->lineItems as $index => $lineItem) {
            $lineItemDescription = "{$baseDescription} › {$lineItem->offering->name}";

            $lineItemSubtotal = $this->formatAmountToDefaultCurrency($lineItem->getRawOriginal('subtotal'));

            $transaction->journalEntries()->create([
                'company_id' => $this->company_id,
                'type' => JournalEntryType::Credit,
                'account_id' => $lineItem->offering->income_account_id,
                'amount' => $lineItemSubtotal,
                'description' => $lineItemDescription,
            ]);

            foreach ($lineItem->adjustments as $adjustment) {
                $adjustmentAmount = $this->formatAmountToDefaultCurrency($lineItem->calculateAdjustmentTotalAmount($adjustment));

                $transaction->journalEntries()->create([
                    'company_id' => $this->company_id,
                    'type' => $adjustment->category->isDiscount() ? JournalEntryType::Debit : JournalEntryType::Credit,
                    'account_id' => $adjustment->account_id,
                    'amount' => $adjustmentAmount,
                    'description' => $lineItemDescription,
                ]);
            }

            if ($this->discount_method->isPerDocument() && $totalLineItemSubtotalCents > 0) {
                $lineItemSubtotalCents = $this->convertAmountToDefaultCurrency((int) $lineItem->getRawOriginal('subtotal'));

                if ($index === $this->lineItems->count() - 1) {
                    $lineItemDiscount = $remainingDiscountCents;
                } else {
                    $lineItemDiscount = (int) round(
                        ($lineItemSubtotalCents / $totalLineItemSubtotalCents) * $invoiceDiscountTotalCents
                    );
                    $remainingDiscountCents -= $lineItemDiscount;
                }

                if ($lineItemDiscount > 0) {
                    $transaction->journalEntries()->create([
                        'company_id' => $this->company_id,
                        'type' => JournalEntryType::Debit,
                        'account_id' => Account::getSalesDiscountAccount()->id,
                        'amount' => CurrencyConverter::convertCentsToFormatSimple($lineItemDiscount),
                        'description' => "{$lineItemDescription} (Proportional Discount)",
                    ]);
                }
            }
        }
    }

    public function updateApprovalTransaction(): void
    {
        $transaction = $this->approvalTransaction;

        if ($transaction) {
            $transaction->delete();
        }

        $this->createApprovalTransaction();
    }

    public static function getApproveDraftAction(string $action = Action::class): MountableAction
    {
        return $action::make('approveDraft')
            ->label('Approve')
            ->icon('heroicon-o-check-circle')
            ->visible(function (self $record) {
                return $record->isDraft();
            })
            ->databaseTransaction()
            ->successNotificationTitle('Invoice Approved')
            ->action(function (self $record, MountableAction $action) {
                $record->approveDraft();

                $action->success();
            });
    }

    public static function getMarkAsSentAction(string $action = Action::class): MountableAction
    {
        return $action::make('markAsSent')
            ->label('Mark as Sent')
            ->icon('heroicon-o-paper-airplane')
            ->visible(static function (self $record) {
                return ! $record->last_sent;
            })
            ->successNotificationTitle('Invoice Sent')
            ->action(function (self $record, MountableAction $action) {
                $record->update([
                    'status' => InvoiceStatus::Sent,
                    'last_sent' => now(),
                ]);

                $action->success();
            });
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
                'invoice_number',
                'date',
                'due_date',
                'approved_at',
                'paid_at',
                'last_sent',
            ])
            ->modal(false)
            ->beforeReplicaSaved(function (self $original, self $replica) {
                $replica->status = InvoiceStatus::Draft;
                $replica->invoice_number = self::getNextDocumentNumber();
                $replica->date = now();
                $replica->due_date = now()->addDays($original->company->defaultInvoice->payment_terms->getDays());
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
                return InvoiceResource::getUrl('edit', ['record' => $replica]);
            });
    }
}
