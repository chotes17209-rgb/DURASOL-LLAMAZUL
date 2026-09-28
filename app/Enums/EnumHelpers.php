<?php

namespace App\Enums;

/**
 * Utilidades comunes para los enums con etiqueta legible.
 */
trait EnumHelpers
{
    /** @return array<string, string> valor => etiqueta, útil para selects. */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function labelFor(?string $value): string
    {
        return $value === null ? '' : (self::tryFrom($value)?->label() ?? $value);
    }
}
