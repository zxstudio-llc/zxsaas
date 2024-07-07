<?php

namespace App\DTO;

class AccountTransactionDTO
{
    public function __construct(
        public string $date,
        public string $description,
        public string $debit,
        public string $credit,
        public string $balance,
    ) {}
}
