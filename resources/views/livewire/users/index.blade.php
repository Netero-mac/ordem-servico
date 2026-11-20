<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">Usuários</flux:heading>
            <flux:text>Quem acessa o sistema. Técnicos não podem excluir registros.</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="create">Novo usuário</flux:button>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Nome</flux:table.column>
            <flux:table.column>E-mail</flux:table.column>
            <flux:table.column>Papel</flux:table.column>
            <flux:table.column />
        </flux:table.columns>
        <flux:table.rows>
            @foreach ($this->users as $user)
                <flux:table.row :key="$user->id">
                    <flux:table.cell variant="strong">
                        {{ $user->name }}
                        @if ($user->is(auth()->user())) <flux:badge size="sm" class="ms-1">Você</flux:badge> @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $user->email }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$user->isAdmin() ? 'indigo' : 'zinc'">{{ $user->role->label() }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="edit({{ $user->id }})" aria-label="Editar" />
                        @can('delete', $user)
                            <flux:button size="xs" variant="ghost" icon="trash" wire:click="delete({{ $user->id }})" wire:confirm="Excluir {{ $user->name }}?" aria-label="Excluir" />
                        @endcan
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <flux:modal name="user-form" class="w-full max-w-md">
        <form wire:submit="save" class="flex flex-col gap-6">
            <flux:heading size="lg">{{ $form->user ? 'Editar usuário' : 'Novo usuário' }}</flux:heading>
            <flux:input wire:model="form.name" label="Nome" required />
            <flux:input wire:model="form.email" label="E-mail" type="email" required />
            @if (! $form->user || auth()->user()->can('changeRole', $form->user))
                <flux:radio.group wire:model="form.role" variant="segmented" label="Papel">
                    @foreach ($roles as $role)
                        <flux:radio :value="$role->value" :label="$role->label()" />
                    @endforeach
                </flux:radio.group>
            @endif
            <flux:input
                wire:model="form.password"
                type="password"
                label="{{ $form->user ? 'Nova senha' : 'Senha' }}"
                :description="$form->user ? 'Deixe em branco para manter a atual.' : 'A pessoa pode trocar depois em Configurações.'"
                autocomplete="new-password"
                :required="! $form->user"
            />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Cancelar</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Salvar</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
