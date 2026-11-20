<?php

use App\Actions\ServiceOrders\OpenServiceOrder;
use App\Enums\ServiceOrderStatus;
use App\Livewire\ServiceOrders\Create;
use App\Models\Customer;
use App\Models\Device;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Database\QueryException;
use Livewire\Livewire;

beforeEach(function () {
    $this->technician = User::factory()->technician()->create();
    $this->admin = User::factory()->admin()->create();
});

describe('action', function () {
    test('cria cliente, aparelho e OS na mesma transação', function () {
        $order = app(OpenServiceOrder::class)->handle(
            $this->admin,
            ['name' => 'Novo Cliente', 'phone' => '65999998888'],
            ['type' => 'phone', 'brand' => 'Apple', 'model' => 'iPhone 13'],
            ['reported_issue' => 'Não liga'],
        );

        expect($order->status)->toBe(ServiceOrderStatus::Received)
            ->and($order->customer->name)->toBe('Novo Cliente')
            ->and($order->device->customer_id)->toBe($order->customer_id)
            ->and($order->user_id)->toBe($this->admin->id);
    });

    test('não deixa cliente pela metade se a OS falhar', function () {
        expect(fn () => app(OpenServiceOrder::class)->handle(
            $this->admin,
            ['name' => 'Novo Cliente', 'phone' => '65999998888'],
            ['type' => 'phone', 'brand' => 'Apple', 'model' => 'iPhone 13'],
            [], // sem reported_issue: o banco recusa
        ))->toThrow(QueryException::class);

        expect(Customer::count())->toBe(0)->and(Device::count())->toBe(0);
    });

    test('recusa aparelho de outro cliente', function () {
        $customer = Customer::factory()->create();
        $otherDevice = Device::factory()->create();

        app(OpenServiceOrder::class)->handle($this->admin, $customer, $otherDevice, ['reported_issue' => 'x']);
    })->throws(InvalidArgumentException::class);
});

describe('tela', function () {
    test('abre OS para cliente e aparelho existentes', function () {
        $device = Device::factory()->create();

        Livewire::actingAs($this->technician)
            ->test(Create::class)
            ->set('customerSearch', $device->customer->name)
            ->assertSee($device->customer->name)
            ->call('selectCustomer', $device->customer_id)
            ->assertSet('deviceId', $device->id) // único aparelho já vem escolhido
            ->set('form.reported_issue', 'Tela quebrada')
            ->set('form.accessories', 'Capa')
            ->set('form.device_password', '1234')
            ->set('form.estimated_delivery_date', '2026-12-01')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('service-orders.show', ServiceOrder::first()));

        $order = ServiceOrder::sole();

        expect($order)
            ->device_id->toBe($device->id)
            ->customer_id->toBe($device->customer_id)
            ->user_id->toBe($this->technician->id)
            ->technician_id->toBe($this->technician->id) // técnico que abre fica responsável
            ->reported_issue->toBe('Tela quebrada')
            ->device_password->toBe('1234')
            // 01/12 às 18h em Cuiabá = 22h UTC
            ->and($order->estimated_delivery_at->toDateTimeString())->toBe('2026-12-01 22:00:00');
    });

    test('cadastra cliente e aparelho novos na hora', function () {
        Livewire::actingAs($this->admin)
            ->test(Create::class)
            ->set('customerMode', 'new')
            ->assertSet('deviceMode', 'new')
            ->set('customerForm.name', 'Paula Mendes')
            ->set('customerForm.phone', '(65) 98888-7777')
            ->set('deviceForm.type', 'notebook')
            ->set('deviceForm.brand', 'Dell')
            ->set('deviceForm.model', 'Inspiron 15')
            ->set('form.reported_issue', 'Não liga')
            ->call('save')
            ->assertHasNoErrors();

        $order = ServiceOrder::with('customer', 'device')->sole();

        expect($order->customer->phone)->toBe('65988887777')
            ->and($order->device->brand)->toBe('Dell')
            ->and($order->technician_id)->toBeNull(); // admin não vira técnico automaticamente
    });

    test('cadastra aparelho novo para cliente existente', function () {
        $customer = Customer::factory()->has(Device::factory())->create();

        Livewire::actingAs($this->admin)
            ->test(Create::class)
            ->call('selectCustomer', $customer->id)
            ->set('deviceMode', 'new')
            ->set('deviceForm.brand', 'Motorola')
            ->set('deviceForm.model', 'Moto G84')
            ->set('form.reported_issue', 'Não carrega')
            ->call('save')
            ->assertHasNoErrors();

        expect($customer->devices()->count())->toBe(2)
            ->and(ServiceOrder::sole()->device->model)->toBe('Moto G84');
    });

    test('pré-seleciona cliente e aparelho vindos da tela do cliente', function () {
        $customer = Customer::factory()->create();
        $devices = Device::factory(2)->for($customer)->create();

        Livewire::actingAs($this->admin)
            ->withQueryParams(['cliente' => $customer->id, 'aparelho' => $devices[1]->id])
            ->test(Create::class)
            ->assertSet('customerId', $customer->id)
            ->assertSet('deviceId', $devices[1]->id);
    });

    test('mostra todos os erros de uma vez', function () {
        Livewire::actingAs($this->admin)
            ->test(Create::class)
            ->call('save')
            ->assertHasErrors(['customerId', 'form.reported_issue']);

        Livewire::actingAs($this->admin)
            ->test(Create::class)
            ->set('customerMode', 'new')
            ->call('save')
            ->assertHasErrors(['customerForm.name', 'customerForm.phone', 'deviceForm.brand', 'form.reported_issue']);

        expect(ServiceOrder::count())->toBe(0);
    });

    test('exige escolher um aparelho do cliente', function () {
        $customer = Customer::factory()->create();
        Device::factory(2)->for($customer)->create();

        Livewire::actingAs($this->admin)
            ->test(Create::class)
            ->call('selectCustomer', $customer->id)
            ->assertSet('deviceId', null)
            ->set('form.reported_issue', 'Não liga')
            ->call('save')
            ->assertHasErrors('deviceId');
    });

    test('não aceita aparelho de outro cliente forjado na requisição', function () {
        $customer = Customer::factory()->has(Device::factory())->create();
        $otherDevice = Device::factory()->create();

        Livewire::actingAs($this->admin)
            ->test(Create::class)
            ->call('selectCustomer', $customer->id)
            ->set('deviceId', $otherDevice->id)
            ->set('form.reported_issue', 'Não liga')
            ->call('save')
            ->assertHasErrors('deviceId');

        expect(ServiceOrder::count())->toBe(0);
    });
});
