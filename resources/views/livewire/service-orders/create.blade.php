@php use App\Support\Phone; @endphp

<div class="flex max-w-3xl flex-col gap-8">
    <div>
        <flux:breadcrumbs class="mb-2">
            <flux:breadcrumbs.item :href="route('service-orders.index')" wire:navigate>Ordens de serviço</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>Nova</flux:breadcrumbs.item>
        </flux:breadcrumbs>
        <flux:heading size="xl" level="1">Nova ordem de serviço</flux:heading>
    </div>

    <form wire:submit="save" class="flex flex-col gap-8">
        {{-- 1. Cliente --}}
        <flux:card class="flex flex-col gap-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <flux:heading size="lg">1. Cliente</flux:heading>
                <flux:radio.group wire:model.live="customerMode" variant="segmented" size="sm">
                    <flux:radio value="existing" label="Já cadastrado" />
                    <flux:radio value="new" label="Novo cliente" />
                </flux:radio.group>
            </div>

            @if ($customerMode === 'existing')
                @if ($this->selectedCustomer)
                    <div class="flex items-center justify-between rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                        <div>
                            <flux:heading>{{ $this->selectedCustomer->name }}</flux:heading>
                            <flux:text>{{ Phone::format($this->selectedCustomer->phone) }}</flux:text>
                        </div>
                        <flux:button size="sm" variant="ghost" wire:click="clearCustomer">Trocar</flux:button>
                    </div>
                @else
                    <flux:input
                        wire:model.live.debounce.300ms="customerSearch"
                        icon="magnifying-glass"
                        placeholder="Buscar por nome ou telefone"
                        autofocus
                    />

                    @if ($this->customerResults->isNotEmpty())
                        <div class="flex flex-col divide-y divide-zinc-200 rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                            @foreach ($this->customerResults as $result)
                                <button
                                    type="button"
                                    wire:key="result-{{ $result->id }}"
                                    wire:click="selectCustomer({{ $result->id }})"
                                    class="flex justify-between px-3 py-2 text-start hover:bg-zinc-50 dark:hover:bg-zinc-700/50"
                                >
                                    <span class="font-medium">{{ $result->name }}</span>
                                    <span class="text-sm text-zinc-500">{{ Phone::format($result->phone) }}</span>
                                </button>
                            @endforeach
                        </div>
                    @elseif (mb_strlen(trim($customerSearch)) >= 2)
                        <flux:text>
                            Nenhum cliente encontrado.
                            <flux:link as="button" type="button" wire:click="$set('customerMode', 'new')">Cadastrar novo cliente</flux:link>
                        </flux:text>
                    @endif

                    <flux:error name="customerId" />
                @endif
            @else
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="customerForm.name" label="Nome" required class="sm:col-span-2" />
                    <flux:input
                        wire:model="customerForm.phone"
                        label="Telefone (WhatsApp)"
                        mask:dynamic="$input.replace(/\D/g, '').length > 10 ? '(99) 99999-9999' : '(99) 9999-99999'"
                        placeholder="(65) 99999-8888"
                        inputmode="tel"
                        required
                    />
                    <flux:input wire:model="customerForm.email" label="E-mail" type="email" badge="Opcional" />
                    <flux:input wire:model="customerForm.document" label="CPF" mask="999.999.999-99" inputmode="numeric" badge="Opcional" />
                </div>
            @endif
        </flux:card>

        {{-- 2. Aparelho --}}
        <flux:card class="flex flex-col gap-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <flux:heading size="lg">2. Aparelho</flux:heading>
                @if ($customerMode === 'existing' && $this->customerDevices->isNotEmpty())
                    <flux:radio.group wire:model.live="deviceMode" variant="segmented" size="sm">
                        <flux:radio value="existing" label="Já cadastrado" />
                        <flux:radio value="new" label="Novo aparelho" />
                    </flux:radio.group>
                @endif
            </div>

            @if ($customerMode === 'existing' && ! $this->selectedCustomer)
                <flux:text>Escolha o cliente primeiro.</flux:text>
            @elseif ($deviceMode === 'existing')
                <flux:radio.group wire:model="deviceId" variant="cards" class="flex-col">
                    @foreach ($this->customerDevices as $device)
                        <flux:radio
                            wire:key="device-{{ $device->id }}"
                            :value="$device->id"
                            :label="$device->description()"
                            :description="$device->type->label().($device->color ? ' · '.$device->color : '').($device->serial_or_imei ? ' · '.$device->serial_or_imei : '')"
                        />
                    @endforeach
                </flux:radio.group>
                <flux:error name="deviceId" />
            @else
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:select wire:model="deviceForm.type" label="Tipo">
                        @foreach ($deviceTypes as $type)
                            <flux:select.option :value="$type->value">{{ $type->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:input wire:model="deviceForm.brand" label="Marca" placeholder="Samsung" required />
                    <flux:input wire:model="deviceForm.model" label="Modelo" placeholder="Galaxy A54" required />
                    <flux:input wire:model="deviceForm.color" label="Cor" badge="Opcional" />
                    <flux:input wire:model="deviceForm.serial_or_imei" label="Nº de série / IMEI" badge="Opcional" class="sm:col-span-2" />
                </div>
            @endif
        </flux:card>

        {{-- 3. OS --}}
        <flux:card class="flex flex-col gap-4">
            <flux:heading size="lg">3. Atendimento</flux:heading>

            <flux:textarea wire:model="form.reported_issue" label="Defeito relatado pelo cliente" rows="3" required />

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="form.accessories" label="Acessórios deixados" placeholder="Carregador, capa, chip..." badge="Opcional" />
                <flux:input
                    wire:model="form.device_password"
                    label="Senha / padrão de desbloqueio"
                    description="Guardada criptografada."
                    autocomplete="off"
                    badge="Opcional"
                />
                <flux:select wire:model="form.technician_id" label="Técnico responsável" placeholder="Definir depois">
                    <flux:select.option value="">Definir depois</flux:select.option>
                    @foreach ($this->technicians as $technician)
                        <flux:select.option :value="$technician->id">{{ $technician->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="form.estimated_delivery_date" type="date" label="Previsão de entrega" badge="Opcional" />
                <flux:input wire:model="form.warranty_days" type="number" min="0" label="Garantia (dias)" />
            </div>
        </flux:card>

        <div class="flex gap-2">
            <flux:button type="submit" variant="primary">Abrir OS</flux:button>
            <flux:button :href="route('service-orders.index')" variant="ghost" wire:navigate>Cancelar</flux:button>
        </div>
    </form>
</div>
