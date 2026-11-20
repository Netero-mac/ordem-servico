<?php

namespace App\Policies;

use App\Enums\ServiceOrderStatus;
use App\Models\ServiceOrder;
use App\Models\User;

class ServiceOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ServiceOrder $order): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Dados da OS (laudo, técnico, previsão...). Depois de entregue/cancelada, só o admin corrige.
     */
    public function update(User $user, ServiceOrder $order): bool
    {
        return $user->isAdmin() || $order->status->isOpen();
    }

    public function changeStatus(User $user, ServiceOrder $order): bool
    {
        return $order->status->isOpen();
    }

    /**
     * Itens do orçamento: livres até o cliente decidir. Depois da aprovação/recusa,
     * só o admin altera — o cliente não pode aprovar um valor e pagar outro.
     */
    public function manageItems(User $user, ServiceOrder $order): bool
    {
        return $user->isAdmin() || in_array($order->status, [
            ServiceOrderStatus::Received,
            ServiceOrderStatus::InAnalysis,
            ServiceOrderStatus::AwaitingApproval,
        ], true);
    }

    public function addPhotos(User $user, ServiceOrder $order): bool
    {
        return $order->status->isOpen();
    }

    public function deletePhotos(User $user, ServiceOrder $order): bool
    {
        return $user->isAdmin();
    }

    /**
     * Comprovante de entrega: só quando o aparelho está pronto ou já foi entregue.
     */
    public function printDelivery(User $user, ServiceOrder $order): bool
    {
        return in_array($order->status, [ServiceOrderStatus::Ready, ServiceOrderStatus::Delivered], true);
    }

    public function delete(User $user, ServiceOrder $order): bool
    {
        return $user->isAdmin();
    }
}
