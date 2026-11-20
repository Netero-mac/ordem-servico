<?php

use App\Enums\ServiceOrderStatus;
use App\Livewire\Customers\Show;
use App\Models\Customer;
use App\Models\Device;
use App\Models\ServiceOrder;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->technician = User::factory()->technician()->create();
    $this->admin = User::factory()->admin()->create();
    $this->customer = Customer::factory()->create([
        'phone' => '65999998888',
        'document' => '52998224725',
    ]);
});

test('mostra os dados do cliente formatados', function () {
    $this->actingAs($this->technician)
        ->get(route('customers.show', $this->customer))
        ->assertOk()
        ->assertSee($this->customer->name)
        ->assertSee('(65) 99999-8888')
        ->assertSee('529.982.247-25')
        ->assertSee('https://wa.me/5565999998888');
});

test('mostra o histórico de OS de cada aparelho', function () {
    $phone = Device::factory()->for($this->customer)->create(['brand' => 'Samsung', 'model' => 'Galaxy A54']);
    $laptop = Device::factory()->for($this->customer)->create(['brand' => 'Dell', 'model' => 'Inspiron 15']);
    $order = ServiceOrder::factory()->status(ServiceOrderStatus::InRepair)->create([
        'device_id' => $phone->id,
        'reported_issue' => 'Tela quebrada',
    ]);

    Livewire::actingAs($this->technician)
        ->test(Show::class, ['customer' => $this->customer])
        ->assertSee('Samsung Galaxy A54')
        ->assertSee('Dell Inspiron 15')
        ->assertSee($order->number)
        ->assertSee('Tela quebrada')
        ->assertSee('Em conserto')
        ->assertSee('Nenhuma ordem de serviço para este aparelho.');
});

test('cadastra aparelho para o cliente', function () {
    Livewire::actingAs($this->technician)
        ->test(Show::class, ['customer' => $this->customer])
        ->call('createDevice')
        ->set('deviceForm.type', 'notebook')
        ->set('deviceForm.brand', 'Lenovo')
        ->set('deviceForm.model', 'IdeaPad 3')
        ->set('deviceForm.color', 'Cinza')
        ->call('saveDevice')
        ->assertHasNoErrors()
        ->assertSee('Lenovo IdeaPad 3');

    expect($this->customer->devices()->sole())
        ->brand->toBe('Lenovo')
        ->type->value->toBe('notebook')
        ->serial_or_imei->toBeNull();
});

test('valida o aparelho', function () {
    Livewire::actingAs($this->technician)
        ->test(Show::class, ['customer' => $this->customer])
        ->set('deviceForm.type', 'geladeira')
        ->call('saveDevice')
        ->assertHasErrors(['deviceForm.type', 'deviceForm.brand', 'deviceForm.model']);
});

test('edita aparelho do cliente', function () {
    $device = Device::factory()->for($this->customer)->create();

    Livewire::actingAs($this->technician)
        ->test(Show::class, ['customer' => $this->customer])
        ->call('editDevice', $device->id)
        ->assertSet('deviceForm.brand', $device->brand)
        ->set('deviceForm.model', 'Modelo Novo')
        ->call('saveDevice')
        ->assertHasNoErrors();

    expect($device->fresh()->model)->toBe('Modelo Novo');
});

test('não permite editar aparelho de outro cliente', function () {
    $otherDevice = Device::factory()->create();

    Livewire::actingAs($this->technician)
        ->test(Show::class, ['customer' => $this->customer])
        ->call('editDevice', $otherDevice->id)
        ->assertNotFound();
});

describe('exclusão', function () {
    test('técnico não vê nem consegue excluir cliente', function () {
        $this->actingAs($this->technician)
            ->get(route('customers.show', $this->customer))
            ->assertDontSee('Excluir cliente?');

        Livewire::actingAs($this->technician)
            ->test(Show::class, ['customer' => $this->customer])
            ->call('deleteCustomer')
            ->assertForbidden();

        expect($this->customer->fresh()->trashed())->toBeFalse();
    });

    test('admin exclui cliente (exclusão lógica)', function () {
        Livewire::actingAs($this->admin)
            ->test(Show::class, ['customer' => $this->customer])
            ->call('deleteCustomer')
            ->assertRedirect(route('customers.index'));

        expect($this->customer->fresh()->trashed())->toBeTrue();
    });

    test('admin não exclui cliente com OS em aberto', function () {
        $device = Device::factory()->for($this->customer)->create();
        ServiceOrder::factory()->status(ServiceOrderStatus::InRepair)->create(['device_id' => $device->id]);

        Livewire::actingAs($this->admin)
            ->test(Show::class, ['customer' => $this->customer])
            ->call('deleteCustomer')
            ->assertNoRedirect();

        expect($this->customer->fresh()->trashed())->toBeFalse();
    });

    test('admin exclui cliente cujas OS já foram entregues', function () {
        $device = Device::factory()->for($this->customer)->create();
        ServiceOrder::factory()->status(ServiceOrderStatus::Delivered)->create(['device_id' => $device->id]);

        Livewire::actingAs($this->admin)
            ->test(Show::class, ['customer' => $this->customer])
            ->call('deleteCustomer');

        expect($this->customer->fresh()->trashed())->toBeTrue();
    });

    test('técnico não exclui aparelho', function () {
        $device = Device::factory()->for($this->customer)->create();

        Livewire::actingAs($this->technician)
            ->test(Show::class, ['customer' => $this->customer])
            ->call('deleteDevice', $device->id)
            ->assertForbidden();

        expect($device->fresh())->not->toBeNull();
    });

    test('admin exclui aparelho sem OS', function () {
        $device = Device::factory()->for($this->customer)->create();

        Livewire::actingAs($this->admin)
            ->test(Show::class, ['customer' => $this->customer])
            ->call('deleteDevice', $device->id);

        expect($device->fresh())->toBeNull();
    });

    test('admin não exclui aparelho com histórico de OS', function () {
        $device = Device::factory()->for($this->customer)->create();
        ServiceOrder::factory()->status(ServiceOrderStatus::Delivered)->create(['device_id' => $device->id]);

        Livewire::actingAs($this->admin)
            ->test(Show::class, ['customer' => $this->customer])
            ->call('deleteDevice', $device->id);

        expect($device->fresh())->not->toBeNull();
    });
});
