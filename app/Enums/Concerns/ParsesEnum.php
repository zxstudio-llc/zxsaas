<?php

namespace App\Enums\Concerns;

trait ParsesEnum
{
    public static function parse(string | self | null $value): ?self
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof self) {
            return $value;
        }

        return self::from($value);
    }
}
