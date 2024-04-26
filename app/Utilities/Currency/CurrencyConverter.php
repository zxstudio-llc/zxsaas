<?php

namespace App\Utilities\Currency;

class CurrencyConverter
{
    public static function convertBalance(int $balance, string $oldCurrency, string $newCurrency): int
    {
        return money($balance, $oldCurrency)->swapAmountFor($newCurrency);
    }

    public static function prepareForMutator(int $balance, string $currency): string
    {
        return money($balance, $currency)->formatSimple();
    }

    public static function prepareForAccessor(string $balance, string $currency): int
    {
        return money($balance, $currency, true)->getAmount();
    }
}
