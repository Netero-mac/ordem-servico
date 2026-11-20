<?php

namespace App\Livewire\Customers;

use App\Actions\Customers\DeleteCustomer;
use App\Actions\Customers\DeleteDevice;
use App\Enums\DeviceType;
use App\Exceptions\CannotDeleteRecord;
use App\Livewire\Forms\DeviceForm;
use App\Models\Customer;
use App\Models\Device;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Show extends Component
{
    public Customer $customer;

    public DeviceForm $deviceForm;

    public function mount(Customer $customer): void
    {
        $this->authorize('view', $customer);
        $this->customer = $customer;
    }

    /**
     * Aparelhos com o histórico de OS de cada um (mais recentes primeiro).
     *
     * @return Collection<int, Device>
     */
    #[Computed]
    public function devices(): Collection
    {
        return $this->customer->devices()
            ->with(['serviceOrders' => fn ($query) => $query->latest()])
            ->latest()
            ->get();
    }

    public function createDevice(): void
    {
        $this->authorize('create', Device::class);

        $this->deviceForm->reset();
        $this->deviceForm->resetValidation();
        Flux::modal('device-form')->show();
    }

    public function editDevice(int $deviceId): void
    {
        $device = $this->findDevice($deviceId);
        $this->authorize('update', $device);

        $this->deviceForm->resetValidation();
        $this->deviceForm->setDevice($device);
        Flux::modal('device-form')->show();
    }

    public function saveDevice(): void
    {
        $device = $this->deviceForm->device;
        $this->authorize($device ? 'update' : 'create', $device ?? Device::class);

        $this->deviceForm->save($this->customer);

        Flux::modal('device-form')->close();
        Flux::toast(variant: 'success', text: $device ? 'Aparelho atualizado.' : 'Aparelho cadastrado.');
        $this->deviceForm->reset();
        unset($this->devices);
    }

    public function deleteDevice(int $deviceId, DeleteDevice $deleteDevice): void
    {
        $device = $this->findDevice($deviceId);
        $this->authorize('delete', $device);

        try {
            $deleteDevice->handle($device);
            Flux::toast(variant: 'success', text: 'Aparelho excluído.');
        } catch (CannotDeleteRecord $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }

        unset($this->devices);
    }

    public function deleteCustomer(DeleteCustomer $deleteCustomer): void
    {
        $this->authorize('delete', $this->customer);

        try {
            $deleteCustomer->handle($this->customer);
        } catch (CannotDeleteRecord $e) {
            Flux::modal('delete-customer')->close();
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: 'Cliente excluído.');
        $this->redirectRoute('customers.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.customers.show', [
            'deviceTypes' => DeviceType::cases(),
        ])->title($this->customer->name);
    }

    /**
     * Garante que o aparelho é deste cliente (não dá para editar aparelho alheio pela URL).
     */
    private function findDevice(int $deviceId): Device
    {
        return $this->customer->devices()->findOrFail($deviceId);
    }
}
