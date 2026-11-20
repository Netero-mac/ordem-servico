<?php

use App\Livewire\Customers\Edit;
use App\Models\Customer;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->technician()->create());
});

test('as telas de cadastro e edição abrem', function () {
    $this->get(route('customers.create'))->assertOk()->assertSee('Novo cliente');

    $customer = Customer::factory()->create();
    $this->get(route('customers.edit', $customer))->assertOk()->assertSee('Editar cliente');
});

test('cadastra cliente guardando telefone e CPF só com dígitos', function () {
    Livewire::test(Edit::class)
        ->set('form.name', '  Maria Oliveira ')
        ->set('form.phone', '(65) 99999-8888')
        ->set('form.email', 'Maria@Example.com')
        ->set('form.document', '529.982.247-25')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('customers.show', Customer::first()));

    expect(Customer::sole())
        ->name->toBe('Maria Oliveira')
        ->phone->toBe('65999998888')
        ->email->toBe('maria@example.com')
        ->document->toBe('52998224725');
});

test('email, CPF e observações são opcionais', function () {
    Livewire::test(Edit::class)
        ->set('form.name', 'João Silva')
        ->set('form.phone', '65 3333-4444')
        ->call('save')
        ->assertHasNoErrors();

    expect(Customer::sole())->email->toBeNull()->document->toBeNull()->notes->toBeNull();
});

test('valida campos obrigatórios em português', function () {
    Livewire::test(Edit::class)
        ->call('save')
        ->assertHasErrors(['form.name' => 'required', 'form.phone' => 'required'])
        ->assertSee('O campo nome é obrigatório.');
});

test('rejeita telefone sem DDD', function () {
    Livewire::test(Edit::class)
        ->set('form.name', 'João Silva')
        ->set('form.phone', '99999-8888')
        ->call('save')
        ->assertHasErrors('form.phone');
});

test('rejeita CPF inválido', function () {
    Livewire::test(Edit::class)
        ->set('form.name', 'João Silva')
        ->set('form.phone', '65999998888')
        ->set('form.document', '123.456.789-00')
        ->call('save')
        ->assertHasErrors('form.document')
        ->assertSee('O CPF informado é inválido.');
});

test('não permite dois clientes com o mesmo CPF', function () {
    Customer::factory()->create(['document' => '52998224725']);

    Livewire::test(Edit::class)
        ->set('form.name', 'João Silva')
        ->set('form.phone', '65999998888')
        ->set('form.document', '529.982.247-25')
        ->call('save')
        ->assertHasErrors('form.document');
});

test('edita cliente mantendo o próprio CPF', function () {
    $customer = Customer::factory()->create(['document' => '52998224725', 'phone' => '65999998888']);

    Livewire::test(Edit::class, ['customer' => $customer])
        ->assertSet('form.phone', '(65) 99999-8888')
        ->assertSet('form.document', '529.982.247-25')
        ->set('form.name', 'Nome Atualizado')
        ->call('save')
        ->assertHasNoErrors();

    expect($customer->fresh()->name)->toBe('Nome Atualizado');
});

test('cliente excluído não pode ser aberto', function () {
    $customer = Customer::factory()->create();
    $customer->delete();

    $this->get(route('customers.edit', $customer))->assertNotFound();
    $this->get(route('customers.show', $customer))->assertNotFound();
});
