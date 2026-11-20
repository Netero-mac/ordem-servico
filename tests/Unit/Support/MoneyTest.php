<?php

use App\Support\Money;

it('formata centavos em reais', function (int $cents, string $expected) {
    expect(Money::format($cents))->toBe($expected);
})->with([
    [0, 'R$ 0,00'],
    [5, 'R$ 0,05'],
    [123456, 'R$ 1.234,56'],
    [100000000, 'R$ 1.000.000,00'],
    [-1050, '-R$ 10,50'],
]);

it('converte texto em centavos', function (string $input, ?int $expected) {
    expect(Money::parse($input))->toBe($expected);
})->with([
    ['1.234,56', 123456],
    ['1234,56', 123456],
    ['1234,5', 123450],
    ['R$ 150,00', 15000],
    ['150', 15000],
    ['10.50', 1050],
    ['1.234', 123400],
    ['0,99', 99],
    ['', null],
    ['abc', null],
    ['1,234,56', null],
    ['-10,00', null],
]);

it('gera o valor para o input', function () {
    expect(Money::toInput(123456))->toBe('1.234,56');
});
