<?php

namespace App\Actions\Users;

use App\Exceptions\CannotDeleteRecord;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderStatusHistory;
use App\Models\User;

class DeleteUser
{
    /**
     * Quem já trabalhou em alguma OS fica no histórico; não pode ser apagado.
     */
    public function handle(User $user): void
    {
        $hasHistory = ServiceOrder::withTrashed()
            ->where(fn ($query) => $query->where('user_id', $user->id)->orWhere('technician_id', $user->id))
            ->exists()
            || ServiceOrderStatusHistory::where('user_id', $user->id)->exists();

        if ($hasHistory) {
            throw new CannotDeleteRecord('Este usuário tem histórico em ordens de serviço e não pode ser excluído. Troque a senha para bloquear o acesso.');
        }

        $user->delete();
    }
}
