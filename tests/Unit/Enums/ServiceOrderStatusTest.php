<?php

use App\Enums\ServiceOrderStatus as S;

/**
 * Mapa completo e explícito das transições permitidas.
 * Qualquer par fora desta lista precisa ser bloqueado.
 */
const ALLOWED_TRANSITIONS = [
    'received' => ['in_analysis', 'cancelled'],
    'in_analysis' => ['awaiting_approval', 'cancelled'],
    'awaiting_approval' => ['approved', 'rejected', 'cancelled'],
    'approved' => ['awaiting_part', 'in_repair', 'cancelled'],
    'awaiting_part' => ['in_repair', 'cancelled'],
    'in_repair' => ['awaiting_part', 'ready'],
    'rejected' => ['ready'],
    'ready' => ['delivered'],
    'delivered' => [],
    'cancelled' => [],
];

dataset('every status pair', function () {
    foreach (S::cases() as $from) {
        foreach (S::cases() as $to) {
            yield "{$from->value} → {$to->value}" => [$from, $to];
        }
    }
});

it('permite somente as transições previstas', function (S $from, S $to) {
    $expected = in_array($to->value, ALLOWED_TRANSITIONS[$from->value], true);

    expect($from->canTransitionTo($to))->toBe($expected);
})->with('every status pair');

it('não permite manter o mesmo status', function (S $status) {
    expect($status->canTransitionTo($status))->toBeFalse();
})->with(S::cases());

it('trata entregue e cancelado como status finais', function () {
    expect(S::Delivered->isFinal())->toBeTrue()
        ->and(S::Cancelled->isFinal())->toBeTrue()
        ->and(S::openStatuses())->not->toContain(S::Delivered, S::Cancelled)
        ->and(S::openStatuses())->toHaveCount(8);
});

it('permite devolver o aparelho quando o orçamento é recusado', function () {
    expect(S::Rejected->canTransitionTo(S::Ready))->toBeTrue()
        ->and(S::Rejected->canTransitionTo(S::InRepair))->toBeFalse();
});

it('tem rótulo em português para todos os status', function () {
    expect(S::options())->toBe([
        'received' => 'Recebido',
        'in_analysis' => 'Em análise',
        'awaiting_approval' => 'Aguardando aprovação',
        'approved' => 'Aprovado',
        'rejected' => 'Recusado',
        'awaiting_part' => 'Aguardando peça',
        'in_repair' => 'Em conserto',
        'ready' => 'Pronto para retirada',
        'delivered' => 'Entregue',
        'cancelled' => 'Cancelado',
    ]);
});

it('todo status não final alcança entregue ou cancelado', function (S $start) {
    $visited = [];
    $queue = [$start];

    while ($queue !== []) {
        $current = array_shift($queue);
        $visited[$current->value] = true;

        foreach ($current->allowedTransitions() as $next) {
            if (! isset($visited[$next->value])) {
                $queue[] = $next;
            }
        }
    }

    expect(isset($visited['delivered']) || isset($visited['cancelled']))->toBeTrue();
})->with(S::cases());
