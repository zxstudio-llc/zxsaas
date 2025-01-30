<?php

namespace App\DTO;

readonly class VendorReportDTO
{
    public function __construct(
        public string $vendorName,
        public string $vendorId,
        public AgingBucketDTO $aging,
    ) {}
}
