<?php

use App\Enums\ServiceOrderStatus as S;
use App\Livewire\ServiceOrders\Show;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderItem;
use App\Models\ServiceOrderPhoto;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->technician = User::factory()->technician()->create();
    $this->admin = User::factory()->admin()->create();
    $this->order = ServiceOrder::factory()->create([
        'reported_issue' => 'Tela quebrada',
        'device_password' => '4321',
    ]);
});

test('mostra os dados da OS sem expor a senha de cara', function () {
    $this->actingAs($this->technician)
        ->get(route('service-orders.show', $this->order))
        ->assertOk()
        ->assertSee($this->order->number)
        ->assertSee('Tela quebrada')
        ->assertSee($this->order->customer->name)
        ->assertSee('Recebido')
        ->assertDontSee('4321');
});

test('mostra a senha do aparelho quando solicitado', function () {
    Livewire::actingAs($this->technician)
        ->test(Show::class, ['serviceOrder' => $this->order])
        ->assertDontSee('4321')
        ->call('togglePassword')
        ->assertSee('4321');
});

test('OS excluída não abre', function () {
    $this->order->delete();

    $this->actingAs($this->admin)->get(route('service-orders.show', $this->order))->assertNotFound();
});

describe('status', function () {
    test('muda status com observação e mostra na linha do tempo', function () {
        Livewire::actingAs($this->technician)
            ->test(Show::class, ['serviceOrder' => $this->order])
            ->call('openStatusModal')
            ->assertSet('newStatus', 'in_analysis')
            ->set('statusNote', 'Na bancada')
            ->call('changeStatus')
            ->assertHasNoErrors()
            ->assertSee('Em análise')
            ->assertSee('Na bancada')
            ->assertSee($this->technician->name);

        expect($this->order->fresh()->status)->toBe(S::InAnalysis)
            ->and($this->order->statusHistory()->count())->toBe(2);
    });

    test('recusa status fora das transições permitidas', function () {
        Livewire::actingAs($this->technician)
            ->test(Show::class, ['serviceOrder' => $this->order])
            ->set('newStatus', 'delivered')
            ->call('changeStatus')
            ->assertHasErrors('newStatus');

        expect($this->order->fresh()->status)->toBe(S::Received);
    });

    test('mostra o erro de orçamento vazio', function () {
        $order = ServiceOrder::factory()->status(S::InAnalysis)->create();

        Livewire::actingAs($this->technician)
            ->test(Show::class, ['serviceOrder' => $order])
            ->set('newStatus', 'awaiting_approval')
            ->call('changeStatus')
            ->assertHasErrors('newStatus')
            ->assertSee('Adicione ao menos um item');
    });

    test('OS entregue não muda mais de status', function () {
        $order = ServiceOrder::factory()->status(S::Delivered)->create();

        Livewire::actingAs($this->admin)
            ->test(Show::class, ['serviceOrder' => $order])
            ->call('openStatusModal')
            ->assertForbidden();
    });
});

describe('dados', function () {
    test('edita laudo, técnico e previsão', function () {
        Livewire::actingAs($this->technician)
            ->test(Show::class, ['serviceOrder' => $this->order])
            ->call('openDetailsModal')
            ->assertSet('form.device_password', '4321')
            ->set('form.diagnosis', 'Display danificado')
            ->set('form.technician_id', $this->technician->id)
            ->set('form.estimated_delivery_date', '2026-12-10')
            ->call('saveDetails')
            ->assertHasNoErrors();

        expect($this->order->fresh())
            ->diagnosis->toBe('Display danificado')
            ->technician_id->toBe($this->technician->id)
            ->device_password->toBe('4321');
    });

    test('técnico não edita OS já entregue; admin edita', function () {
        $order = ServiceOrder::factory()->status(S::Delivered)->create();

        Livewire::actingAs($this->technician)
            ->test(Show::class, ['serviceOrder' => $order])
            ->call('openDetailsModal')
            ->assertForbidden();

        Livewire::actingAs($this->admin)
            ->test(Show::class, ['serviceOrder' => $order])
            ->call('openDetailsModal')
            ->assertOk();
    });
});

describe('orçamento', function () {
    test('adiciona peça e mão de obra e calcula o total', function () {
        Livewire::actingAs($this->technician)
            ->test(Show::class, ['serviceOrder' => $this->order])
            ->call('openItemModal')
            ->set('itemForm.type', 'part')
            ->set('itemForm.description', 'Tela compatível')
            ->set('itemForm.quantity', 2)
            ->set('itemForm.unit_price', '1.234,56')
            ->call('saveItem')
            ->assertHasNoErrors()
            ->call('openItemModal')
            ->set('itemForm.type', 'labor')
            ->set('itemForm.description', 'Troca de tela')
            ->set('itemForm.unit_price', '150,00')
            ->call('saveItem')
            ->assertSee('Tela compatível')
            ->assertSee('R$ 2.469,12')
            ->assertSee('R$ 2.619,12');

        expect($this->order->items()->sum('unit_price_cents'))->toBe(138456)
            ->and($this->order->fresh()->totalCents())->toBe(261912);
    });

    test('valida o item', function () {
        Livewire::actingAs($this->technician)
            ->test(Show::class, ['serviceOrder' => $this->order])
            ->set('itemForm.quantity', 0)
            ->set('itemForm.unit_price', 'abc')
            ->call('saveItem')
            ->assertHasErrors(['itemForm.description', 'itemForm.quantity', 'itemForm.unit_price']);
    });

    test('edita e remove item', function () {
        $item = ServiceOrderItem::factory()->for($this->order)->create(['unit_price_cents' => 10000]);

        Livewire::actingAs($this->technician)
            ->test(Show::class, ['serviceOrder' => $this->order])
            ->call('openItemModal', $item->id)
            ->assertSet('itemForm.unit_price', '100,00')
            ->set('itemForm.unit_price', '120,00')
            ->call('saveItem');

        expect($item->fresh()->unit_price_cents)->toBe(12000);

        Livewire::actingAs($this->technician)
            ->test(Show::class, ['serviceOrder' => $this->order])
            ->call('deleteItem', $item->id);

        expect($item->fresh())->toBeNull();
    });

    test('técnico não altera orçamento depois da decisão do cliente', function (S $status) {
        $order = ServiceOrder::factory()->status($status)->create();
        $item = ServiceOrderItem::factory()->for($order)->create();

        Livewire::actingAs($this->technician)
            ->test(Show::class, ['serviceOrder' => $order])
            ->assertSee('O orçamento está fechado')
            ->call('deleteItem', $item->id)
            ->assertForbidden();

        Livewire::actingAs($this->admin)
            ->test(Show::class, ['serviceOrder' => $order])
            ->call('deleteItem', $item->id)
            ->assertOk();

        expect($item->fresh())->toBeNull();
    })->with([S::Approved, S::Rejected, S::InRepair, S::Delivered]);

    test('não mexe em item de outra OS', function () {
        $otherItem = ServiceOrderItem::factory()->create();

        Livewire::actingAs($this->admin)
            ->test(Show::class, ['serviceOrder' => $this->order])
            ->call('deleteItem', $otherItem->id)
            ->assertNotFound();

        expect($otherItem->fresh())->not->toBeNull();
    });
});

describe('fotos', function () {
    beforeEach(fn () => Storage::fake('local'));

    test('envia fotos para o disco privado', function () {
        Livewire::actingAs($this->technician)
            ->test(Show::class, ['serviceOrder' => $this->order])
            ->set('uploads', [
                UploadedFile::fake()->image('frente.jpg'),
                UploadedFile::fake()->image('verso.png'),
            ])
            ->call('savePhotos')
            ->assertHasNoErrors();

        expect($this->order->photos()->count())->toBe(2);
        $this->order->photos->each(fn ($photo) => Storage::disk('local')->assertExists($photo->path));
    });

    test('aceita só imagens de até 5 MB', function () {
        Livewire::actingAs($this->technician)
            ->test(Show::class, ['serviceOrder' => $this->order])
            ->set('uploads', [UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf')])
            ->call('savePhotos')
            ->assertHasErrors('uploads.0');

        Livewire::actingAs($this->technician)
            ->test(Show::class, ['serviceOrder' => $this->order])
            ->set('uploads', [UploadedFile::fake()->image('grande.jpg')->size(5 * 1024 + 1)])
            ->call('savePhotos')
            ->assertHasErrors('uploads.0');

        expect($this->order->photos()->count())->toBe(0);
    });

    test('limita a 5 fotos por OS, contando as que já existem', function () {
        ServiceOrderPhoto::factory(4)->for($this->order)->create();

        Livewire::actingAs($this->technician)
            ->test(Show::class, ['serviceOrder' => $this->order])
            ->set('uploads', [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')])
            ->call('savePhotos')
            ->assertHasErrors('uploads');

        expect($this->order->photos()->count())->toBe(4);
    });

    test('a foto só é entregue para usuário logado', function () {
        $path = UploadedFile::fake()->image('frente.jpg')->store("service-orders/{$this->order->id}", 'local');
        $photo = $this->order->photos()->create(['path' => $path]);

        $this->get(route('service-orders.photos.show', [$this->order, $photo]))->assertRedirect(route('login'));

        $this->actingAs($this->technician)
            ->get(route('service-orders.photos.show', [$this->order, $photo]))
            ->assertOk();

        $otherOrder = ServiceOrder::factory()->create();
        $this->get(route('service-orders.photos.show', [$otherOrder, $photo]))->assertNotFound();
    });

    test('só admin exclui foto, e o arquivo é apagado', function () {
        $path = UploadedFile::fake()->image('frente.jpg')->store("service-orders/{$this->order->id}", 'local');
        $photo = $this->order->photos()->create(['path' => $path]);

        Livewire::actingAs($this->technician)
            ->test(Show::class, ['serviceOrder' => $this->order])
            ->call('deletePhoto', $photo->id)
            ->assertForbidden();

        Livewire::actingAs($this->admin)
            ->test(Show::class, ['serviceOrder' => $this->order])
            ->call('deletePhoto', $photo->id);

        expect($photo->fresh())->toBeNull();
        Storage::disk('local')->assertMissing($path);
    });
});

describe('exclusão', function () {
    test('técnico não exclui OS', function () {
        Livewire::actingAs($this->technician)
            ->test(Show::class, ['serviceOrder' => $this->order])
            ->call('deleteOrder')
            ->assertForbidden();

        expect($this->order->fresh()->trashed())->toBeFalse();
    });

    test('admin exclui OS (exclusão lógica)', function () {
        Livewire::actingAs($this->admin)
            ->test(Show::class, ['serviceOrder' => $this->order])
            ->call('deleteOrder')
            ->assertRedirect(route('service-orders.index'));

        expect($this->order->fresh()->trashed())->toBeTrue();
    });
});
