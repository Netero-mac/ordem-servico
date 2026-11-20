<?php

namespace App\Enums;

enum DeviceType: string
{
    case Phone = 'phone';
    case Notebook = 'notebook';
    case Desktop = 'desktop';
    case Tablet = 'tablet';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Phone => 'Celular',
            self::Notebook => 'Notebook',
            self::Desktop => 'Desktop',
            self::Tablet => 'Tablet',
            self::Other => 'Outro',
        };
    }
}
