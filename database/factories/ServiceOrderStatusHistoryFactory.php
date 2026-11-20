<?php

namespace Database\Factories;

use App\Enums\ServiceOrderStatus;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderStatusHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceOrderStatusHistory>
 */
class ServiceOrderStatusHistoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'service_order_id' => ServiceOrder::factory(),
            'from_status' => ServiceOrderStatus::Received,
            'to_status' => ServiceOrderStatus::InAnalysis,
            'user_id' => null,
            'note' => null,
        ];
    }
}
