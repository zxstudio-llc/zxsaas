<?php

namespace App\Contracts;

interface MoneyFormattableDTO
{
    public static function fromArray(array $balances): static;
}
