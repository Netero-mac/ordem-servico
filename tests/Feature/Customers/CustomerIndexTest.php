<?php

use App\Livewire\Customers\Index;
use App\Models\Customer;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->technician()->create());
});

test('visitante é redirecionado para o login', function () {
    auth()->logout();

    $this->get(route('customers.index'))->assertRedirect(route('login'));
});

test('lista os clientes em ordem alfabética', function () {
    Customer::factory()->create(['name' => 'Bruna Lima']);
    Customer::factory()->create(['name' => 'Ana Souza']);

    $this->get(route('customers.index'))
        ->assertOk()
        ->assertSeeInOrder(['Ana Souza', 'Bruna Lima']);
});

test('busca por parte do nome, sem diferenciar maiúsculas', function () {
    Customer::factory()->create(['name' => 'Carlos Pereira']);
    Customer::factory()->create(['name' => 'Daniela Rocha']);

    Livewire::test(Index::class)
        ->set('search', 'carlos')
        ->assertSee('Carlos Pereira')
        ->assertDontSee('Daniela Rocha');
});

test('busca por telefone com ou sem máscara', function (string $term) {
    Customer::factory()->create(['name' => 'Carlos Pereira', 'phone' => '65999998888']);
    Customer::factory()->create(['name' => 'Daniela Rocha', 'phone' => '65911112222']);

    Livewire::test(Index::class)
        ->set('search', $term)
        ->assertSee('Carlos Pereira')
        ->assertDontSee('Daniela Rocha');
})->with(['99999-8888', '(65) 99999', '8888']);

test('a busca fica na URL', function () {
    Customer::factory()->create(['name' => 'Carlos Pereira']);

    Livewire::withQueryParams(['busca' => 'Carlos'])
        ->test(Index::class)
        ->assertSet('search', 'Carlos')
        ->assertSee('Carlos Pereira');
});

test('mostra mensagem quando nada é encontrado', function () {
    Livewire::test(Index::class)
        ->set('search', 'Inexistente')
        ->assertSee('Nenhum cliente encontrado');
});

test('não lista clientes excluídos', function () {
    Customer::factory()->create(['name' => 'Cliente Excluído'])->delete();

    Livewire::test(Index::class)->assertDontSee('Cliente Excluído');
});

test('mostra 15 clientes por página', function () {
    Customer::factory(20)->create();

    $component = Livewire::test(Index::class);

    expect($component->instance()->customers()->total())->toBe(20)
        ->and($component->instance()->customers()->count())->toBe(15);
});
