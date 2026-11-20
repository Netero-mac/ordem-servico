<?php

namespace Database\Factories;

use App\Enums\DeviceType;
use App\Models\Customer;
use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    /**
     * @var array<string, array<string, list<string>>>
     */
    private const CATALOG = [
        'phone' => [
            'Samsung' => ['Galaxy A54', 'Galaxy S23', 'Galaxy A15'],
            'Apple' => ['iPhone 13', 'iPhone 14', 'iPhone 11'],
            'Motorola' => ['Moto G84', 'Moto E22', 'Edge 40'],
            'Xiaomi' => ['Redmi Note 13', 'Poco X6'],
        ],
        'notebook' => [
            'Dell' => ['Inspiron 15', 'Vostro 3520'],
            'Lenovo' => ['IdeaPad 3', 'ThinkPad E14'],
            'Acer' => ['Aspire 5', 'Nitro 5'],
        ],
        'desktop' => [
            'Montado' => ['Gabinete ATX', 'Mini ITX'],
            'Positivo' => ['Master D380'],
        ],
        'tablet' => [
            'Samsung' => ['Galaxy Tab A8'],
            'Apple' => ['iPad 9ª geração'],
        ],
        'other' => [
            'Sony' => ['PlayStation 4'],
            'JBL' => ['Flip 6'],
        ],
    ];

    public function definition(): array
    {
        $type = fake()->randomElement(DeviceType::cases());
        $brand = fake()->randomElement(array_keys(self::CATALOG[$type->value]));
        $isMobile = in_array($type, [DeviceType::Phone, DeviceType::Tablet], true);

        return [
            'customer_id' => Customer::factory(),
            'type' => $type,
            'brand' => $brand,
            'model' => fake()->randomElement(self::CATALOG[$type->value][$brand]),
            'serial_or_imei' => fake()->optional(0.7)->numerify($isMobile ? '###############' : 'SN##########'),
            'color' => fake()->optional(0.8)->randomElement(['Preto', 'Branco', 'Prata', 'Azul', 'Verde', 'Grafite']),
        ];
    }
}
