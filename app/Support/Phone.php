<?php

namespace App\Support;

final class Phone
{
    /** Regra de validação para telefone já normalizado: DDD + 8 ou 9 dígitos. */
    public const RULE = 'regex:/^\d{10,11}$/';

    /** "(69) 99999-9999" -> "69999999999"; remove +55 quando presente. */
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $phone);

        if (strlen($digits) > 11 && str_starts_with($digits, '55')) {
            $digits = substr($digits, 2);
        }

        return $digits === '' ? null : $digits;
    }

    /** "69999999999" -> "(69) 99999-9999" */
    public static function format(?string $phone): string
    {
        $digits = self::normalize($phone) ?? '';

        return match (strlen($digits)) {
            11 => sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 5), substr($digits, 7)),
            10 => sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 4), substr($digits, 6)),
            default => $digits,
        };
    }
}
