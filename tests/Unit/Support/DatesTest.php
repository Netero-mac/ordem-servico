<?php

use App\Support\Dates;
use Carbon\CarbonImmutable;

it('exibe datas no fuso de Cuiabá e no formato brasileiro', function () {
    $utc = CarbonImmutable::parse('2026-11-20 02:30:00', 'UTC');

    expect(Dates::dateTime($utc))->toBe('19/11/2026 22:30')
        ->and(Dates::date($utc))->toBe('19/11/2026')
        ->and(Dates::date(null))->toBe('—');
});
