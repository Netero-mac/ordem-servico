<?php

use App\Enums\UserRole;
use App\Livewire\Users\Index;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->technician = User::factory()->technician()->create();
});

test('técnico não acessa a gestão de usuários nem vê o menu', function () {
    $this->actingAs($this->technician)->get(route('users.index'))->assertForbidden();
    $this->actingAs($this->technician)->get(route('dashboard'))->assertDontSee(route('users.index'));
    $this->actingAs($this->admin)->get(route('dashboard'))->assertSee(route('users.index'));
});

test('admin cria um técnico', function () {
    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->call('create')
        ->set('form.name', 'Novo Técnico')
        ->set('form.email', 'Novo@Example.com')
        ->set('form.role', 'technician')
        ->set('form.password', 'senha-segura-123')
        ->call('save')
        ->assertHasNoErrors();

    $user = User::where('email', 'novo@example.com')->sole();

    expect($user->role)->toBe(UserRole::Technician)
        ->and(Hash::check('senha-segura-123', $user->password))->toBeTrue();
});

test('valida e-mail único e senha obrigatória na criação', function () {
    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->set('form.name', 'X')
        ->set('form.email', $this->technician->email)
        ->call('save')
        ->assertHasErrors(['form.email' => 'unique', 'form.password' => 'required']);
});

test('edita sem trocar a senha quando o campo fica em branco', function () {
    $oldHash = $this->technician->password;

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->call('edit', $this->technician->id)
        ->set('form.name', 'Nome Novo')
        ->set('form.role', 'admin')
        ->call('save')
        ->assertHasNoErrors();

    $fresh = $this->technician->fresh();
    expect($fresh->name)->toBe('Nome Novo')
        ->and($fresh->role)->toBe(UserRole::Admin)
        ->and($fresh->password)->toBe($oldHash);
});

test('admin não rebaixa o próprio papel', function () {
    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->call('edit', $this->admin->id)
        ->set('form.role', 'technician')
        ->call('save');

    expect($this->admin->fresh()->role)->toBe(UserRole::Admin);
});

test('admin não exclui a si mesmo', function () {
    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->call('delete', $this->admin->id)
        ->assertForbidden();
});

test('admin exclui usuário sem histórico', function () {
    Livewire::actingAs($this->admin)->test(Index::class)->call('delete', $this->technician->id);

    expect($this->technician->fresh())->toBeNull();
});

test('usuário com histórico em OS não é excluído', function () {
    ServiceOrder::factory()->forTechnician($this->technician)->create();

    Livewire::actingAs($this->admin)->test(Index::class)->call('delete', $this->technician->id);

    expect($this->technician->fresh())->not->toBeNull();
});

test('técnico não cria usuário pela ação do componente', function () {
    Livewire::actingAs($this->technician)->test(Index::class)->assertForbidden();
});
