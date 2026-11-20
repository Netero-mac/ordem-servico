<?php

use App\Actions\ServiceOrders\GenerateServiceOrderNumber;
use App\Enums\ServiceOrderStatus;
use App\Models\ServiceOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

test('gera números sequenciais no formato OS-ANO-0000', function () {
    $this->travelTo(now()->setDate(2026, 6, 15));

    $numbers = ServiceOrder::factory(3)->create()->pluck('number')->all();

    expect($numbers)->toBe(['OS-2026-0001', 'OS-2026-0002', 'OS-2026-0003']);
});

test('a numeração reinicia no ano novo', function () {
    $this->travelTo(now()->setDate(2026, 12, 20));
    ServiceOrder::factory(2)->create();

    $this->travelTo(now()->setDate(2027, 1, 10));

    expect(ServiceOrder::factory()->create()->number)->toBe('OS-2027-0001');
});

test('o ano da OS segue o fuso da loja, não o UTC', function () {
    // 01/01/2027 02:00 UTC ainda é 31/12/2026 22:00 em Cuiabá.
    $this->travelTo(Carbon::parse('2027-01-01 02:00:00', 'UTC'));

    expect(app(GenerateServiceOrderNumber::class)->handle())->toBe('OS-2026-0001');
});

test('a numeração não se perde quando uma OS é excluída', function () {
    $this->travelTo(now()->setDate(2026, 3, 1));
    ServiceOrder::factory(2)->create()->last()->forceDelete();

    expect(ServiceOrder::factory()->create()->number)->toBe('OS-2026-0003');
});

test('gera token público longo, aleatório e único', function () {
    $orders = ServiceOrder::factory(5)->create();

    $orders->each(fn (ServiceOrder $order) => expect($order->public_token)
        ->toHaveLength(ServiceOrder::PUBLIC_TOKEN_LENGTH)
        ->toMatch('/^[A-Za-z0-9]+$/'));

    expect($orders->pluck('public_token')->unique())->toHaveCount(5);
});

test('registra a abertura no histórico', function () {
    $order = ServiceOrder::factory()->create();

    $history = $order->statusHistory()->sole();

    expect($history->from_status)->toBeNull()
        ->and($history->to_status)->toBe(ServiceOrderStatus::Received)
        ->and($history->user_id)->toBe($order->user_id);
});

test('a senha do aparelho fica criptografada no banco', function () {
    $order = ServiceOrder::factory()->create(['device_password' => '1234']);

    $raw = DB::table('service_orders')->where('id', $order->id)->value('device_password');

    expect($raw)->not->toBe('1234')
        ->and($raw)->not->toContain('1234')
        ->and($order->fresh()->device_password)->toBe('1234');
});

test('senha do aparelho e token não aparecem na serialização', function () {
    $order = ServiceOrder::factory()->create(['device_password' => '1234']);

    expect($order->toArray())->not->toHaveKeys(['device_password', 'public_token']);
});

test('status não pode ser definido por atribuição em massa', function () {
    $order = new ServiceOrder(['status' => 'delivered']);

    expect($order->status)->toBe(ServiceOrderStatus::Received);
});

test('garantia padrão é de 90 dias', function () {
    expect(ServiceOrder::factory()->create()->warranty_days)->toBe(90);
});
