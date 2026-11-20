<?php

use App\Enums\ServiceOrderStatus as S;
use App\Livewire\Dashboard;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderItem;
use App\Models\User;
use App\Services\DashboardMetrics;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    // 15/11/2026 12:00 em Cuiabá
    $this->travelTo(Carbon::parse('2026-11-15 16:00:00', 'UTC'));
    $this->metrics = app(DashboardMetrics::class);
});

function deliveredOrder(string $deliveredAtUtc, int $cents, bool $approved = true): ServiceOrder
{
    $order = ServiceOrder::factory()->status(S::Delivered)->create([
        'delivered_at' => Carbon::parse($deliveredAtUtc, 'UTC'),
    ]);
    $order->forceFill(['approved_at' => $approved ? Carbon::parse($deliveredAtUtc, 'UTC')->subDay() : null])->save();
    ServiceOrderItem::factory()->for($order)->create(['quantity' => 1, 'unit_price_cents' => $cents]);

    return $order;
}

test('conta OS em aberto por status, incluindo zeros, na ordem do fluxo', function () {
    ServiceOrder::factory(2)->status(S::InRepair)->create();
    ServiceOrder::factory()->status(S::Received)->create();
    ServiceOrder::factory()->status(S::Delivered)->create();
    ServiceOrder::factory()->status(S::Cancelled)->create();
    ServiceOrder::factory()->status(S::InRepair)->create()->delete(); // excluída não conta

    $byStatus = $this->metrics->openByStatus();

    expect(array_keys($byStatus))->toBe(array_map(fn ($s) => $s->value, S::openStatuses()))
        ->and($byStatus['in_repair'])->toBe(2)
        ->and($byStatus['received'])->toBe(1)
        ->and($byStatus['ready'])->toBe(0)
        ->and(array_sum($byStatus))->toBe(3);
});

test('conta e lista as atrasadas, da mais antiga para a mais recente', function () {
    $older = ServiceOrder::factory()->status(S::InRepair)->create(['estimated_delivery_at' => now()->subDays(5)]);
    $newer = ServiceOrder::factory()->status(S::Received)->create(['estimated_delivery_at' => now()->subDay()]);
    ServiceOrder::factory()->status(S::Ready)->create(['estimated_delivery_at' => now()->subDays(3)]);

    expect($this->metrics->overdueCount())->toBe(2)
        ->and($this->metrics->mostOverdue()->pluck('id')->all())->toBe([$older->id, $newer->id]);
});

test('faturamento soma só OS entregues e aprovadas no mês', function () {
    deliveredOrder('2026-11-10 15:00:00', 30000);
    deliveredOrder('2026-11-12 15:00:00', 15050);
    deliveredOrder('2026-11-11 15:00:00', 99900, approved: false);   // recusada: devolvida sem conserto
    deliveredOrder('2026-10-20 15:00:00', 50000);                    // mês passado
    deliveredOrder('2026-11-13 15:00:00', 70000)->delete();          // excluída

    $inRepair = ServiceOrder::factory()->status(S::InRepair)->create();
    ServiceOrderItem::factory()->for($inRepair)->create(['unit_price_cents' => 80000]); // ainda não entregue

    expect($this->metrics->revenueInMonth())->toBe(45050)
        ->and($this->metrics->revenueLastMonth())->toBe(50000)
        ->and($this->metrics->deliveredInMonth())->toBe(3); // 3 entregas (inclui a recusada), sem a excluída
});

test('faturamento multiplica quantidade pelo valor unitário', function () {
    $order = deliveredOrder('2026-11-10 15:00:00', 10000);
    ServiceOrderItem::factory()->for($order)->create(['quantity' => 3, 'unit_price_cents' => 2500]);

    expect($this->metrics->revenueInMonth())->toBe(17500);
});

test('o mês segue o fuso da loja nas viradas', function () {
    // 01/11 02:00 UTC = 31/10 22:00 em Cuiabá → outubro
    deliveredOrder('2026-11-01 02:00:00', 10000);
    // 01/12 03:30 UTC = 30/11 23:30 em Cuiabá → novembro
    deliveredOrder('2026-12-01 03:30:00', 20000);

    expect($this->metrics->revenueInMonth())->toBe(20000)
        ->and($this->metrics->revenueLastMonth())->toBe(10000);
});

test('o painel mostra os indicadores', function () {
    deliveredOrder('2026-11-10 15:00:00', 123456);
    deliveredOrder('2026-10-10 15:00:00', 100000);
    ServiceOrder::factory()->status(S::InRepair)->create(['estimated_delivery_at' => now()->subDay()]);

    Livewire::actingAs(User::factory()->create())
        ->test(Dashboard::class)
        ->assertSee('Novembro de 2026')
        ->assertSeeHtml('data-test="open-total">1<')
        ->assertSeeHtml('data-test="overdue-total">1<')
        ->assertSeeHtml('data-test="delivered-total">1<')
        ->assertSee('R$ 1.234,56')
        ->assertSee('+23% vs. mês anterior')
        ->assertSee('Em conserto');
});

test('painel sem dados não quebra', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(Dashboard::class)
        ->assertSee('R$ 0,00')
        ->assertSee('Sem faturamento no mês anterior')
        ->assertSee('Nenhuma OS atrasada');
});
