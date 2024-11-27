<?php

namespace App\Models\Accounting;

use App\Casts\MoneyCast;
use App\Concerns\Blamable;
use App\Concerns\CompanyOwned;
use App\Enums\Accounting\DocumentStatus;
use App\Enums\Accounting\DocumentType;
use App\Models\Banking\Payment;
use App\Models\Common\Client;
use App\Models\Common\Vendor;
use App\Observers\DocumentObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(DocumentObserver::class)]
class Document extends Model
{
    use Blamable;
    use CompanyOwned;
    use HasFactory;

    protected $table = 'documents';

    protected $fillable = [
        'company_id',
        'client_id',
        'vendor_id',
        'type',
        'logo',
        'header',
        'subheader',
        'document_number',
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
        'type' => DocumentType::class,
        'date' => 'date',
        'due_date' => 'date',
        'status' => DocumentStatus::class,
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

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(DocumentLineItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public static function getNextDocumentNumber(DocumentType $documentType = DocumentType::Invoice): string
    {
        $company = auth()->user()->currentCompany;

        if (! $company) {
            throw new \RuntimeException('No current company is set for the user.');
        }

        $defaultInvoiceSettings = $company->defaultInvoice;

        $numberPrefix = $defaultInvoiceSettings->number_prefix;
        $numberDigits = $defaultInvoiceSettings->number_digits;

        $latestDocument = static::query()
            ->whereNotNull('document_number')
            ->where('type', $documentType)
            ->latest('document_number')
            ->first();

        $lastNumberNumericPart = $latestDocument
            ? (int) substr($latestDocument->document_number, strlen($numberPrefix))
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
