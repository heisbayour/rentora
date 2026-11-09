<?php

declare(strict_types=1);

namespace App\Enums\Concerns;

/**
 * For backed enums with label(): options() feeds <x-select :options>, values() feeds Rule::in.
 */
trait HasOptions
{
    /** @return array<string, string> */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
