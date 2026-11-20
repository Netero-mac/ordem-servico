<?php

use App\Enums\ServiceOrderStatus as S;
use App\Livewire\ServiceOrders\Show;
use App\Models\Customer;
use App\Models\Device;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderItem;
use App\Models\User;
use App\Services\WhatsAppLinkBuilder;
use Livewire\Livewire;

beforeEach(function () {
    config(['shop.name' => 'Loja Teste']);
    $device = Device::factory()
        ->for(Customer::factory()->state(['name' => 'Rafael Nunes', 'phone' => '65999998888']))
        ->create(['brand' => 'Apple', 'model' => 'iPhone 13']);
    $this->order = ServiceOrder::factory()->create(['device_id' => $device->id]);
});

test('monta o link wa.me com o telefone do cliente e a mensagem codificada', function () {
    $link = app(WhatsAppLinkBuilder::class)->forServiceOrder($this->order);

    expect($link)->toStartWith('https://wa.me/5565999998888?text=');

    parse_str(parse_url($link, PHP_URL_QUERY), $query);

    expect($query['text'])
        ->toContain('Olá, Rafael! Aqui é da Loja Teste.')
        ->toContain($this->order->number)
        ->toContain(route('tracking.show', $this->order->public_token));
});

test('a mensagem muda conforme o status', function () {
    ServiceOrderItem::factory()->for($this->order)->create(['quantity' => 1, 'unit_price_cents' => 25000]);
    $builder = app(WhatsAppLinkBuilder::class);

    $this->order->status = S::AwaitingApproval;
    expect($builder->message($this->order))->toContain('total de R$ 250,00')->toContain('aprovar ou recusar');

    $this->order->status = S::Ready;
    expect($builder->message($this->order))->toContain('pronto para retirada');

    $this->order->status = S::Delivered;
    expect($builder->message($this->order))->toContain('90 dias de garantia');
});

test('a tela interna da OS tem o botão do WhatsApp', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(Show::class, ['serviceOrder' => $this->order])
        ->assertSee('Enviar pelo WhatsApp')
        ->assertSee('https://wa.me/5565999998888?text=', false);
});
