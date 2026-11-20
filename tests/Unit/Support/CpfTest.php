<?php

use App\Support\Cpf;
use Database\Factories\CustomerFactory;

it('valida CPFs', function (string $cpf, bool $valid) {
    expect(Cpf::isValid($cpf))->toBe($valid);
})->with([
    'válido sem máscara' => ['52998224725', true],
    'válido com máscara' => ['529.982.247-25', true],
    'dígito errado' => ['52998224724', false],
    'todos iguais' => ['11111111111', false],
    'curto' => ['5299822472', false],
    'vazio' => ['', false],
]);

it('formata CPF', function () {
    expect(Cpf::format('52998224725'))->toBe('529.982.247-25');
});

it('a factory gera CPFs fictícios válidos', function () {
    foreach (range(1, 50) as $ignored) {
        expect(Cpf::isValid(CustomerFactory::fakeCpf()))->toBeTrue();
    }
});
