<?php

namespace App\DTO;

use Livewire\Wireable;

class AccountDTO implements Wireable
{
    public function __construct(
        public string $accountName,
        public string $accountCode,
        public ?int $accountId,
        public AccountBalanceDTO $balance,
    ) {}

    public function toLivewire(): array
    {
        return [
            'accountName' => $this->accountName,
            'accountCode' => $this->accountCode,
            'accountId' => $this->accountId,
            'balance' => $this->balance->toLivewire(),
        ];
    }

    public static function fromLivewire($value): static
    {
        return new static(
            $value['accountName'],
            $value['accountCode'],
            $value['accountId'],
            AccountBalanceDTO::fromLivewire($value['balance']),
        );
    }
}
