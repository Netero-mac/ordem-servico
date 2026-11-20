<?php

namespace App\Enums;

enum ItemType: string
{
    case Part = 'part';
    case Labor = 'labor';

    public function label(): string
    {
        return match ($this) {
            self::Part => 'Peça',
            self::Labor => 'Mão de obra',
        };
    }
}
