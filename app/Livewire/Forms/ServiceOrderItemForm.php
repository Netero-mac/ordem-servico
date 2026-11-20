<?php

namespace App\Livewire\Forms;

use App\Enums\ItemType;
use App\Models\ServiceOrderItem;
use App\Support\Money;
use Closure;
use Illuminate\Validation\Rule;
use Livewire\Form;

class ServiceOrderItemForm extends Form
{
    public ?ServiceOrderItem $item = null;

    public string $type = 'part';

    public string $description = '';

    public int $quantity = 1;

    /** Valor digitado no formato brasileiro, ex.: "1.234,56". */
    public string $unit_price = '';

    public function setItem(ServiceOrderItem $item): void
    {
        $this->item = $item;
        $this->type = $item->type->value;
        $this->description = $item->description;
        $this->quantity = $item->quantity;
        $this->unit_price = Money::toInput($item->unit_price_cents);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(ItemType::class)],
            'description' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'unit_price' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail) {
                $cents = Money::parse(is_string($value) ? $value : null);

                if ($cents === null || $cents > 100_000_000) {
                    $fail('Informe um valor válido, ex.: 150,00.');
                }
            }],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'type' => 'tipo',
            'description' => 'descrição',
            'quantity' => 'quantidade',
            'unit_price' => 'valor unitário',
        ];
    }

    /**
     * @return array{type: string, description: string, quantity: int, unit_price_cents: int}
     */
    public function payload(): array
    {
        $this->validate();

        return [
            'type' => $this->type,
            'description' => trim($this->description),
            'quantity' => $this->quantity,
            'unit_price_cents' => (int) Money::parse($this->unit_price),
        ];
    }
}
