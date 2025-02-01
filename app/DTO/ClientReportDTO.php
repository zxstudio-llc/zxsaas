<?php

namespace App\DTO;

readonly class ClientReportDTO
{
    public function __construct(
        public string $clientName,
        public string $clientId,
        public ?AgingBucketDTO $aging = null,
        public ?ClientBalanceDTO $balance = null,
    ) {}
}
