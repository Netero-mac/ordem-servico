<?php

/**
 * Matriz de autorização: o que cada papel pode fazer.
 * Regra geral do sistema: técnico trabalha nas OS, mas não exclui nada; só o admin exclui.
 */

use App\Enums\ServiceOrderStatus as S;
use App\Models\Customer;
use App\Models\Device;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

dataset('roles', [
    'admin' => fn () => User::factory()->admin()->create(),
    'técnico' => fn () => User::factory()->technician()->create(),
]);

test('ambos os papéis consultam, cadastram e editam clientes e aparelhos', function (User $user) {
    $customer = Customer::factory()->create();
    $device = Device::factory()->for($customer)->create();
    $gate = Gate::forUser($user);

    expect($gate->allows('viewAny', Customer::class))->toBeTrue()
        ->and($gate->allows('create', Customer::class))->toBeTrue()
        ->and($gate->allows('update', $customer))->toBeTrue()
        ->and($gate->allows('create', Device::class))->toBeTrue()
        ->and($gate->allows('update', $device))->toBeTrue();
})->with('roles');

test('ambos os papéis abrem OS, mudam status e anexam fotos', function (User $user) {
    $order = ServiceOrder::factory()->status(S::InRepair)->create();
    $gate = Gate::forUser($user);

    expect($gate->allows('create', ServiceOrder::class))->toBeTrue()
        ->and($gate->allows('view', $order))->toBeTrue()
        ->and($gate->allows('update', $order))->toBeTrue()
        ->and($gate->allows('changeStatus', $order))->toBeTrue()
        ->and($gate->allows('addPhotos', $order))->toBeTrue();
})->with('roles');

test('só o admin exclui', function () {
    $admin = User::factory()->admin()->create();
    $technician = User::factory()->technician()->create();
    $other = User::factory()->technician()->create();
    $customer = Customer::factory()->create();
    $device = Device::factory()->for($customer)->create();
    $order = ServiceOrder::factory()->create(['device_id' => $device->id]);

    $abilities = [
        ['delete', $customer],
        ['delete', $device],
        ['delete', $order],
        ['deletePhotos', $order],
        ['delete', $other],
    ];

    foreach ($abilities as [$ability, $model]) {
        expect(Gate::forUser($admin)->allows($ability, $model))->toBeTrue("admin deveria poder {$ability} ".class_basename($model))
            ->and(Gate::forUser($technician)->allows($ability, $model))->toBeFalse("técnico NÃO deveria poder {$ability} ".class_basename($model));
    }
});

test('só o admin gerencia usuários', function () {
    $admin = User::factory()->admin()->create();
    $technician = User::factory()->technician()->create();

    expect(Gate::forUser($admin)->allows('viewAny', User::class))->toBeTrue()
        ->and(Gate::forUser($technician)->allows('viewAny', User::class))->toBeFalse()
        ->and(Gate::forUser($technician)->allows('create', User::class))->toBeFalse()
        ->and(Gate::forUser($technician)->allows('update', $admin))->toBeFalse();
});

test('depois da decisão do cliente, só o admin mexe no orçamento', function (S $status, bool $technicianCan) {
    $order = ServiceOrder::factory()->status($status)->create();

    expect(Gate::forUser(User::factory()->technician()->create())->allows('manageItems', $order))->toBe($technicianCan)
        ->and(Gate::forUser(User::factory()->admin()->create())->allows('manageItems', $order))->toBeTrue();
})->with([
    [S::Received, true],
    [S::InAnalysis, true],
    [S::AwaitingApproval, true],
    [S::Approved, false],
    [S::Rejected, false],
    [S::InRepair, false],
    [S::Delivered, false],
]);

test('OS encerrada: técnico não edita; ninguém muda status', function (S $status) {
    $order = ServiceOrder::factory()->status($status)->create();
    $admin = User::factory()->admin()->create();
    $technician = User::factory()->technician()->create();

    expect(Gate::forUser($technician)->allows('update', $order))->toBeFalse()
        ->and(Gate::forUser($admin)->allows('update', $order))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('changeStatus', $order))->toBeFalse()
        ->and(Gate::forUser($technician)->allows('addPhotos', $order))->toBeFalse();
})->with([S::Delivered, S::Cancelled]);

test('todas as telas internas exigem login', function (string $routeName, bool $needsOrder) {
    $params = $needsOrder ? [ServiceOrder::factory()->create()] : [];

    $this->get(route($routeName, $params))->assertRedirect(route('login'));
})->with([
    ['dashboard', false],
    ['customers.index', false],
    ['customers.create', false],
    ['service-orders.index', false],
    ['service-orders.create', false],
    ['service-orders.show', true],
    ['service-orders.pdf.entry', true],
    ['users.index', false],
]);
