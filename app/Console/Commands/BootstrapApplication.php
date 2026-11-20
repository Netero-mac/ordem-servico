<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Roda a cada deploy (depois das migrations). Só age quando o banco ainda não tem usuários:
 *  - SEED_DEMO_DATA=true          → carrega os dados fictícios de demonstração;
 *  - INITIAL_ADMIN_EMAIL/PASSWORD → cria o primeiro administrador (uso real da loja).
 * Com usuários já cadastrados, não faz nada — é seguro rodar sempre.
 */
#[Signature('app:bootstrap')]
#[Description('Prepara um banco vazio: dados de demonstração ou primeiro administrador')]
class BootstrapApplication extends Command
{
    public function handle(): int
    {
        if (User::query()->exists()) {
            $this->components->info('Banco já inicializado. Nada a fazer.');

            return self::SUCCESS;
        }

        if (config('app.seed_demo_data')) {
            // Tudo ou nada: se falhar no meio, o banco continua vazio e a próxima subida tenta de novo.
            DB::transaction(fn () => $this->call('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]));
            $this->components->info('Dados de demonstração carregados.');

            return self::SUCCESS;
        }

        $email = config('app.initial_admin.email');
        $password = config('app.initial_admin.password');

        if (! is_string($email) || $email === '' || ! is_string($password) || strlen($password) < 12) {
            $this->components->warn('Nenhum usuário cadastrado. Defina INITIAL_ADMIN_EMAIL e INITIAL_ADMIN_PASSWORD (mín. 12 caracteres) ou SEED_DEMO_DATA=true.');

            return self::SUCCESS;
        }

        User::create([
            'name' => config('app.initial_admin.name'),
            'email' => mb_strtolower($email),
            'password' => $password,
            'role' => UserRole::Admin,
            'email_verified_at' => now(),
        ]);

        $this->components->info("Administrador {$email} criado. Remova INITIAL_ADMIN_PASSWORD das variáveis de ambiente.");

        return self::SUCCESS;
    }
}
