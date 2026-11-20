<?php

namespace App\Livewire\Forms;

use App\Models\ServiceOrder;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * Dados da OS em si. Usado na abertura e na edição dos dados da OS.
 */
class ServiceOrderForm extends Form
{
    public string $reported_issue = '';

    public string $diagnosis = '';

    public string $accessories = '';

    public string $device_password = '';

    public ?int $technician_id = null;

    /** Data (Y-m-d) no fuso da loja; a hora é fixada no fim do expediente. */
    public string $estimated_delivery_date = '';

    public int $warranty_days = 90;

    /** Horário em que a loja considera a OS "prometida" no dia da previsão. */
    public const DELIVERY_HOUR = 18;

    public function setOrder(ServiceOrder $order): void
    {
        $this->reported_issue = $order->reported_issue;
        $this->diagnosis = (string) $order->diagnosis;
        $this->accessories = (string) $order->accessories;
        $this->device_password = (string) $order->device_password;
        $this->technician_id = $order->technician_id;
        $this->estimated_delivery_date = $order->estimated_delivery_at
            ?->setTimezone(config('app.display_timezone'))
            ->format('Y-m-d') ?? '';
        $this->warranty_days = $order->warranty_days;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'reported_issue' => ['required', 'string', 'max:2000'],
            'diagnosis' => ['nullable', 'string', 'max:5000'],
            'accessories' => ['nullable', 'string', 'max:255'],
            'device_password' => ['nullable', 'string', 'max:100'],
            'technician_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'estimated_delivery_date' => ['nullable', 'date_format:Y-m-d'],
            'warranty_days' => ['required', 'integer', 'min:0', 'max:730'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'reported_issue' => 'defeito relatado',
            'diagnosis' => 'laudo técnico',
            'accessories' => 'acessórios',
            'device_password' => 'senha do aparelho',
            'technician_id' => 'técnico',
            'estimated_delivery_date' => 'previsão de entrega',
            'warranty_days' => 'garantia',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $this->validate();

        return [
            'reported_issue' => trim($this->reported_issue),
            'diagnosis' => filled($this->diagnosis) ? trim($this->diagnosis) : null,
            'accessories' => filled($this->accessories) ? trim($this->accessories) : null,
            'device_password' => filled($this->device_password) ? $this->device_password : null,
            'technician_id' => $this->technician_id ?: null,
            'estimated_delivery_at' => filled($this->estimated_delivery_date)
                ? Date::createFromFormat('Y-m-d', $this->estimated_delivery_date, config('app.display_timezone'))
                    ?->setTime(self::DELIVERY_HOUR, 0)
                    ->utc()
                : null,
            'warranty_days' => $this->warranty_days,
        ];
    }
}
