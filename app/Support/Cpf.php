<?php

namespace App\Support;

final class Cpf
{
    public static function normalize(?string $value): string
    {
        return preg_replace('/\D/', '', (string) $value) ?? '';
    }

    public static function isValid(?string $value): bool
    {
        $cpf = self::normalize($value);

        // 11 dígitos e não pode ser tudo igual (111.111.111-11 passa no cálculo, mas é inválido).
        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        foreach ([9, 10] as $length) {
            $sum = 0;
            for ($i = 0; $i < $length; $i++) {
                $sum += (int) $cpf[$i] * ($length + 1 - $i);
            }

            if ((int) $cpf[$length] !== ($sum * 10) % 11 % 10) {
                return false;
            }
        }

        return true;
    }

    /**
     * 12345678909 → 123.456.789-09
     */
    public static function format(?string $value): string
    {
        $cpf = self::normalize($value);

        if (strlen($cpf) !== 11) {
            return $cpf;
        }

        return sprintf('%s.%s.%s-%s', substr($cpf, 0, 3), substr($cpf, 3, 3), substr($cpf, 6, 3), substr($cpf, 9));
    }
}
