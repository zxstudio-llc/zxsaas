<?php

namespace App\Models\Accounting;

use App\Casts\MoneyCast;
use App\Concerns\Blamable;
use App\Concerns\CompanyOwned;
use App\Enums\Accounting\InvoiceStatus;
use App\Models\Banking\Payment;
use App\Models\Common\Client;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

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

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
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
            InvoiceStatus::Overpaid,
            InvoiceStatus::Void,
        ]);
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
}
