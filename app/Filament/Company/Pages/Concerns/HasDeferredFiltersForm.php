<?php

namespace App\Filament\Company\Pages\Concerns;

use Illuminate\Support\Carbon;

trait HasDeferredFiltersForm
{
    protected function queryStringHasDeferredFiltersForm(): array
    {
        // Get the filter keys dynamically from the filters form
        $filterKeys = collect($this->getFiltersForm()->getFlatFields())->keys()->toArray();

        return array_merge(
            $this->generateQueryStrings($filterKeys),
            $this->generateExcludedQueryStrings(),
        );
    }

    protected function generateQueryStrings(array $filterKeys): array
    {
        $generatedQueryStrings = [];

        $excludedKeys = $this->excludeQueryStrings();

        foreach ($filterKeys as $key) {
            if (! in_array($key, $excludedKeys)) {
                $generatedQueryStrings["filters.{$key}"] = [
                    'as' => $key,
                    'keep' => true,
                ];
            }
        }

        return $generatedQueryStrings;
    }

    protected function generateExcludedQueryStrings(): array
    {
        $excludedQueryStrings = [];

        foreach ($this->excludeQueryStrings() as $key) {
            $excludedQueryStrings["filters.{$key}.value"] = null;
        }

        return $excludedQueryStrings;
    }

    protected function excludeQueryStrings(): array
    {
        return [
            'dateRange', // Example: dateRange should not have 'as' and 'keep'
        ];
    }

    public function dehydrateHasDeferredFiltersForm(): void
    {
        foreach ($this->filters as $key => $value) {
            if ($this->isDateFilter($value)) {
                $this->filters[$key] = Carbon::parse($value)->toDateString();
            }
        }
    }

    protected function isDateFilter($value): bool
    {
        return $value instanceof Carbon || strtotime($value) !== false;
    }
}
