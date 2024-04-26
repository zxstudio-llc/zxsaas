<?php

namespace App\ValueObjects;

use App\Utilities\Currency\CurrencyAccessor;
use App\Utilities\Currency\CurrencyConverter;

class BalanceValue
{
    private int $value;

    private string $currency;

    private ?int $convertedValue = null;

    public function __construct(int $value, string $currency)
    {
        $this->value = $value;
        $this->currency = $currency;
    }

    public function getValue(): int
    {
        return $this->value;
    }

    public function getEffectiveValue(): int
    {
        return $this->convertedValue ?? $this->value;
    }

    public function getConvertedValue(): ?int
    {
        return $this->convertedValue;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function formatted(): string
    {
        return money($this->getEffectiveValue(), $this->getCurrency())->format();
    }

    public function formattedSimple(): string
    {
        return money($this->getEffectiveValue(), $this->getCurrency())->formatSimple();
    }

    public function formatWithCode(bool $codeBefore = false): string
    {
        return money($this->getEffectiveValue(), $this->getCurrency())->formatWithCode($codeBefore);
    }

    public function convert(): self
    {
        // The journal entry sums are stored in the default currency not the account currency (transaction amounts are stored in the account currency)
        $fromCurrency = CurrencyAccessor::getDefaultCurrency();
        $toCurrency = $this->currency;

        if ($fromCurrency !== $toCurrency) {
            $this->convertedValue = CurrencyConverter::convertBalance($this->value, $fromCurrency, $toCurrency);
        }

        return $this;
    }
}
