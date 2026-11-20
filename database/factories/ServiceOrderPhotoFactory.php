<?php

namespace Database\Factories;

use App\Models\ServiceOrder;
use App\Models\ServiceOrderPhoto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceOrderPhoto>
 */
class ServiceOrderPhotoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'service_order_id' => ServiceOrder::factory(),
            'path' => 'service-orders/'.fake()->uuid().'.jpg',
            'caption' => fake()->optional()->randomElement(['Frente', 'Traseira', 'Tela trincada', 'Lateral']),
        ];
    }
}
