<?php

namespace App\DTO;

use App\Models\Company;

readonly class CompanyDTO
{
    public function __construct(
        public string $name,
        public string $address,
        public string $city,
        public string $state,
        public string $zipCode,
        public string $country,
    ) {}

    public static function fromModel(Company $company): self
    {
        $profile = $company->profile;

        return new self(
            name: $company->name,
            address: $profile->address ?? '',
            city: $profile->city?->name ?? '',
            state: $profile->state?->name ?? '',
            zipCode: $profile->zip_code ?? '',
            country: $profile->state?->country->name ?? '',
        );
    }
}
