<?php

namespace App\Concerns;

use RuntimeException;

trait GeneratesDocumentNumbers
{
    public static function getNextDocumentNumber(): string
    {
        $company = auth()->user()->currentCompany;

        if (! $company) {
            throw new RuntimeException('No current company is set for the user.');
        }

        $settings = $company->{static::getSettingsKey()};
        $numberField = static::getDocumentNumberField();

        $latestDocument = static::query()
            ->whereNotNull($numberField)
            ->latest($numberField)
            ->first();

        $lastNumberPart = $latestDocument
            ? (int) substr($latestDocument->{$numberField}, strlen($settings->number_prefix))
            : 0;

        return $settings->getNumberNext(
            padded: true,
            format: true,
            prefix: $settings->number_prefix,
            digits: $settings->number_digits,
            next: $lastNumberPart + 1
        );
    }
}
