<?php

namespace App\Casts;

use App\Utilities\Currency\CurrencyAccessor;
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
            return money($value, $currency_code)->formatSimple();
        }

        return '';
    }

    /**
     * @throws UnexpectedValueException
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): int
    {
        $currency_code = $model->bankAccount?->account?->currency_code ?? CurrencyAccessor::getDefaultCurrency();

        if (! $currency_code) {
            throw new UnexpectedValueException('Currency code is not set');
        }

        if (is_numeric($value)) {
            $value = (string) $value;
        } elseif (! is_string($value)) {
            throw new UnexpectedValueException('Expected string or numeric value for money cast');
        }

        return money($value, $currency_code, true)->getAmount();
    }
}
