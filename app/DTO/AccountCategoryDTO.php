<?php

namespace App\DTO;

class AccountCategoryDTO
{
    /**
     * @param  AccountDTO[]  $accounts
     */
    public function __construct(
        public array $accounts,
        public AccountBalanceDTO $summary,
    ) {}
}
