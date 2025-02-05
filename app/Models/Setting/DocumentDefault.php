<?php

namespace App\Models\Setting;

use App\Casts\TrimLeadingZeroCast;
use App\Concerns\Blamable;
use App\Concerns\CompanyOwned;
use App\Enums\Setting\DocumentType;
use App\Enums\Setting\Font;
use App\Enums\Setting\PaymentTerms;
use App\Enums\Setting\Template;
use Database\Factories\Setting\DocumentDefaultFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class DocumentDefault extends Model
{
    use Blamable;
    use CompanyOwned;
    use HasFactory;

    protected $table = 'document_defaults';

    protected $fillable = [
        'company_id',
        'type',
        'logo',
        'show_logo',
        'number_prefix',
        'number_digits',
        'number_next',
        'payment_terms',
        'header',
        'subheader',
        'terms',
        'footer',
        'accent_color',
        'font',
        'template',
        'item_name',
        'unit_name',
        'price_name',
        'amount_name',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'show_logo' => 'boolean',
        'number_next' => TrimLeadingZeroCast::class,
        'payment_terms' => PaymentTerms::class,
        'font' => Font::class,
        'template' => Template::class,
        'item_name' => AsArrayObject::class,
        'unit_name' => AsArrayObject::class,
        'price_name' => AsArrayObject::class,
        'amount_name' => AsArrayObject::class,
    ];

    protected $appends = [
        'logo_url',
    ];

    protected function logoUrl(): Attribute
    {
        return Attribute::get(static function (mixed $value, array $attributes): ?string {
            return $attributes['logo'] ? Storage::disk('public')->url($attributes['logo']) : null;
        });
    }

    public function scopeType(Builder $query, string | DocumentType $type): Builder
    {
        return $query->where($this->qualifyColumn('type'), $type);
    }

    public function scopeInvoice(Builder $query): Builder
    {
        return $query->scopes(['type' => [DocumentType::Invoice]]);
    }

    public function scopeBill(Builder $query): Builder
    {
        return $query->scopes(['type' => [DocumentType::Bill]]);
    }

    public static function availableNumberDigits(): array
    {
        return array_combine(range(1, 20), range(1, 20));
    }

    public function getNumberNext(?bool $padded = null, ?bool $format = null, ?string $prefix = null, int | string | null $digits = null, int | string | null $next = null): string
    {
        [$number_prefix, $number_digits, $number_next] = $this->initializeAttributes($prefix, $digits, $next);

        return match (true) {
            $format && $padded => $number_prefix . $this->getPaddedNumberNext($number_next, $number_digits),
            $format => $number_prefix . $number_next,
            $padded => $this->getPaddedNumberNext($number_next, $number_digits),
            default => $number_next,
        };
    }

    public function initializeAttributes(?string $prefix, int | string | null $digits, int | string | null $next): array
    {
        $number_prefix = $prefix ?? $this->number_prefix;
        $number_digits = $digits ?? $this->number_digits;
        $number_next = $next ?? $this->number_next;

        return [$number_prefix, $number_digits, $number_next];
    }

    /**
     * Get the next number with padding for dynamic display purposes.
     * Even if number_next is a string, it will be cast to an integer.
     */
    public function getPaddedNumberNext(int | string | null $number_next, int | string | null $number_digits): string
    {
        return str_pad($number_next, $number_digits, '0', STR_PAD_LEFT);
    }

    public static function getAvailableItemNameOptions(): array
    {
        $options = [
            'items' => 'Items',
            'products' => 'Products',
            'services' => 'Services',
            'other' => 'Other',
        ];

        return array_map(translate(...), $options);
    }

    public static function getAvailableUnitNameOptions(): array
    {
        $options = [
            'quantity' => 'Quantity',
            'hours' => 'Hours',
            'other' => 'Other',
        ];

        return array_map(translate(...), $options);
    }

    public static function getAvailablePriceNameOptions(): array
    {
        $options = [
            'price' => 'Price',
            'rate' => 'Rate',
            'other' => 'Other',
        ];

        return array_map(translate(...), $options);
    }

    public static function getAvailableAmountNameOptions(): array
    {
        $options = [
            'amount' => 'Amount',
            'total' => 'Total',
            'other' => 'Other',
        ];

        return array_map(translate(...), $options);
    }

    public function getLabelOptionFor(string $optionType, ?string $optionValue)
    {
        $optionValue = $optionValue ?? $this->{$optionType}['option'];

        if (! $optionValue) {
            return null;
        }

        $options = match ($optionType) {
            'item_name' => static::getAvailableItemNameOptions(),
            'unit_name' => static::getAvailableUnitNameOptions(),
            'price_name' => static::getAvailablePriceNameOptions(),
            'amount_name' => static::getAvailableAmountNameOptions(),
            default => [],
        };

        return $options[$optionValue] ?? null;
    }

    public function resolveColumnLabel(string $column, string $default, ?array $data = null): string
    {
        if ($data) {
            $custom = $data[$column]['custom'] ?? null;
            $option = $data[$column]['option'] ?? null;
        } else {
            $custom = $this->{$column}['custom'] ?? null;
            $option = $this->{$column}['option'] ?? null;
        }

        if ($custom) {
            return $custom;
        }

        return $this->getLabelOptionFor($column, $option) ?? $default;
    }

    protected static function newFactory(): Factory
    {
        return DocumentDefaultFactory::new();
    }
}
