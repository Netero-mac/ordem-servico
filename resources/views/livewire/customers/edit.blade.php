<div class="flex max-w-2xl flex-col gap-6">
    <div>
        <flux:breadcrumbs class="mb-2">
            <flux:breadcrumbs.item :href="route('customers.index')" wire:navigate>Clientes</flux:breadcrumbs.item>
            @if ($form->customer)
                <flux:breadcrumbs.item :href="route('customers.show', $form->customer)" wire:navigate>{{ $form->customer->name }}</flux:breadcrumbs.item>
            @endif
            <flux:breadcrumbs.item>{{ $title }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <flux:heading size="xl" level="1">{{ $title }}</flux:heading>
    </div>

    <form wire:submit="save" class="flex flex-col gap-6">
        <flux:input wire:model="form.name" label="Nome" required autofocus autocomplete="off" />

        <div class="grid gap-6 sm:grid-cols-2">
            <flux:input
                wire:model="form.phone"
                label="Telefone (WhatsApp)"
                mask:dynamic="$input.replace(/\D/g, '').length > 10 ? '(99) 99999-9999' : '(99) 9999-99999'"
                placeholder="(65) 99999-8888"
                inputmode="tel"
                required
            />
            <flux:input wire:model="form.email" label="E-mail" type="email" badge="Opcional" />
        </div>

        <flux:input
            wire:model="form.document"
            label="CPF"
            mask="999.999.999-99"
            placeholder="000.000.000-00"
            inputmode="numeric"
            badge="Opcional"
            class="sm:max-w-xs"
        />

        <flux:textarea wire:model="form.notes" label="Observações" badge="Opcional" rows="3" />

        <div class="flex gap-2">
            <flux:button type="submit" variant="primary">Salvar</flux:button>
            <flux:button
                :href="$form->customer ? route('customers.show', $form->customer) : route('customers.index')"
                variant="ghost"
                wire:navigate
            >Cancelar</flux:button>
        </div>
    </form>
</div>
