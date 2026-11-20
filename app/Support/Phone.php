<?php

namespace App\Support;

/**
 * Telefones são guardados só com dígitos (DDD + número), sem o +55.
 */
final class Phone
{
    public static function normalize(?string $value): string
    {
        $digits = preg_replace('/\D/', '', (string) $value) ?? '';

        // Remove o código do país se a pessoa colou "+55 65 9...".
        if (strlen($digits) > 11 && str_starts_with($digits, '55')) {
            $digits = substr($digits, 2);
        }

        return $digits;
    }

    /**
     * 65999998888 → (65) 99999-8888 | 6533334444 → (65) 3333-4444
     */
    public static function format(?string $value): string
    {
        $digits = self::normalize($value);

        return match (strlen($digits)) {
            11 => sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 5), substr($digits, 7)),
            10 => sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 4), substr($digits, 6)),
            default => $digits,
        };
    }

    /**
     * Número no formato do wa.me (com código do Brasil).
     */
    public static function whatsApp(?string $value): string
    {
        return '55'.self::normalize($value);
    }
}
