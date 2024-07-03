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
        public ?AccountBalanceDTO $overallTotal = null,
        public array $fields = [],
    ) {}

    public function toLivewire(): array
    {
        return [
            'categories' => $this->categories,
            'overallTotal' => $this->overallTotal?->toLivewire(),
            'fields' => $this->fields,
        ];
    }

    public static function fromLivewire($value): static
    {
        return new static(
            $value['categories'],
            isset($value['overallTotal']) ? AccountBalanceDTO::fromLivewire($value['overallTotal']) : null,
            $value['fields'] ?? [],
        );
    }
}
