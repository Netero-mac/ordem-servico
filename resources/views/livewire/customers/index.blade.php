<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <flux:heading size="xl" level="1">Clientes</flux:heading>

        <flux:button variant="primary" icon="plus" :href="route('customers.create')" wire:navigate>
            Novo cliente
        </flux:button>
    </div>

    <flux:input
        wire:model.live.debounce.300ms="search"
        icon="magnifying-glass"
        placeholder="Buscar por nome ou telefone"
        clearable
        class="max-w-md"
    />

    <flux:table :paginate="$this->customers">
        <flux:table.columns>
            <flux:table.column>Nome</flux:table.column>
            <flux:table.column>Telefone</flux:table.column>
            <flux:table.column class="hidden md:table-cell">E-mail</flux:table.column>
            <flux:table.column align="end">Aparelhos</flux:table.column>
            <flux:table.column align="end">OS</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->customers as $customer)
                <flux:table.row :key="$customer->id">
                    <flux:table.cell variant="strong">
                        <flux:link :href="route('customers.show', $customer)" wire:navigate>{{ $customer->name }}</flux:link>
                    </flux:table.cell>
                    <flux:table.cell>{{ \App\Support\Phone::format($customer->phone) }}</flux:table.cell>
                    <flux:table.cell class="hidden md:table-cell">{{ $customer->email ?? '—' }}</flux:table.cell>
                    <flux:table.cell align="end">{{ $customer->devices_count }}</flux:table.cell>
                    <flux:table.cell align="end">{{ $customer->service_orders_count }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5" class="text-center">
                        {{ $search === '' ? 'Nenhum cliente cadastrado ainda.' : 'Nenhum cliente encontrado para "'.$search.'".' }}
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
