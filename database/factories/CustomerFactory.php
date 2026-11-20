<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            // Sem tratamento (Sr., Srta.) nem sufixo (Filho, Jr.): a mensagem usa o primeiro nome.
            'name' => fake()->firstName().' '.fake()->lastName(),
            // Celular fictício de Cuiabá/MT: DDD 65 + 9 + 8 dígitos.
            'phone' => '659'.fake()->numerify('########'),
            'email' => fake()->optional(0.6)->safeEmail(),
            'document' => fake()->boolean() ? self::fakeCpf() : null,
            'notes' => fake()->optional(0.2)->sentence(),
        ];
    }

    /**
     * CPF fictício com dígitos verificadores válidos (só números).
     */
    public static function fakeCpf(): string
    {
        $digits = array_map(fn () => fake()->numberBetween(0, 9), range(1, 9));

        foreach ([10, 11] as $weight) {
            $sum = 0;
            foreach ($digits as $i => $digit) {
                $sum += $digit * ($weight - $i);
            }
            $digits[] = ($sum * 10) % 11 % 10;
        }

        return implode('', $digits);
    }
}
