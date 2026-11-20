<?php

namespace App\Actions\ServiceOrders;

use App\Models\Customer;
use App\Models\Device;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Abre a OS no balcão, criando na mesma transação o cliente e/ou o aparelho
 * quando eles ainda não existem. Se algo falhar, nada fica pela metade.
 */
class OpenServiceOrder
{
    /**
     * @param  Customer|array<string, mixed>  $customer  cliente existente ou dados de um novo
     * @param  Device|array<string, mixed>  $device  aparelho existente ou dados de um novo
     * @param  array<string, mixed>  $order  dados da OS (defeito, acessórios, senha...)
     */
    public function handle(User $openedBy, Customer|array $customer, Device|array $device, array $order): ServiceOrder
    {
        return DB::transaction(function () use ($openedBy, $customer, $device, $order): ServiceOrder {
            if (is_array($customer)) {
                $customer = Customer::create($customer);
            }

            if (is_array($device)) {
                $device = $customer->devices()->create($device);
            }

            if ($device->customer_id !== $customer->id) {
                throw new InvalidArgumentException('O aparelho informado não pertence a este cliente.');
            }

            return ServiceOrder::create([
                ...$order,
                'customer_id' => $customer->id,
                'device_id' => $device->id,
                'user_id' => $openedBy->id,
            ]);
        });
    }
}
