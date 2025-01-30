<?php

namespace App\DTO;

use App\Contracts\MoneyFormattableDTO;

class AccountBalanceDTO implements MoneyFormattableDTO
{
    public function __construct(
        public ?string $startingBalance,
        public ?string $debitBalance,
        public ?string $creditBalance,
        public ?string $netMovement,
        public ?string $endingBalance,
    ) {}

    public static function fromArray(array $balances): static
    {
        return new static(
            startingBalance: $balances['starting_balance'] ?? null,
            debitBalance: $balances['debit_balance'] ?? null,
            creditBalance: $balances['credit_balance'] ?? null,
            netMovement: $balances['net_movement'] ?? null,
            endingBalance: $balances['ending_balance'] ?? null,
        );
    }
}
