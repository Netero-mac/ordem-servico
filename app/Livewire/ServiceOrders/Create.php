<?php

namespace App\Livewire\ServiceOrders;

use App\Actions\ServiceOrders\OpenServiceOrder;
use App\Enums\DeviceType;
use App\Enums\UserRole;
use App\Livewire\Forms\CustomerForm;
use App\Livewire\Forms\DeviceForm;
use App\Livewire\Forms\ServiceOrderForm;
use App\Models\Customer;
use App\Models\Device;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Support\Phone;
use Closure;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Abertura de OS em uma tela só: escolher ou cadastrar o cliente e o aparelho na hora.
 *
 * @property-read Collection<int, Customer> $customerResults
 * @property-read Customer|null $selectedCustomer
 * @property-read Collection<int, Device> $customerDevices
 * @property-read Collection<int, User> $technicians
 */
#[Title('Nova ordem de serviço')]
class Create extends Component
{
    public CustomerForm $customerForm;

    public DeviceForm $deviceForm;

    public ServiceOrderForm $form;

    /** 'existing' = cliente já cadastrado | 'new' = cadastrar agora */
    public string $customerMode = 'existing';

    public string $deviceMode = 'existing';

    public string $customerSearch = '';

    #[Url(as: 'cliente')]
    public ?int $customerId = null;

    #[Url(as: 'aparelho')]
    public ?int $deviceId = null;

    public function mount(): void
    {
        $this->authorize('create', ServiceOrder::class);

        // Pré-seleção vinda da tela do cliente.
        if ($this->customerId !== null) {
            $customer = Customer::find($this->customerId);
            $customer ? $this->selectCustomer($customer->id, keepDevice: true) : $this->clearCustomer();
        }

        // Técnico abrindo a OS já fica como responsável.
        $user = Auth::user();

        if ($user?->role === UserRole::Technician) {
            $this->form->technician_id = $user->id;
        }
    }

    /**
     * @return Collection<int, Customer>
     */
    #[Computed]
    public function customerResults(): Collection
    {
        $term = trim($this->customerSearch);
        $digits = Phone::normalize($term);

        if (mb_strlen($term) < 2) {
            return new Collection;
        }

        return Customer::query()
            ->where(function (Builder $query) use ($term, $digits) {
                $query->whereLike('name', "%{$term}%");

                if (strlen($digits) >= 3) {
                    $query->orWhereLike('phone', "%{$digits}%");
                }
            })
            ->orderBy('name')
            ->limit(8)
            ->get();
    }

    #[Computed]
    public function selectedCustomer(): ?Customer
    {
        return $this->customerId ? Customer::find($this->customerId) : null;
    }

    /**
     * @return Collection<int, Device>
     */
    #[Computed]
    public function customerDevices(): Collection
    {
        return $this->selectedCustomer?->devices()->latest()->get() ?? new Collection;
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function technicians(): Collection
    {
        return User::query()->orderBy('name')->get(['id', 'name']);
    }

    public function selectCustomer(int $customerId, bool $keepDevice = false): void
    {
        $this->customerId = Customer::findOrFail($customerId)->id;
        $this->customerMode = 'existing';
        $this->customerSearch = '';
        unset($this->selectedCustomer, $this->customerDevices);

        $devices = $this->customerDevices;

        if (! $keepDevice || ! $devices->contains('id', $this->deviceId)) {
            $this->deviceId = $devices->count() === 1 ? $devices->first()?->id : null;
        }

        $this->deviceMode = $devices->isEmpty() ? 'new' : 'existing';
        $this->resetValidation(['customerId', 'deviceId']);
    }

    public function clearCustomer(): void
    {
        $this->customerId = null;
        $this->deviceId = null;
        $this->deviceMode = 'existing';
        unset($this->selectedCustomer, $this->customerDevices);
    }

    public function updatedCustomerMode(string $mode): void
    {
        if ($mode === 'new') {
            $this->clearCustomer();
            $this->deviceMode = 'new';
        }
    }

    public function save(OpenServiceOrder $openServiceOrder): void
    {
        $errors = [];

        $customer = $this->collectErrors($errors, fn () => $this->customerMode === 'new'
            ? $this->customerForm->payload()
            : $this->validatedCustomer());

        $device = $this->collectErrors($errors, fn () => $this->deviceMode === 'new'
            ? $this->deviceForm->payload()
            : $this->validatedDevice());

        $order = $this->collectErrors($errors, fn () => $this->form->payload());

        // Mostra todos os erros de uma vez, não um bloco por vez.
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        /** @var User $user */
        $user = Auth::user();
        /** @var Customer|array<string, mixed> $customer */
        /** @var Device|array<string, mixed> $device */
        /** @var array<string, mixed> $order */
        $serviceOrder = $openServiceOrder->handle($user, $customer, $device, $order);

        Flux::toast(variant: 'success', text: "OS {$serviceOrder->number} aberta.");

        $this->redirectRoute('service-orders.show', $serviceOrder, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.service-orders.create', [
            'deviceTypes' => DeviceType::cases(),
        ]);
    }

    private function validatedCustomer(): Customer
    {
        $customer = $this->selectedCustomer;

        if ($customer === null) {
            throw ValidationException::withMessages(['customerId' => 'Escolha um cliente ou cadastre um novo.']);
        }

        return $customer;
    }

    private function validatedDevice(): ?Device
    {
        if ($this->selectedCustomer === null) {
            return null; // o erro já foi dado no cliente
        }

        $device = $this->customerDevices->firstWhere('id', $this->deviceId);

        if ($device === null) {
            throw ValidationException::withMessages(['deviceId' => 'Escolha o aparelho ou cadastre um novo.']);
        }

        return $device;
    }

    /**
     * Executa uma etapa de validação e acumula os erros em vez de parar no primeiro bloco.
     *
     * @template T
     *
     * @param  array<string, mixed>  $errors
     * @param  Closure(): T  $step
     * @return T|null
     */
    private function collectErrors(array &$errors, Closure $step): mixed
    {
        try {
            return $step();
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $errors[(string) $field] = $messages;
            }

            return null;
        }
    }
}
