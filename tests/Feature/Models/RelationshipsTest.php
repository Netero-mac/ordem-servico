<?php

use App\Enums\DeviceType;
use App\Enums\ItemType;
use App\Enums\ServiceOrderStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Device;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderItem;
use App\Models\ServiceOrderPhoto;
use App\Models\User;
use Illuminate\Database\QueryException;

test('cliente tem aparelhos e ordens de serviço', function () {
    $customer = Customer::factory()->create();
    $devices = Device::factory(2)->for($customer)->create();
    ServiceOrder::factory()->create(['device_id' => $devices[0]->id]);

    expect($customer->devices)->toHaveCount(2)
        ->and($customer->serviceOrders)->toHaveCount(1)
        ->and($devices[0]->customer->is($customer))->toBeTrue();
});

test('aparelho tem histórico de ordens de serviço', function () {
    $device = Device::factory()->create();
    ServiceOrder::factory(3)->create(['device_id' => $device->id]);

    expect($device->serviceOrders)->toHaveCount(3)
        ->and($device->type)->toBeInstanceOf(DeviceType::class);
});

test('a OS pertence ao dono do aparelho por padrão', function () {
    $order = ServiceOrder::factory()->create();

    expect($order->customer_id)->toBe($order->device->customer_id);
});

test('OS sabe quem abriu e quem é o técnico responsável', function () {
    $attendant = User::factory()->admin()->create();
    $technician = User::factory()->technician()->create();

    $order = ServiceOrder::factory()
        ->forTechnician($technician)
        ->create(['user_id' => $attendant->id]);

    expect($order->openedBy->is($attendant))->toBeTrue()
        ->and($order->technician->is($technician))->toBeTrue()
        ->and($attendant->openedServiceOrders)->toHaveCount(1)
        ->and($technician->assignedServiceOrders)->toHaveCount(1)
        ->and($attendant->isAdmin())->toBeTrue()
        ->and($technician->isAdmin())->toBeFalse()
        ->and($technician->role)->toBe(UserRole::Technician);
});

test('técnico é opcional', function () {
    $order = ServiceOrder::factory()->create(['technician_id' => null]);

    expect($order->technician)->toBeNull();
});

test('OS tem itens, fotos e histórico de status', function () {
    $order = ServiceOrder::factory()->create();
    ServiceOrderItem::factory()->part()->for($order)->create();
    ServiceOrderItem::factory()->labor()->for($order)->create();
    ServiceOrderPhoto::factory(2)->for($order)->create();

    expect($order->items)->toHaveCount(2)
        ->and($order->items->pluck('type')->all())->toEqualCanonicalizing([ItemType::Part, ItemType::Labor])
        ->and($order->photos)->toHaveCount(2)
        ->and($order->statusHistory)->toHaveCount(1)
        ->and($order->photos->first()->serviceOrder->is($order))->toBeTrue();
});

test('o total do orçamento soma quantidade × preço unitário em centavos', function () {
    $order = ServiceOrder::factory()->create();
    ServiceOrderItem::factory()->for($order)->create(['quantity' => 2, 'unit_price_cents' => 4_990]);
    ServiceOrderItem::factory()->labor()->for($order)->create(['quantity' => 1, 'unit_price_cents' => 15_000]);

    expect($order->items->first()->totalCents())->toBe(9_980)
        ->and($order->totalCents())->toBe(24_980);
});

test('excluir a OS apaga itens e fotos em cascata quando removida de vez', function () {
    $order = ServiceOrder::factory()->create();
    ServiceOrderItem::factory()->for($order)->create();
    ServiceOrderPhoto::factory()->for($order)->create();

    $order->forceDelete();

    expect(ServiceOrderItem::count())->toBe(0)
        ->and(ServiceOrderPhoto::count())->toBe(0);
});

test('cliente com OS não pode ser removido de vez do banco', function () {
    $order = ServiceOrder::factory()->create();

    $order->customer->forceDelete();
})->throws(QueryException::class);

test('cliente excluído logicamente continua visível nas OS antigas', function () {
    $order = ServiceOrder::factory()->create();
    $order->customer->delete();

    expect($order->fresh()->customer)->not->toBeNull()
        ->and($order->fresh()->customer->trashed())->toBeTrue()
        ->and(Customer::count())->toBe(0);
});

test('status padrão de uma OS nova é recebido', function () {
    expect(ServiceOrder::factory()->create()->status)->toBe(ServiceOrderStatus::Received);
});
