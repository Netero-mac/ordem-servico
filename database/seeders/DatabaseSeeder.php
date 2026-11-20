<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Dados FICTÍCIOS para desenvolvimento e demonstração.
 * Não rode em um banco com dados reais da loja.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->admin()->create([
            'name' => 'Admin Demo',
            'email' => 'admin@example.com',
        ]);

        User::factory()->technician()->create([
            'name' => 'Técnico Demo',
            'email' => 'tecnico@example.com',
        ]);

        $this->call([
            CustomerSeeder::class,
            ServiceOrderSeeder::class,
        ]);
    }
}
