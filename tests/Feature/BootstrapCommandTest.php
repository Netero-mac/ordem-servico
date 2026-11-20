<?php

use App\Enums\UserRole;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('com SEED_DEMO_DATA carrega a demonstração num banco vazio', function () {
    config(['app.seed_demo_data' => true]);

    $this->artisan('app:bootstrap')->assertSuccessful();

    expect(User::count())->toBe(2)->and(ServiceOrder::count())->toBe(20);
});

test('cria o primeiro administrador a partir das variáveis de ambiente', function () {
    config(['app.initial_admin' => ['name' => 'Dono da Loja', 'email' => 'Dono@Loja.com', 'password' => 'uma-senha-bem-longa']]);

    $this->artisan('app:bootstrap')->assertSuccessful();

    $admin = User::sole();
    expect($admin->email)->toBe('dono@loja.com')
        ->and($admin->role)->toBe(UserRole::Admin)
        ->and(Hash::check('uma-senha-bem-longa', $admin->password))->toBeTrue();
});

test('recusa senha inicial curta', function () {
    config(['app.initial_admin' => ['name' => 'X', 'email' => 'a@b.com', 'password' => 'curta']]);

    $this->artisan('app:bootstrap')->assertSuccessful();

    expect(User::count())->toBe(0);
});

test('não faz nada se já existem usuários (seguro rodar a cada deploy)', function () {
    User::factory()->create();
    config(['app.seed_demo_data' => true, 'app.initial_admin.email' => 'x@y.com', 'app.initial_admin.password' => 'uma-senha-bem-longa']);

    $this->artisan('app:bootstrap')->expectsOutputToContain('Nada a fazer')->assertSuccessful();

    expect(User::count())->toBe(1)->and(ServiceOrder::count())->toBe(0);
});
