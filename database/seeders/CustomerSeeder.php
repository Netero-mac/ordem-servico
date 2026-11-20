<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Device;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        Customer::factory(10)
            ->has(Device::factory()->count(fake()->numberBetween(1, 2)))
            ->create();
    }
}
