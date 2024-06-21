<?php

namespace App\DTO;

use Livewire\Wireable;

class ReportDTO implements Wireable
{
    public function __construct(
        /**
         * @var AccountCategoryDTO[]
         */
        public array $categories,
        public AccountBalanceDTO $overallTotal,
        public array $fields,
    ) {}

    public function toLivewire(): array
    {
        return [
            'categories' => $this->categories,
            'overallTotal' => $this->overallTotal->toLivewire(),
            'fields' => $this->fields,
        ];
    }

    public static function fromLivewire($value): static
    {
        return new static(
            $value['categories'],
            AccountBalanceDTO::fromLivewire($value['overallTotal']),
            $value['fields'],
        );
    }
}
