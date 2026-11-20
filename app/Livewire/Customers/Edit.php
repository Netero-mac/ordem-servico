<?php

namespace App\Livewire\Customers;

use App\Livewire\Forms\CustomerForm;
use App\Models\Customer;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Cadastro (sem cliente) e edição (com cliente) usam a mesma tela.
 */
class Edit extends Component
{
    public CustomerForm $form;

    public function mount(?Customer $customer = null): void
    {
        if ($customer?->exists) {
            $this->authorize('update', $customer);
            $this->form->setCustomer($customer);
        } else {
            $this->authorize('create', Customer::class);
        }
    }

    public function save(): void
    {
        $isNew = $this->form->customer === null;
        $customer = $this->form->save();

        Flux::toast(variant: 'success', text: $isNew ? 'Cliente cadastrado.' : 'Cliente atualizado.');

        $this->redirectRoute('customers.show', $customer, navigate: true);
    }

    public function render(): View
    {
        $title = $this->form->customer ? 'Editar cliente' : 'Novo cliente';

        return view('livewire.customers.edit', ['title' => $title])->title($title);
    }
}
