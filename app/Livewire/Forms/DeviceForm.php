<?php

namespace App\Livewire\Forms;

use App\Enums\DeviceType;
use App\Models\Customer;
use App\Models\Device;
use Illuminate\Validation\Rule;
use Livewire\Form;

class DeviceForm extends Form
{
    public ?Device $device = null;

    public string $type = 'phone';

    public string $brand = '';

    public string $model = '';

    public string $serial_or_imei = '';

    public string $color = '';

    public function setDevice(Device $device): void
    {
        $this->device = $device;
        $this->type = $device->type->value;
        $this->brand = $device->brand;
        $this->model = $device->model;
        $this->serial_or_imei = (string) $device->serial_or_imei;
        $this->color = (string) $device->color;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(DeviceType::class)],
            'brand' => ['required', 'string', 'max:100'],
            'model' => ['required', 'string', 'max:100'],
            'serial_or_imei' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'type' => 'tipo',
            'brand' => 'marca',
            'model' => 'modelo',
            'serial_or_imei' => 'número de série/IMEI',
            'color' => 'cor',
        ];
    }

    /**
     * @return array{type: string, brand: string, model: string, serial_or_imei: ?string, color: ?string}
     */
    public function payload(): array
    {
        $this->validate();

        return [
            'type' => $this->type,
            'brand' => trim($this->brand),
            'model' => trim($this->model),
            'serial_or_imei' => filled($this->serial_or_imei) ? trim($this->serial_or_imei) : null,
            'color' => filled($this->color) ? trim($this->color) : null,
        ];
    }

    public function save(Customer $customer): Device
    {
        $data = $this->payload();

        if ($this->device) {
            $this->device->update($data);

            return $this->device;
        }

        return $this->device = $customer->devices()->create($data);
    }
}
