<?php

use App\Enums\ServiceOrderStatus as S;
use App\Models\ServiceOrder;

test('OS em serviço com previsão vencida está atrasada', function (S $status, bool $overdue) {
    $order = ServiceOrder::factory()->status($status)->create(['estimated_delivery_at' => now()->subHour()]);

    expect($order->isOverdue())->toBe($overdue)
        ->and(ServiceOrder::overdue()->whereKey($order->id)->exists())->toBe($overdue);
})->with([
    [S::Received, true],
    [S::InAnalysis, true],
    [S::AwaitingApproval, true],
    [S::Approved, true],
    [S::Rejected, true],
    [S::AwaitingPart, true],
    [S::InRepair, true],
    [S::Ready, false],
    [S::Delivered, false],
    [S::Cancelled, false],
]);

test('OS sem previsão ou com previsão futura não está atrasada', function () {
    $noEstimate = ServiceOrder::factory()->create(['estimated_delivery_at' => null]);
    $future = ServiceOrder::factory()->create(['estimated_delivery_at' => now()->addDay()]);

    expect($noEstimate->isOverdue())->toBeFalse()
        ->and($future->isOverdue())->toBeFalse()
        ->and(ServiceOrder::overdue()->count())->toBe(0);
});
