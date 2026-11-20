<?php

use App\Enums\ServiceOrderStatus as S;
use App\Livewire\ServiceOrders\Index;
use App\Models\Customer;
use App\Models\Device;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs($this->user = User::factory()->technician()->create());
});

function orderFor(string $customerName, array $attributes = [], ?S $status = null): ServiceOrder
{
    $device = Device::factory()->for(Customer::factory()->state(['name' => $customerName]))->create();

    return ServiceOrder::factory()
        ->status($status ?? S::Received)
        ->create(['device_id' => $device->id, 'estimated_delivery_at' => null, ...$attributes]);
}

test('a tela abre', function () {
    $this->get(route('service-orders.index'))->assertOk()->assertSee('Ordens de serviço');
});

test('filtra por status e por "em aberto"', function () {
    orderFor('Cliente Recebido');
    orderFor('Cliente Conserto', status: S::InRepair);
    orderFor('Cliente Entregue', status: S::Delivered);

    Livewire::test(Index::class)
        ->set('status', 'in_repair')
        ->assertSee('Cliente Conserto')
        ->assertDontSee('Cliente Recebido')
        ->assertDontSee('Cliente Entregue')
        ->set('status', 'open')
        ->assertSee('Cliente Recebido')
        ->assertSee('Cliente Conserto')
        ->assertDontSee('Cliente Entregue');
});

test('filtra por técnico e por OS sem técnico', function () {
    $other = User::factory()->technician()->create();
    orderFor('Cliente Meu', ['technician_id' => $this->user->id]);
    orderFor('Cliente Outro', ['technician_id' => $other->id]);
    orderFor('Cliente Sem', ['technician_id' => null]);

    Livewire::test(Index::class)
        ->set('technician', (string) $this->user->id)
        ->assertSee('Cliente Meu')
        ->assertDontSee('Cliente Outro')
        ->assertDontSee('Cliente Sem')
        ->set('technician', 'none')
        ->assertSee('Cliente Sem')
        ->assertDontSee('Cliente Meu');
});

test('filtra por período de entrada no fuso da loja', function () {
    // 10/11 23:30 em Cuiabá = 11/11 03:30 UTC: deve contar como dia 10.
    $this->travelTo(Carbon::parse('2026-11-11 03:30:00', 'UTC'));
    orderFor('Cliente Dia Dez');

    $this->travelTo(Carbon::parse('2026-11-12 15:00:00', 'UTC'));
    orderFor('Cliente Dia Doze');

    Livewire::test(Index::class)
        ->set('from', '2026-11-10')
        ->set('to', '2026-11-10')
        ->assertSee('Cliente Dia Dez')
        ->assertDontSee('Cliente Dia Doze')
        ->set('from', '2026-11-11')
        ->set('to', '')
        ->assertSee('Cliente Dia Doze')
        ->assertDontSee('Cliente Dia Dez');
});

test('busca por número ou nome do cliente', function () {
    $order = orderFor('Fernanda Alves');
    orderFor('Gustavo Lima');

    Livewire::test(Index::class)
        ->set('search', $order->number)
        ->assertSee('Fernanda Alves')
        ->assertDontSee('Gustavo Lima')
        ->set('search', 'gustavo')
        ->assertSee('Gustavo Lima')
        ->assertDontSee('Fernanda Alves');
});

test('destaca e filtra as OS atrasadas', function () {
    orderFor('Cliente Atrasado', ['estimated_delivery_at' => now()->subDay()], S::InRepair);
    orderFor('Cliente No Prazo', ['estimated_delivery_at' => now()->addDay()], S::InRepair);
    orderFor('Cliente Pronto', ['estimated_delivery_at' => now()->subDay()], S::Ready);

    Livewire::test(Index::class)
        ->assertSee('1 OS com previsão de entrega vencida')
        ->assertSee('Atrasada')
        ->set('onlyOverdue', true)
        ->assertSee('Cliente Atrasado')
        ->assertDontSee('Cliente No Prazo')
        ->assertDontSee('Cliente Pronto');
});

test('filtros ficam na URL e podem ser limpos', function () {
    Livewire::withQueryParams(['status' => 'ready', 'atrasadas' => '1'])
        ->test(Index::class)
        ->assertSet('status', 'ready')
        ->assertSet('onlyOverdue', true)
        ->call('clearFilters')
        ->assertSet('status', '')
        ->assertSet('onlyOverdue', false);
});
