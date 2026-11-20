@php use App\Support\{Cpf, Dates, Phone}; @endphp

<div class="flex flex-col gap-8">
    <div>
        <flux:breadcrumbs class="mb-2">
            <flux:breadcrumbs.item :href="route('customers.index')" wire:navigate>Clientes</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ $customer->name }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="flex flex-wrap items-start justify-between gap-4">
            <flux:heading size="xl" level="1">{{ $customer->name }}</flux:heading>

            <div class="flex gap-2">
                <flux:button variant="primary" icon="plus" :href="route('service-orders.create', ['cliente' => $customer->id])" wire:navigate>Nova OS</flux:button>
                <flux:button icon="pencil-square" :href="route('customers.edit', $customer)" wire:navigate>Editar</flux:button>

                @can('delete', $customer)
                    <flux:modal.trigger name="delete-customer">
                        <flux:button variant="danger" icon="trash">Excluir</flux:button>
                    </flux:modal.trigger>
                @endcan
            </div>
        </div>
    </div>

    <flux:card class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <flux:text>Telefone (WhatsApp)</flux:text>
            <flux:link href="https://wa.me/{{ Phone::whatsApp($customer->phone) }}" target="_blank" class="font-medium">
                {{ Phone::format($customer->phone) }}
            </flux:link>
        </div>
        <div>
            <flux:text>E-mail</flux:text>
            <flux:heading>{{ $customer->email ?? '—' }}</flux:heading>
        </div>
        <div>
            <flux:text>CPF</flux:text>
            <flux:heading>{{ $customer->document ? Cpf::format($customer->document) : '—' }}</flux:heading>
        </div>
        <div>
            <flux:text>Cliente desde</flux:text>
            <flux:heading>{{ Dates::date($customer->created_at) }}</flux:heading>
        </div>
        @if ($customer->notes)
            <div class="sm:col-span-2 lg:col-span-4">
                <flux:text>Observações</flux:text>
                <flux:heading class="whitespace-pre-line font-normal">{{ $customer->notes }}</flux:heading>
            </div>
        @endif
    </flux:card>

    <section class="flex flex-col gap-4">
        <div class="flex items-center justify-between">
            <flux:heading size="lg" level="2">Aparelhos</flux:heading>
            <flux:button size="sm" icon="plus" wire:click="createDevice">Adicionar aparelho</flux:button>
        </div>

        @forelse ($this->devices as $device)
            <flux:card wire:key="device-{{ $device->id }}" class="flex flex-col gap-4">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <flux:heading>{{ $device->description() }}</flux:heading>
                        <flux:text>
                            {{ $device->type->label() }}
                            @if ($device->color) · {{ $device->color }} @endif
                            @if ($device->serial_or_imei) · Série/IMEI: {{ $device->serial_or_imei }} @endif
                        </flux:text>
                    </div>

                    <div class="flex gap-1">
                        <flux:button size="sm" variant="ghost" icon="plus" :href="route('service-orders.create', ['cliente' => $customer->id, 'aparelho' => $device->id])" wire:navigate>Nova OS</flux:button>
                        <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="editDevice({{ $device->id }})" aria-label="Editar aparelho" />
                        @can('delete', $device)
                            <flux:button
                                size="sm" variant="ghost" icon="trash"
                                wire:click="deleteDevice({{ $device->id }})"
                                wire:confirm="Excluir o aparelho {{ $device->description() }}?"
                                aria-label="Excluir aparelho"
                            />
                        @endcan
                    </div>
                </div>

                @if ($device->serviceOrders->isEmpty())
                    <flux:text>Nenhuma ordem de serviço para este aparelho.</flux:text>
                @else
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>OS</flux:table.column>
                            <flux:table.column>Entrada</flux:table.column>
                            <flux:table.column>Defeito relatado</flux:table.column>
                            <flux:table.column>Status</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach ($device->serviceOrders as $order)
                                <flux:table.row :key="$order->id">
                                    <flux:table.cell variant="strong">
                                        <flux:link :href="route('service-orders.show', $order)" wire:navigate>{{ $order->number }}</flux:link>
                                    </flux:table.cell>
                                    <flux:table.cell>{{ Dates::date($order->created_at) }}</flux:table.cell>
                                    <flux:table.cell class="max-w-xs truncate">{{ $order->reported_issue }}</flux:table.cell>
                                    <flux:table.cell><x-status-badge :status="$order->status" /></flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                @endif
            </flux:card>
        @empty
            <flux:text>Nenhum aparelho cadastrado.</flux:text>
        @endforelse
    </section>

    <flux:modal name="device-form" class="w-full max-w-lg">
        <form wire:submit="saveDevice" class="flex flex-col gap-6">
            <flux:heading size="lg">{{ $deviceForm->device ? 'Editar aparelho' : 'Novo aparelho' }}</flux:heading>

            <flux:select wire:model="deviceForm.type" label="Tipo">
                @foreach ($deviceTypes as $type)
                    <flux:select.option :value="$type->value">{{ $type->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="grid gap-6 sm:grid-cols-2">
                <flux:input wire:model="deviceForm.brand" label="Marca" placeholder="Samsung" required />
                <flux:input wire:model="deviceForm.model" label="Modelo" placeholder="Galaxy A54" required />
                <flux:input wire:model="deviceForm.serial_or_imei" label="Nº de série / IMEI" badge="Opcional" />
                <flux:input wire:model="deviceForm.color" label="Cor" badge="Opcional" />
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Salvar</flux:button>
            </div>
        </form>
    </flux:modal>

    @can('delete', $customer)
        <flux:modal name="delete-customer" class="max-w-md">
            <div class="flex flex-col gap-6">
                <div>
                    <flux:heading size="lg">Excluir cliente?</flux:heading>
                    <flux:text class="mt-2">
                        {{ $customer->name }} deixará de aparecer na lista de clientes. As ordens de serviço antigas continuam guardadas.
                    </flux:text>
                </div>
                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">Cancelar</flux:button>
                    </flux:modal.close>
                    <flux:button variant="danger" wire:click="deleteCustomer">Excluir</flux:button>
                </div>
            </div>
        </flux:modal>
    @endcan
</div>
