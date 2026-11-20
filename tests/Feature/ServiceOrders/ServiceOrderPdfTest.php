<?php

use App\Enums\ServiceOrderStatus as S;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderItem;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;

beforeEach(function () {
    $this->user = User::factory()->technician()->create();
    config(['shop.name' => 'Loja Teste']);
});

test('visitante não acessa os PDFs', function () {
    $order = ServiceOrder::factory()->status(S::Delivered)->create();

    $this->get(route('service-orders.pdf.entry', $order))->assertRedirect(route('login'));
    $this->get(route('service-orders.pdf.delivery', $order))->assertRedirect(route('login'));
});

test('gera o comprovante de entrada em PDF', function () {
    $order = ServiceOrder::factory()->create();

    $response = $this->actingAs($this->user)->get(route('service-orders.pdf.entry', $order));

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($response->getContent())->toStartWith('%PDF');
});

test('o comprovante de entrada tem termos, garantia e assinatura, sem a senha', function () {
    $order = ServiceOrder::factory()->create([
        'warranty_days' => 120,
        'accessories' => 'Carregador original',
        'device_password' => 'senha-9876',
    ]);
    ServiceOrderItem::factory()->for($order)->create(['quantity' => 2, 'unit_price_cents' => 5000]);
    $order->load(['customer', 'device', 'items', 'openedBy', 'technician']);

    $html = view('pdf.entry-receipt', [
        'order' => $order,
        'shop' => config('shop'),
        'totals' => ['parts' => 10000, 'labor' => 0, 'total' => 10000],
    ])->render();

    expect($html)
        ->toContain('Comprovante de entrada')
        ->toContain($order->number)
        ->toContain('Loja Teste')
        ->toContain('Carregador original')
        ->toContain('Garantia de 120 dias')
        ->toContain('Termo de garantia')
        ->toContain('declaro estar de acordo')
        ->toContain('R$ 100,00')
        ->toContain(route('tracking.show', $order->public_token))
        ->not->toContain('senha-9876');
});

test('o controller calcula os totais por tipo', function () {
    $order = ServiceOrder::factory()->create();
    ServiceOrderItem::factory()->part()->for($order)->create(['quantity' => 2, 'unit_price_cents' => 5000]);
    ServiceOrderItem::factory()->labor()->for($order)->create(['quantity' => 1, 'unit_price_cents' => 8000]);

    Pdf::spy();
    $this->actingAs($this->user)->get(route('service-orders.pdf.entry', $order));

    Pdf::shouldHaveReceived('loadView')->withArgs(
        fn (string $view, array $data) => $view === 'pdf.entry-receipt'
            && $data['totals'] === ['parts' => 10000, 'labor' => 8000, 'total' => 18000]
    );
});

test('o comprovante de entrega só existe para OS pronta ou entregue', function (S $status, int $expected) {
    $order = ServiceOrder::factory()->status($status)->create();

    $this->actingAs($this->user)->get(route('service-orders.pdf.delivery', $order))->assertStatus($expected);
})->with([
    [S::InRepair, 403],
    [S::Received, 403],
    [S::Ready, 200],
    [S::Delivered, 200],
]);

test('o comprovante de entrega mostra data, garantia até e termos', function () {
    $order = ServiceOrder::factory()->status(S::Delivered)->create(['warranty_days' => 90]);
    $order->forceFill([
        'approved_at' => '2026-11-01 15:00:00',
        'delivered_at' => '2026-11-10 15:00:00',
    ])->save();
    ServiceOrderItem::factory()->for($order)->create(['quantity' => 1, 'unit_price_cents' => 25000]);
    $order->load(['customer', 'device', 'items', 'openedBy', 'technician']);

    $html = view('pdf.delivery-receipt', [
        'order' => $order, 'shop' => config('shop'),
        'totals' => ['parts' => 25000, 'labor' => 0, 'total' => 25000],
    ])->render();

    expect($html)
        ->toContain('Comprovante de entrega')
        ->toContain('10/11/2026 11:00')          // 15h UTC = 11h em Cuiabá
        ->toContain('90 dias')
        ->toContain('até 08/02/2027')
        ->toContain('Recebi o aparelho')
        ->toContain('R$ 250,00');
});

test('entrega de orçamento recusado não mostra garantia', function () {
    $order = ServiceOrder::factory()->status(S::Delivered)->create();
    $order->load(['customer', 'device', 'items', 'openedBy', 'technician']);

    $html = view('pdf.delivery-receipt', [
        'order' => $order, 'shop' => config('shop'),
        'totals' => ['parts' => 0, 'labor' => 0, 'total' => 0],
    ])->render();

    expect($html)
        ->toContain('devolvido sem reparo')
        ->not->toContain('Termo de garantia');
});

test('a tela da OS mostra os botões de impressão conforme o status', function () {
    $open = ServiceOrder::factory()->status(S::InRepair)->create();
    $ready = ServiceOrder::factory()->status(S::Ready)->create();

    $this->actingAs($this->user)->get(route('service-orders.show', $open))
        ->assertSee('Comprovante de entrada')
        ->assertDontSee('Comprovante de entrega');

    $this->actingAs($this->user)->get(route('service-orders.show', $ready))
        ->assertSee('Comprovante de entrega');
});
