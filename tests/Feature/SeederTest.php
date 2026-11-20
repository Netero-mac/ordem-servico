<?php

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\ServiceOrder;
use App\Models\User;

test('o seeder cria os dados de demonstração com histórico coerente', function () {
    $this->seed();

    expect(User::where('role', UserRole::Admin)->count())->toBe(1)
        ->and(User::where('role', UserRole::Technician)->count())->toBe(1)
        ->and(Customer::count())->toBe(10)
        ->and(ServiceOrder::count())->toBe(20)
        ->and(ServiceOrder::distinct()->count('status'))->toBeGreaterThanOrEqual(8);

    ServiceOrder::with('statusHistory')->get()->each(function (ServiceOrder $order) {
        // Todas as OS abertas nas últimas semanas, e nenhum evento no futuro.
        expect($order->created_at->greaterThan(now()->subDays(26)))->toBeTrue()
            ->and($order->statusHistory->last()->created_at->lessThanOrEqualTo(now()))->toBeTrue();

        expect($order->statusHistory->last()->to_status)->toBe($order->status)
            ->and($order->customer_id)->toBe($order->device->customer_id);
    });
});
