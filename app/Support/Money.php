<?php

namespace App\Support;

/**
 * Valores monetários são inteiros em centavos. Esta classe converte de/para o formato brasileiro.
 */
final class Money
{
    /**
     * 123456 → "R$ 1.234,56"
     */
    public static function format(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';

        return $sign.'R$ '.number_format(abs($cents) / 100, 2, ',', '.');
    }

    /**
     * 123456 → "1.234,56" (para preencher inputs)
     */
    public static function toInput(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '.');
    }

    /**
     * "1.234,56" | "1234,5" | "R$ 10" | "10.50" → centavos. Retorna null se não for um valor válido.
     */
    public static function parse(?string $value): ?int
    {
        $value = trim(str_replace(['R$', ' ', "\u{00A0}"], '', (string) $value));

        if ($value === '') {
            return null;
        }

        if (str_contains($value, ',')) {
            // Formato brasileiro: ponto é milhar, vírgula é decimal.
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif (! preg_match('/^\d+\.\d{1,2}$/', $value)) {
            // Sem vírgula: "1.234" é mil duzentos e trinta e quatro.
            $value = str_replace('.', '', $value);
        }

        if (! preg_match('/^\d+(\.\d{1,2})?$/', $value)) {
            return null;
        }

        [$reais, $centavos] = array_pad(explode('.', $value), 2, '0');

        return (int) $reais * 100 + (int) str_pad($centavos, 2, '0');
    }
}
