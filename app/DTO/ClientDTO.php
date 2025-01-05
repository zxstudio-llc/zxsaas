<?php

namespace App\DTO;

use App\Models\Common\Client;

readonly class ClientDTO
{
    public function __construct(
        public string $name,
        public string $addressLine1,
        public string $addressLine2,
        public string $city,
        public string $state,
        public string $postalCode,
        public string $country,
    ) {}

    public static function fromModel(Client $client): self
    {
        $address = $client->billingAddress ?? null;

        return new self(
            name: $client->name,
            addressLine1: $address?->address_line_1 ?? '',
            addressLine2: $address?->address_line_2 ?? '',
            city: $address?->city ?? '',
            state: $address?->state ?? '',
            postalCode: $address?->postal_code ?? '',
            country: $address?->country ?? '',
        );
    }
}
