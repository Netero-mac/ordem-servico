<?php

use App\Support\Phone;

it('normaliza telefone para só dígitos', function (string $input, string $expected) {
    expect(Phone::normalize($input))->toBe($expected);
})->with([
    'celular formatado' => ['(65) 99999-8888', '65999998888'],
    'com +55' => ['+55 65 99999-8888', '65999998888'],
    'fixo' => ['65 3333-4444', '6533334444'],
    'vazio' => ['', ''],
]);

it('formata celular e fixo', function () {
    expect(Phone::format('65999998888'))->toBe('(65) 99999-8888')
        ->and(Phone::format('6533334444'))->toBe('(65) 3333-4444')
        ->and(Phone::format('123'))->toBe('123');
});

it('monta o número do WhatsApp com o código do Brasil', function () {
    expect(Phone::whatsApp('(65) 99999-8888'))->toBe('5565999998888');
});
