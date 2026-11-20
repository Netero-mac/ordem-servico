<?php

use App\Actions\ServiceOrders\ChangeServiceOrderStatus;
use App\Enums\ServiceOrderStatus as S;
use App\Exceptions\InvalidStatusTransition;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderItem;
use App\Models\User;

beforeEach(function () {
    $this->action = app(ChangeServiceOrderStatus::class);
    $this->technician = User::factory()->technician()->create();
});

test('muda o status e grava o histórico com usuário e observação', function () {
    $order = ServiceOrder::factory()->create();

    $order = $this->action->handle($order, S::InAnalysis, $this->technician, '  Aparelho na bancada.  ');

    expect($order->status)->toBe(S::InAnalysis)
        ->and($order->fresh()->status)->toBe(S::InAnalysis);

    $last = $order->statusHistory()->get()->last();

    expect($last->from_status)->toBe(S::Received)
        ->and($last->to_status)->toBe(S::InAnalysis)
        ->and($last->user_id)->toBe($this->technician->id)
        ->and($last->note)->toBe('Aparelho na bancada.');
});

test('observação vazia é gravada como nula', function () {
    $order = $this->action->handle(ServiceOrder::factory()->create(), S::InAnalysis, $this->technician, '   ');

    expect($order->statusHistory()->get()->last()->note)->toBeNull();
});

test('bloqueia transição inválida sem alterar nada', function () {
    $order = ServiceOrder::factory()->create();

    expect(fn () => $this->action->handle($order, S::Delivered, $this->technician))
        ->toThrow(InvalidStatusTransition::class, 'Não é possível mudar o status de "Recebido" para "Entregue".');

    expect($order->fresh()->status)->toBe(S::Received)
        ->and($order->statusHistory()->count())->toBe(1);
});

test('não permite mudar o status de uma OS entregue', function () {
    $order = ServiceOrder::factory()->status(S::Delivered)->create();

    $this->action->handle($order, S::InRepair, $this->technician);
})->throws(InvalidStatusTransition::class);

test('valida contra o status atual do banco, não o da instância em memória', function () {
    $order = ServiceOrder::factory()->status(S::AwaitingApproval)->create();
    $stale = ServiceOrder::find($order->id);

    // O cliente cancela por outro caminho...
    $this->action->handle($order, S::Cancelled, null);

    // ...e a instância desatualizada não consegue mais aprovar.
    expect(fn () => $this->action->handle($stale, S::Approved, $this->technician))
        ->toThrow(InvalidStatusTransition::class);
});

test('registra a data de aprovação', function () {
    $this->freezeSecond();
    $order = ServiceOrder::factory()->status(S::AwaitingApproval)->create();

    $order = $this->action->handle($order, S::Approved, null, 'Aprovado pelo cliente via link.');

    expect($order->approved_at->equalTo(now()))->toBeTrue()
        ->and($order->statusHistory()->get()->last()->user_id)->toBeNull();
});

test('registra a data de entrega', function () {
    $this->freezeSecond();
    $order = ServiceOrder::factory()->status(S::Ready)->create();

    $order = $this->action->handle($order, S::Delivered, $this->technician);

    expect($order->delivered_at->equalTo(now()))->toBeTrue();
});

test('não envia para aprovação um orçamento sem itens', function () {
    $order = ServiceOrder::factory()->status(S::InAnalysis)->create();

    expect(fn () => $this->action->handle($order, S::AwaitingApproval, $this->technician))
        ->toThrow(InvalidStatusTransition::class, 'Adicione ao menos um item ao orçamento');

    ServiceOrderItem::factory()->for($order)->create();

    expect($this->action->handle($order, S::AwaitingApproval, $this->technician)->status)->toBe(S::AwaitingApproval);
});

test('percorre o fluxo completo e mantém o histórico encadeado', function () {
    $order = ServiceOrder::factory()->create();
    ServiceOrderItem::factory()->for($order)->create();
    $path = [S::InAnalysis, S::AwaitingApproval, S::Approved, S::AwaitingPart, S::InRepair, S::Ready, S::Delivered];

    foreach ($path as $status) {
        $order = $this->action->handle($order, $status, $this->technician);
    }

    $history = $order->statusHistory()->get();

    expect($history)->toHaveCount(8)
        ->and($history->pluck('to_status')->all())->toBe([S::Received, ...$path]);

    $history->skip(1)->values()->each(
        fn ($row, $i) => expect($row->from_status)->toBe($history[$i]->to_status)
    );
});
