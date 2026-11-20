<?php

namespace App\Actions\ServiceOrders;

use App\Enums\ServiceOrderStatus;
use App\Exceptions\InvalidStatusTransition;
use App\Models\ServiceOrder;

/**
 * O cliente aprova ou recusa o orçamento pelo link público.
 * Fica registrado no histórico sem usuário (user_id nulo).
 */
class RecordCustomerDecision
{
    public function __construct(private ChangeServiceOrderStatus $changeStatus) {}

    /**
     * @throws InvalidStatusTransition quando o orçamento já foi respondido
     */
    public function handle(ServiceOrder $order, bool $approved, ?string $reason = null): ServiceOrder
    {
        if ($order->status !== ServiceOrderStatus::AwaitingApproval) {
            throw new InvalidStatusTransition('Este orçamento não está mais aguardando aprovação.');
        }

        $note = $approved ? 'Orçamento aprovado pelo cliente pelo link de acompanhamento.' : 'Orçamento recusado pelo cliente pelo link de acompanhamento.';

        if (! $approved && filled($reason)) {
            $note .= "\nMotivo: ".trim($reason);
        }

        return $this->changeStatus->handle(
            $order,
            $approved ? ServiceOrderStatus::Approved : ServiceOrderStatus::Rejected,
            null,
            $note,
        );
    }
}
