<?php

namespace App\Support;

use DateTimeInterface;
use Illuminate\Support\Facades\Date;

/**
 * Converte datas (guardadas em UTC) para o fuso da loja e formata no padrão brasileiro.
 */
final class Dates
{
    public static function date(?DateTimeInterface $value): string
    {
        return self::format($value, 'd/m/Y');
    }

    public static function dateTime(?DateTimeInterface $value): string
    {
        return self::format($value, 'd/m/Y H:i');
    }

    private static function format(?DateTimeInterface $value, string $format): string
    {
        if ($value === null) {
            return '—';
        }

        return Date::instance($value)->setTimezone(config('app.display_timezone'))->format($format);
    }
}
