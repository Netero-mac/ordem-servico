<?php

namespace App\Livewire\ServiceOrders;

use App\Actions\ServiceOrders\ChangeServiceOrderStatus;
use App\Actions\ServiceOrders\DeleteServiceOrderPhoto;
use App\Actions\ServiceOrders\StoreServiceOrderPhotos;
use App\Enums\ItemType;
use App\Enums\ServiceOrderStatus;
use App\Exceptions\InvalidStatusTransition;
use App\Livewire\Forms\ServiceOrderForm;
use App\Livewire\Forms\ServiceOrderItemForm;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderItem;
use App\Models\User;
use App\Services\WhatsAppLinkBuilder;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * @property-read Collection<int, ServiceOrderItem> $items
 * @property-read array{parts: int, labor: int, total: int} $totals
 * @property-read Collection<int, User> $technicians
 */
class Show extends Component
{
    use WithFileUploads;

    public ServiceOrder $order;

    public ServiceOrderForm $form;

    public ServiceOrderItemForm $itemForm;

    public string $newStatus = '';

    public string $statusNote = '';

    public bool $showPassword = false;

    /** @var array<int, UploadedFile> */
    public array $uploads = [];

    public function mount(ServiceOrder $serviceOrder): void
    {
        $this->authorize('view', $serviceOrder);
        $this->order = $serviceOrder;
    }

    /**
     * @return Collection<int, ServiceOrderItem>
     */
    #[Computed]
    public function items(): Collection
    {
        return $this->order->items()->oldest('id')->get();
    }

    /**
     * @return array{parts: int, labor: int, total: int}
     */
    #[Computed]
    public function totals(): array
    {
        $parts = $this->items->where('type', ItemType::Part)->sum(fn (ServiceOrderItem $item) => $item->totalCents());
        $labor = $this->items->where('type', ItemType::Labor)->sum(fn (ServiceOrderItem $item) => $item->totalCents());

        return ['parts' => (int) $parts, 'labor' => (int) $labor, 'total' => (int) ($parts + $labor)];
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function technicians(): Collection
    {
        return User::query()->orderBy('name')->get(['id', 'name']);
    }

    // ---------------------------------------------------------------- status

    public function openStatusModal(): void
    {
        $this->authorize('changeStatus', $this->order);

        $this->newStatus = $this->order->status->allowedTransitions()[0]->value ?? '';
        $this->statusNote = '';
        $this->resetValidation(['newStatus', 'statusNote']);
        Flux::modal('change-status')->show();
    }

    public function changeStatus(ChangeServiceOrderStatus $changeStatus): void
    {
        $this->authorize('changeStatus', $this->order);

        $this->validate([
            'newStatus' => ['required', Rule::in(array_map(fn ($s) => $s->value, $this->order->status->allowedTransitions()))],
            'statusNote' => ['nullable', 'string', 'max:1000'],
        ], attributes: ['newStatus' => 'novo status', 'statusNote' => 'observação']);

        try {
            $this->order = $changeStatus->handle(
                $this->order,
                ServiceOrderStatus::from($this->newStatus),
                $this->user(),
                $this->statusNote,
            );
        } catch (InvalidStatusTransition $e) {
            $this->addError('newStatus', $e->getMessage());

            return;
        }

        Flux::modal('change-status')->close();
        Flux::toast(variant: 'success', text: "Status alterado para \"{$this->order->status->label()}\".");
    }

    // ---------------------------------------------------------------- dados

    public function openDetailsModal(): void
    {
        $this->authorize('update', $this->order);

        $this->form->resetValidation();
        $this->form->setOrder($this->order);
        Flux::modal('edit-details')->show();
    }

    public function saveDetails(): void
    {
        $this->authorize('update', $this->order);

        $this->order->update($this->form->payload());

        Flux::modal('edit-details')->close();
        Flux::toast(variant: 'success', text: 'Dados da OS atualizados.');
    }

    public function togglePassword(): void
    {
        $this->showPassword = ! $this->showPassword;
    }

    // ---------------------------------------------------------------- itens

    public function openItemModal(?int $itemId = null): void
    {
        $this->authorize('manageItems', $this->order);

        $this->itemForm->reset();
        $this->itemForm->resetValidation();

        if ($itemId !== null) {
            $this->itemForm->setItem($this->findItem($itemId));
        }

        Flux::modal('item-form')->show();
    }

    public function saveItem(): void
    {
        $this->authorize('manageItems', $this->order);

        $data = $this->itemForm->payload();
        $item = $this->itemForm->item;

        if ($item !== null) {
            $this->findItem($item->id)->update($data);
        } else {
            $this->order->items()->create($data);
        }

        Flux::modal('item-form')->close();
        Flux::toast(variant: 'success', text: $item ? 'Item atualizado.' : 'Item adicionado.');
        $this->itemForm->reset();
        unset($this->items, $this->totals);
    }

    public function deleteItem(int $itemId): void
    {
        $this->authorize('manageItems', $this->order);

        $this->findItem($itemId)->delete();

        Flux::toast(variant: 'success', text: 'Item removido.');
        unset($this->items, $this->totals);
    }

    // ---------------------------------------------------------------- fotos

    public function savePhotos(StoreServiceOrderPhotos $storePhotos): void
    {
        $this->authorize('addPhotos', $this->order);

        $this->validate([
            'uploads' => ['required', 'array', 'max:'.StoreServiceOrderPhotos::MAX_PHOTOS],
            'uploads.*' => StoreServiceOrderPhotos::fileRules(),
        ], [
            'uploads.*.image' => 'Envie apenas imagens (JPG, PNG ou WEBP).',
            'uploads.*.mimes' => 'Envie apenas imagens (JPG, PNG ou WEBP).',
            'uploads.*.max' => 'Cada foto pode ter no máximo 5 MB.',
        ], ['uploads' => 'fotos']);

        $storePhotos->handle($this->order, $this->uploads, 'uploads');

        $this->uploads = [];
        Flux::toast(variant: 'success', text: 'Fotos enviadas.');
    }

    public function deletePhoto(int $photoId, DeleteServiceOrderPhoto $deletePhoto): void
    {
        $this->authorize('deletePhotos', $this->order);

        $deletePhoto->handle($this->order->photos()->findOrFail($photoId));

        Flux::toast(variant: 'success', text: 'Foto excluída.');
    }

    // ---------------------------------------------------------------- exclusão

    public function deleteOrder(): void
    {
        $this->authorize('delete', $this->order);

        $this->order->delete();

        Flux::toast(variant: 'success', text: "OS {$this->order->number} excluída.");
        $this->redirectRoute('service-orders.index', navigate: true);
    }

    public function render(WhatsAppLinkBuilder $whatsApp): View
    {
        $this->order->load([
            'customer', 'device', 'openedBy', 'technician', 'photos',
            'statusHistory.user',
        ]);

        return view('livewire.service-orders.show', [
            'itemTypes' => ItemType::cases(),
            'whatsAppLink' => $whatsApp->forServiceOrder($this->order),
            'trackingLink' => route('tracking.show', $this->order->public_token),
        ])->title("OS {$this->order->number}");
    }

    private function findItem(int $itemId): ServiceOrderItem
    {
        return $this->order->items()->findOrFail($itemId);
    }

    private function user(): User
    {
        /** @var User */
        return Auth::user();
    }
}
