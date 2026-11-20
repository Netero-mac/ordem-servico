<?php

namespace App\Actions\ServiceOrders;

use App\Enums\ServiceOrderStatus;
use App\Exceptions\InvalidStatusTransition;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Único ponto do sistema que muda o status de uma OS.
 * Valida a transição pelo Enum e grava a linha no histórico.
 */
class ChangeServiceOrderStatus
{
    /**
     * @param  User|null  $user  null quando a ação vem do cliente pelo link público
     *
     * @throws InvalidStatusTransition
     */
    public function handle(ServiceOrder $order, ServiceOrderStatus $to, ?User $user, ?string $note = null): ServiceOrder
    {
        return DB::transaction(function () use ($order, $to, $user, $note): ServiceOrder {
            // Relê a OS com trava para evitar duas mudanças simultâneas
            // (ex.: cliente aprova pelo link enquanto o técnico cancela).
            $order = ServiceOrder::query()->lockForUpdate()->findOrFail($order->id);
            $from = $order->status;

            if (! $from->canTransitionTo($to)) {
                throw InvalidStatusTransition::between($from, $to);
            }

            if ($to === ServiceOrderStatus::AwaitingApproval && ! $order->items()->exists()) {
                throw new InvalidStatusTransition('Adicione ao menos um item ao orçamento antes de enviá-lo para aprovação.');
            }

            $order->status = $to;

            if ($to === ServiceOrderStatus::Approved) {
                $order->approved_at = now();
            }

            if ($to === ServiceOrderStatus::Delivered) {
                $order->delivered_at = now();
            }

            $order->save();

            $order->statusHistory()->create([
                'from_status' => $from,
                'to_status' => $to,
                'user_id' => $user?->id,
                'note' => filled($note) ? trim($note) : null,
            ]);

            return $order;
        });
    }
}
