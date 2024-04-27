<?php

namespace App\Casts;

use App\Utilities\Currency\CurrencyAccessor;
use App\Utilities\Currency\CurrencyConverter;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use UnexpectedValueException;

class TransactionAmountCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): string
    {
        // Attempt to retrieve the currency code from the related bankAccount->account model
        $currency_code = $model->bankAccount?->account?->currency_code ?? CurrencyAccessor::getDefaultCurrency();

        if ($value !== null) {
            return CurrencyConverter::prepareForMutator($value, $currency_code);
        }

        return '';
    }

    /**
     * @throws UnexpectedValueException
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): int
    {
        $currency_code = $model->bankAccount?->account?->currency_code ?? CurrencyAccessor::getDefaultCurrency();

        if (is_numeric($value)) {
            $value = (string) $value;
        } elseif (! is_string($value)) {
            throw new UnexpectedValueException('Expected string or numeric value for money cast');
        }

        return CurrencyConverter::prepareForAccessor($value, $currency_code);
    }
}
