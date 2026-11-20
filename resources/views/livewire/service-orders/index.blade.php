@php use App\Support\Dates; @endphp

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <flux:heading size="xl" level="1">Ordens de serviço</flux:heading>
        <flux:button variant="primary" icon="plus" :href="route('service-orders.create')" wire:navigate>Nova OS</flux:button>
    </div>

    @if ($this->overdueCount > 0 && ! $onlyOverdue)
        <flux:callout icon="exclamation-triangle" color="red" variant="secondary">
            <flux:callout.heading>{{ $this->overdueCount }} OS com previsão de entrega vencida</flux:callout.heading>
            <x-slot name="actions">
                <flux:button size="sm" wire:click="$set('onlyOverdue', true)">Ver atrasadas</flux:button>
            </x-slot>
        </flux:callout>
    @endif

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Número ou cliente" class="lg:col-span-2" />

        <flux:select wire:model.live="status" aria-label="Status">
            <flux:select.option value="">Todos os status</flux:select.option>
            <flux:select.option value="open">Em aberto</flux:select.option>
            @foreach ($statuses as $option)
                <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="technician" aria-label="Técnico">
            <flux:select.option value="">Todos os técnicos</flux:select.option>
            <flux:select.option value="none">Sem técnico</flux:select.option>
            @foreach ($this->technicians as $user)
                <flux:select.option :value="(string) $user->id">{{ $user->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:input wire:model.live="from" type="date" aria-label="Entrada a partir de" />
        <flux:input wire:model.live="to" type="date" aria-label="Entrada até" />
    </div>

    <div class="flex flex-wrap items-center gap-4">
        <flux:switch wire:model.live="onlyOverdue" label="Só atrasadas" align="left" />
        <flux:button size="sm" variant="ghost" wire:click="clearFilters">Limpar filtros</flux:button>
    </div>

    <flux:table :paginate="$this->orders">
        <flux:table.columns>
            <flux:table.column>OS</flux:table.column>
            <flux:table.column>Cliente</flux:table.column>
            <flux:table.column class="hidden md:table-cell">Aparelho</flux:table.column>
            <flux:table.column class="hidden lg:table-cell">Técnico</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column class="hidden sm:table-cell">Entrada</flux:table.column>
            <flux:table.column>Previsão</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->orders as $order)
                @php $overdue = $order->isOverdue(); @endphp
                <flux:table.row :key="$order->id" @class(['bg-red-50 dark:bg-red-950/30' => $overdue])>
                    <flux:table.cell variant="strong">
                        <flux:link :href="route('service-orders.show', $order)" wire:navigate>{{ $order->number }}</flux:link>
                    </flux:table.cell>
                    <flux:table.cell>{{ $order->customer->name }}</flux:table.cell>
                    <flux:table.cell class="hidden md:table-cell">{{ $order->device->description() }}</flux:table.cell>
                    <flux:table.cell class="hidden lg:table-cell">{{ $order->technician->name ?? '—' }}</flux:table.cell>
                    <flux:table.cell><x-status-badge :status="$order->status" /></flux:table.cell>
                    <flux:table.cell class="hidden sm:table-cell">{{ Dates::date($order->created_at) }}</flux:table.cell>
                    <flux:table.cell>
                        <span @class(['font-semibold text-red-600 dark:text-red-400' => $overdue])>{{ Dates::date($order->estimated_delivery_at) }}</span>
                        @if ($overdue)
                            <flux:badge size="sm" color="red" class="ms-1">Atrasada</flux:badge>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7" class="text-center">Nenhuma ordem de serviço encontrada.</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
