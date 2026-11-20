<?php

namespace Database\Factories;

use App\Enums\ServiceOrderStatus;
use App\Models\Device;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceOrder>
 */
class ServiceOrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'device_id' => Device::factory(),
            // O cliente da OS é sempre o dono do aparelho.
            'customer_id' => fn (array $attributes) => Device::query()->whereKey($attributes['device_id'])->value('customer_id'),
            'user_id' => User::factory(),
            'technician_id' => null,
            'reported_issue' => fake()->randomElement([
                'Tela quebrada após queda.',
                'Não liga.',
                'Bateria descarrega muito rápido.',
                'Não carrega, conector com mau contato.',
                'Esquentando e desligando sozinho.',
                'Muito lento, travando ao abrir programas.',
                'Caiu na água.',
                'Sem áudio no alto-falante.',
                'Teclado com teclas falhando.',
            ]),
            'accessories' => fake()->optional(0.6)->randomElement(['Carregador', 'Capa', 'Chip', 'Carregador e capa', 'Cartão de memória']),
            'device_password' => fake()->optional(0.5)->numerify('####'),
            'status' => ServiceOrderStatus::Received,
            'estimated_delivery_at' => fake()->optional(0.8)->dateTimeBetween('-5 days', '+10 days'),
            'warranty_days' => 90,
        ];
    }

    public function status(ServiceOrderStatus $status): static
    {
        return $this->state(fn (array $attributes) => ['status' => $status]);
    }

    public function forTechnician(User $technician): static
    {
        return $this->state(fn (array $attributes) => ['technician_id' => $technician->id]);
    }
}
