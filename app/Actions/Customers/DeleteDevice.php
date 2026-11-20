<?php

namespace App\Actions\Customers;

use App\Exceptions\CannotDeleteRecord;
use App\Models\Device;

class DeleteDevice
{
    public function handle(Device $device): void
    {
        if ($device->serviceOrders()->withTrashed()->exists()) {
            throw new CannotDeleteRecord('Este aparelho tem histórico de ordens de serviço e não pode ser excluído.');
        }

        $device->delete();
    }
}
