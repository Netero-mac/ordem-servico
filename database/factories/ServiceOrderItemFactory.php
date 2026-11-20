<?php

namespace Database\Factories;

use App\Enums\ItemType;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceOrderItem>
 */
class ServiceOrderItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'service_order_id' => ServiceOrder::factory(),
            'type' => ItemType::Part,
            'description' => fake()->randomElement(['Tela (display) compatível', 'Bateria', 'Conector de carga', 'SSD 480 GB', 'Memória RAM 8 GB', 'Alto-falante']),
            'quantity' => 1,
            'unit_price_cents' => fake()->numberBetween(50, 600) * 100,
        ];
    }

    public function part(): static
    {
        return $this->state(fn (array $attributes) => ['type' => ItemType::Part]);
    }

    public function labor(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ItemType::Labor,
            'description' => fake()->randomElement(['Mão de obra', 'Troca de tela', 'Limpeza interna', 'Formatação e backup']),
            'unit_price_cents' => fake()->numberBetween(5, 25) * 1000,
        ]);
    }
}
