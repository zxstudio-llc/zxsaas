<?php

namespace App\ValueObjects;

use App\Utilities\Currency\CurrencyAccessor;
use App\Utilities\Currency\CurrencyConverter;

class BalanceValue
{
    private int $value;

    private string $currency;

    public function __construct(int $value, string $currency)
    {
        $this->value = $value;
        $this->currency = $currency;
    }

    public function getValue(): int
    {
        return $this->value;
    }

    public function formatted(): string
    {
        return money($this->value, $this->currency)->format();
    }

    public function formattedSimple(): string
    {
        return money($this->value, $this->currency)->formatSimple();
    }

    public function formattedForDisplay(): string
    {
        $defaultCurrency = CurrencyAccessor::getDefaultCurrency();
        $accountCurrency = $this->currency;

        if ($accountCurrency === $defaultCurrency) {
            return $this->formatted();
        }

        $convertedBalance = CurrencyConverter::convertBalance($this->value, $defaultCurrency, $accountCurrency);

        return money($convertedBalance, $accountCurrency)->formatWithCode();
    }
}
