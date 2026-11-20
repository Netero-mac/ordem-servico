<?php

use App\Actions\ServiceOrders\ChangeServiceOrderStatus;
use App\Enums\ServiceOrderStatus as S;
use App\Models\Customer;
use App\Models\Device;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderItem;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->technician = User::factory()->technician()->create(['name' => 'Técnico Interno Fulano']);
    $customer = Customer::factory()->create([
        'name' => 'Mariana Costa Ribeiro',
        'phone' => '65999998888',
        'document' => '52998224725',
        'email' => 'mariana@example.com',
        'notes' => 'Cliente pede desconto sempre',
    ]);
    $device = Device::factory()->for($customer)->create([
        'brand' => 'Samsung', 'model' => 'Galaxy A54', 'serial_or_imei' => '356789012345678',
    ]);

    $this->order = ServiceOrder::factory()->status(S::InAnalysis)->create([
        'device_id' => $device->id,
        'user_id' => $this->technician->id,
        'technician_id' => $this->technician->id,
        'device_password' => 'senha-secreta-9876',
        'diagnosis' => 'Laudo interno confidencial',
        'accessories' => 'Capinha rosa',
    ]);
    ServiceOrderItem::factory()->part()->for($this->order)->create(['description' => 'Tela compatível', 'quantity' => 1, 'unit_price_cents' => 35000]);
    ServiceOrderItem::factory()->labor()->for($this->order)->create(['description' => 'Troca de tela', 'quantity' => 1, 'unit_price_cents' => 10000]);

    app(ChangeServiceOrderStatus::class)->handle($this->order, S::AwaitingApproval, $this->technician, 'Nota interna: cliente difícil');
    $this->url = route('tracking.show', $this->order->public_token);
});

test('abre sem login e mostra número, aparelho, status, linha do tempo e orçamento', function () {
    $this->get($this->url)
        ->assertOk()
        ->assertSee('Olá, Mariana!')
        ->assertSee($this->order->number)
        ->assertSee('Samsung Galaxy A54')
        ->assertSee('Aguardando aprovação')
        ->assertSee('Em análise') // linha do tempo
        ->assertSee('Tela compatível')
        ->assertSee('R$ 450,00')
        ->assertSee('Aprovar')
        ->assertSee('Recusar');
});

test('não mostra senha, CPF, contatos nem dados internos', function () {
    $html = $this->get($this->url)->assertOk()->getContent();

    expect($html)
        ->not->toContain('senha-secreta-9876')
        ->not->toContain('52998224725')
        ->not->toContain('529.982.247-25')
        ->not->toContain('99999-8888')
        ->not->toContain('65999998888')
        ->not->toContain('mariana@example.com')
        ->not->toContain('Costa Ribeiro')
        ->not->toContain('Cliente pede desconto')
        ->not->toContain('356789012345678')
        ->not->toContain('Laudo interno confidencial')
        ->not->toContain('Nota interna')
        ->not->toContain('Técnico Interno Fulano')
        ->not->toContain('Capinha rosa');
});

test('envia cabeçalhos para não indexar nem vazar o token', function () {
    $this->get($this->url)
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
});

test('token desconhecido ou fora do formato retorna 404', function (string $token) {
    $this->get('/acompanhar/'.$token)->assertNotFound();
})->with([
    'aleatório válido' => fn () => Str::random(64),
    'curto' => 'abc123',
    'com símbolos' => str_repeat('a', 63).'-',
    'número da OS' => 'OS-2026-0001',
]);

test('OS excluída não aparece mais no link', function () {
    $this->order->delete();

    $this->get($this->url)->assertNotFound();
});

test('o token é longo e impossível de adivinhar', function () {
    expect($this->order->public_token)->toHaveLength(64)
        ->and(ServiceOrder::factory()->create()->public_token)->not->toBe($this->order->public_token);
});

describe('decisão do cliente', function () {
    test('cliente aprova o orçamento e fica registrado sem usuário', function () {
        $this->post(route('tracking.decide', $this->order->public_token), ['decision' => 'approve', 'confirmed' => '1'])
            ->assertRedirect($this->url)
            ->assertSessionHas('success');

        $order = $this->order->fresh();
        $last = $order->statusHistory()->get()->last();

        expect($order->status)->toBe(S::Approved)
            ->and($order->approved_at)->not->toBeNull()
            ->and($last->user_id)->toBeNull()
            ->and($last->note)->toContain('aprovado pelo cliente');

        $this->get($this->url)->assertSee('Orçamento aprovado!')->assertDontSee('Sim, aprovar');
    });

    test('cliente recusa o orçamento informando o motivo', function () {
        $this->post(route('tracking.decide', $this->order->public_token), [
            'decision' => 'reject', 'confirmed' => '1', 'reason' => 'Vou comprar outro',
        ])->assertRedirect($this->url);

        $order = $this->order->fresh();

        expect($order->status)->toBe(S::Rejected)
            ->and($order->approved_at)->toBeNull()
            ->and($order->statusHistory()->get()->last()->note)->toContain('Motivo: Vou comprar outro');
    });

    test('exige confirmação', function () {
        $this->post(route('tracking.decide', $this->order->public_token), ['decision' => 'approve'])
            ->assertSessionHasErrors('confirmed');

        expect($this->order->fresh()->status)->toBe(S::AwaitingApproval);
    });

    test('rejeita decisão inválida', function () {
        $this->post(route('tracking.decide', $this->order->public_token), ['decision' => 'delivered', 'confirmed' => '1'])
            ->assertSessionHasErrors('decision');

        expect($this->order->fresh()->status)->toBe(S::AwaitingApproval);
    });

    test('não permite responder de novo nem fora de "aguardando aprovação"', function () {
        $this->post(route('tracking.decide', $this->order->public_token), ['decision' => 'approve', 'confirmed' => '1']);

        $this->post(route('tracking.decide', $this->order->public_token), ['decision' => 'reject', 'confirmed' => '1'])
            ->assertRedirect($this->url)
            ->assertSessionHas('error', 'Este orçamento não está mais aguardando aprovação.');

        expect($this->order->fresh()->status)->toBe(S::Approved)
            ->and($this->order->statusHistory()->count())->toBe(3); // abertura, aguardando, aprovado
    });

    test('botões de aprovação só aparecem aguardando aprovação', function () {
        $order = ServiceOrder::factory()->status(S::InRepair)->create();

        $this->get(route('tracking.show', $order->public_token))->assertOk()->assertDontSee('Sim, aprovar');
    });
});

describe('rate limiting', function () {
    test('limita acessos à página por IP', function () {
        RateLimiter::clear('public-tracking');

        foreach (range(1, 30) as $ignored) {
            $this->get($this->url)->assertOk();
        }

        $this->get($this->url)->assertStatus(429);
    });

    test('o limite também vale para tokens errados (evita força bruta)', function () {
        foreach (range(1, 30) as $ignored) {
            $this->get('/acompanhar/'.Str::random(64))->assertNotFound();
        }

        $this->get($this->url)->assertStatus(429);
    });

    test('limita tentativas de decisão por IP', function () {
        foreach (range(1, 5) as $ignored) {
            $this->post(route('tracking.decide', $this->order->public_token), ['decision' => 'x']);
        }

        $this->post(route('tracking.decide', $this->order->public_token), ['decision' => 'approve', 'confirmed' => '1'])
            ->assertStatus(429);

        expect($this->order->fresh()->status)->toBe(S::AwaitingApproval);
    });
});
