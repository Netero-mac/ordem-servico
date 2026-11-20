<?php

namespace App\Actions\Customers;

use App\Enums\ServiceOrderStatus;
use App\Exceptions\CannotDeleteRecord;
use App\Models\Customer;

/**
 * Exclusão lógica: o cliente some das listas, mas continua nas OS antigas.
 */
class DeleteCustomer
{
    public function handle(Customer $customer): void
    {
        $hasOpenOrders = $customer->serviceOrders()
            ->whereIn('status', ServiceOrderStatus::openStatuses())
            ->exists();

        if ($hasOpenOrders) {
            throw new CannotDeleteRecord('Este cliente tem ordens de serviço em aberto e não pode ser excluído.');
        }

        $customer->delete();
    }
}
