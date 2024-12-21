<?php

namespace App\Concerns;

use App\Utilities\Currency\CurrencyAccessor;
use App\Utilities\Currency\CurrencyConverter;

trait HandlesCurrencyConversion
{
    public function convertAmountToDefaultCurrency(int $amountCents): int
    {
        $defaultCurrency = CurrencyAccessor::getDefaultCurrency();
        $needsConversion = $this->currency_code !== $defaultCurrency;

        if ($needsConversion) {
            return CurrencyConverter::convertBalance(
                $amountCents,
                $this->currency_code,
                $defaultCurrency
            );
        }

        return $amountCents;
    }

    public function formatAmountToDefaultCurrency(int $amountCents): string
    {
        $convertedCents = $this->convertAmountToDefaultCurrency($amountCents);

        return CurrencyConverter::convertCentsToFormatSimple($convertedCents);
    }
}
