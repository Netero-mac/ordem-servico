<?php

namespace App\Livewire\Forms;

use App\Models\Customer;
use App\Rules\ValidCpf;
use App\Support\Cpf;
use App\Support\Phone;
use Closure;
use Livewire\Form;

/**
 * Validação e gravação do cliente. É o equivalente Livewire de um Form Request:
 * reaproveitado na tela de cliente e, na Fase 3, na abertura da OS.
 */
class CustomerForm extends Form
{
    public ?Customer $customer = null;

    public string $name = '';

    public string $phone = '';

    public string $email = '';

    public string $document = '';

    public string $notes = '';

    public function setCustomer(Customer $customer): void
    {
        $this->customer = $customer;
        $this->name = $customer->name;
        $this->phone = Phone::format($customer->phone);
        $this->email = (string) $customer->email;
        $this->document = Cpf::format($customer->document);
        $this->notes = (string) $customer->notes;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', $this->phoneRule()],
            'email' => ['nullable', 'email', 'max:255'],
            'document' => ['nullable', 'string', new ValidCpf, $this->uniqueCpfRule()],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'name' => 'nome',
            'phone' => 'telefone',
            'email' => 'e-mail',
            'document' => 'CPF',
            'notes' => 'observações',
        ];
    }

    public function save(): Customer
    {
        $this->validate();

        $data = [
            'name' => trim($this->name),
            'phone' => Phone::normalize($this->phone),
            'email' => filled($this->email) ? mb_strtolower(trim($this->email)) : null,
            'document' => filled($this->document) ? Cpf::normalize($this->document) : null,
            'notes' => filled($this->notes) ? trim($this->notes) : null,
        ];

        if ($this->customer) {
            $this->customer->update($data);

            return $this->customer;
        }

        return $this->customer = Customer::create($data);
    }

    private function phoneRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! in_array(strlen(Phone::normalize(is_string($value) ? $value : '')), [10, 11], true)) {
                $fail('Informe o telefone com DDD, ex.: (65) 99999-8888.');
            }
        };
    }

    private function uniqueCpfRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $exists = Customer::query()
                ->where('document', Cpf::normalize(is_string($value) ? $value : ''))
                ->when($this->customer, fn ($query, Customer $customer) => $query->whereKeyNot($customer->id))
                ->exists();

            if ($exists) {
                $fail('Já existe um cliente cadastrado com este CPF.');
            }
        };
    }
}
