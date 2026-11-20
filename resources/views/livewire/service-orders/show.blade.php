@php
    use App\Actions\ServiceOrders\StoreServiceOrderPhotos;
    use App\Support\{Dates, Money, Phone};
@endphp

<div class="flex flex-col gap-8">
    {{-- Cabeçalho --}}
    <div>
        <flux:breadcrumbs class="mb-2">
            <flux:breadcrumbs.item :href="route('service-orders.index')" wire:navigate>Ordens de serviço</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ $order->number }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex flex-wrap items-center gap-3">
                <flux:heading size="xl" level="1">{{ $order->number }}</flux:heading>
                <x-status-badge :status="$order->status" />
                @if ($order->isOverdue())
                    <flux:badge size="sm" color="red" icon="exclamation-triangle">Atrasada</flux:badge>
                @endif
            </div>

            <div class="flex flex-wrap gap-2">
                @can('changeStatus', $order)
                    <flux:button variant="primary" icon="arrow-path" wire:click="openStatusModal">Mudar status</flux:button>
                @endcan
                @can('update', $order)
                    <flux:button icon="pencil-square" wire:click="openDetailsModal">Editar dados</flux:button>
                @endcan
                @can('delete', $order)
                    <flux:modal.trigger name="delete-order">
                        <flux:button variant="danger" icon="trash" aria-label="Excluir OS" />
                    </flux:modal.trigger>
                @endcan
            </div>
        </div>
    </div>

    <div class="grid gap-8 lg:grid-cols-3">
        <div class="flex flex-col gap-8 lg:col-span-2">
            {{-- Dados --}}
            <flux:card class="grid gap-6 sm:grid-cols-2">
                <div>
                    <flux:text>Cliente</flux:text>
                    <flux:link :href="route('customers.show', $order->customer)" wire:navigate class="font-medium">{{ $order->customer->name }}</flux:link>
                    <flux:text>{{ Phone::format($order->customer->phone) }}</flux:text>
                </div>
                <div>
                    <flux:text>Aparelho</flux:text>
                    <flux:heading>{{ $order->device->description() }}</flux:heading>
                    <flux:text>
                        {{ $order->device->type->label() }}
                        @if ($order->device->color) · {{ $order->device->color }} @endif
                        @if ($order->device->serial_or_imei) · {{ $order->device->serial_or_imei }} @endif
                    </flux:text>
                </div>
                <div class="sm:col-span-2">
                    <flux:text>Defeito relatado</flux:text>
                    <flux:heading class="whitespace-pre-line font-normal">{{ $order->reported_issue }}</flux:heading>
                </div>
                <div class="sm:col-span-2">
                    <flux:text>Laudo técnico</flux:text>
                    <flux:heading class="whitespace-pre-line font-normal">{{ $order->diagnosis ?? 'Ainda não informado.' }}</flux:heading>
                </div>
                <div>
                    <flux:text>Acessórios deixados</flux:text>
                    <flux:heading class="font-normal">{{ $order->accessories ?? 'Nenhum' }}</flux:heading>
                </div>
                <div>
                    <flux:text>Senha do aparelho</flux:text>
                    @if ($order->device_password)
                        <div class="flex items-center gap-2">
                            <flux:heading class="font-mono">{{ $showPassword ? $order->device_password : '••••••' }}</flux:heading>
                            <flux:button size="xs" variant="ghost" :icon="$showPassword ? 'eye-slash' : 'eye'" wire:click="togglePassword">
                                {{ $showPassword ? 'Ocultar' : 'Mostrar' }}
                            </flux:button>
                        </div>
                    @else
                        <flux:heading class="font-normal">Não informada</flux:heading>
                    @endif
                </div>
            </flux:card>

            {{-- Orçamento --}}
            <section class="flex flex-col gap-4">
                <div class="flex items-center justify-between">
                    <flux:heading size="lg" level="2">Orçamento</flux:heading>
                    @can('manageItems', $order)
                        <flux:button size="sm" icon="plus" wire:click="openItemModal">Adicionar item</flux:button>
                    @endcan
                </div>

                @cannot('manageItems', $order)
                    <flux:callout icon="lock-closed" color="zinc" variant="secondary">
                        <flux:callout.text>O orçamento está fechado porque o cliente já decidiu. Só o administrador pode alterá-lo.</flux:callout.text>
                    </flux:callout>
                @endcannot

                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Descrição</flux:table.column>
                        <flux:table.column>Tipo</flux:table.column>
                        <flux:table.column align="end">Qtd.</flux:table.column>
                        <flux:table.column align="end">Unitário</flux:table.column>
                        <flux:table.column align="end">Total</flux:table.column>
                        @can('manageItems', $order)
                            <flux:table.column />
                        @endcan
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($this->items as $item)
                            <flux:table.row :key="$item->id">
                                <flux:table.cell variant="strong" class="whitespace-normal">{{ $item->description }}</flux:table.cell>
                                <flux:table.cell>{{ $item->type->label() }}</flux:table.cell>
                                <flux:table.cell align="end">{{ $item->quantity }}</flux:table.cell>
                                <flux:table.cell align="end">{{ Money::format($item->unit_price_cents) }}</flux:table.cell>
                                <flux:table.cell align="end">{{ Money::format($item->totalCents()) }}</flux:table.cell>
                                @can('manageItems', $order)
                                    <flux:table.cell align="end">
                                        <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="openItemModal({{ $item->id }})" aria-label="Editar item" />
                                        <flux:button size="xs" variant="ghost" icon="trash" wire:click="deleteItem({{ $item->id }})" wire:confirm="Remover este item?" aria-label="Remover item" />
                                    </flux:table.cell>
                                @endcan
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="6" class="text-center">Nenhum item no orçamento.</flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>

                <div class="ms-auto flex w-full max-w-xs flex-col gap-1 text-sm">
                    <div class="flex justify-between"><span>Peças</span><span>{{ Money::format($this->totals['parts']) }}</span></div>
                    <div class="flex justify-between"><span>Mão de obra</span><span>{{ Money::format($this->totals['labor']) }}</span></div>
                    <flux:separator class="my-1" />
                    <div class="flex justify-between text-base font-semibold"><span>Total</span><span data-test="order-total">{{ Money::format($this->totals['total']) }}</span></div>
                </div>
            </section>

            {{-- Fotos --}}
            <section class="flex flex-col gap-4">
                <flux:heading size="lg" level="2">Fotos de entrada ({{ $order->photos->count() }}/{{ StoreServiceOrderPhotos::MAX_PHOTOS }})</flux:heading>

                @if ($order->photos->isNotEmpty())
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-5">
                        @foreach ($order->photos as $photo)
                            <div wire:key="photo-{{ $photo->id }}" class="group relative">
                                <a href="{{ route('service-orders.photos.show', [$order, $photo]) }}" target="_blank">
                                    <img
                                        src="{{ route('service-orders.photos.show', [$order, $photo]) }}"
                                        alt="Foto do aparelho"
                                        class="aspect-square w-full rounded-lg border border-zinc-200 object-cover dark:border-zinc-700"
                                        loading="lazy"
                                    >
                                </a>
                                @can('deletePhotos', $order)
                                    <flux:button
                                        size="xs" variant="danger" icon="trash"
                                        class="absolute end-1 top-1"
                                        wire:click="deletePhoto({{ $photo->id }})"
                                        wire:confirm="Excluir esta foto?"
                                        aria-label="Excluir foto"
                                    />
                                @endcan
                            </div>
                        @endforeach
                    </div>
                @endif

                @can('addPhotos', $order)
                    @if ($order->photos->count() < StoreServiceOrderPhotos::MAX_PHOTOS)
                        <form wire:submit="savePhotos" class="flex flex-col gap-3">
                            <input
                                type="file"
                                wire:model="uploads"
                                multiple
                                accept="image/jpeg,image/png,image/webp"
                                class="text-sm file:me-3 file:rounded-md file:border-0 file:bg-zinc-100 file:px-3 file:py-2 file:text-sm dark:file:bg-zinc-700"
                            >
                            <flux:text size="sm">Até {{ StoreServiceOrderPhotos::MAX_PHOTOS }} fotos por OS, JPG/PNG/WEBP, máx. 5 MB cada.</flux:text>
                            <flux:error name="uploads" />
                            @foreach ($errors->get('uploads.*') as $messages)
                                <flux:text class="text-red-500" size="sm">{{ $messages[0] }}</flux:text>
                            @endforeach
                            <div wire:loading wire:target="uploads"><flux:text size="sm">Carregando arquivos...</flux:text></div>
                            @if ($uploads !== [])
                                <div><flux:button type="submit" size="sm" icon="arrow-up-tray">Enviar {{ count($uploads) }} foto(s)</flux:button></div>
                            @endif
                        </form>
                    @endif
                @endcan
            </section>
        </div>

        {{-- Coluna lateral --}}
        <div class="flex flex-col gap-8">
            <flux:card class="flex flex-col gap-3 text-sm">
                <div class="flex justify-between gap-4"><flux:text>Entrada</flux:text><span>{{ Dates::dateTime($order->created_at) }}</span></div>
                <div class="flex justify-between gap-4">
                    <flux:text>Previsão de entrega</flux:text>
                    <span @class(['font-semibold text-red-600 dark:text-red-400' => $order->isOverdue()])>{{ Dates::date($order->estimated_delivery_at) }}</span>
                </div>
                @if ($order->approved_at)
                    <div class="flex justify-between gap-4"><flux:text>Aprovado em</flux:text><span>{{ Dates::dateTime($order->approved_at) }}</span></div>
                @endif
                @if ($order->delivered_at)
                    <div class="flex justify-between gap-4"><flux:text>Entregue em</flux:text><span>{{ Dates::dateTime($order->delivered_at) }}</span></div>
                @endif
                <div class="flex justify-between gap-4"><flux:text>Garantia</flux:text><span>{{ $order->warranty_days }} dias</span></div>
                <flux:separator />
                <div class="flex justify-between gap-4"><flux:text>Aberta por</flux:text><span>{{ $order->openedBy->name }}</span></div>
                <div class="flex justify-between gap-4"><flux:text>Técnico</flux:text><span>{{ $order->technician->name ?? 'Não definido' }}</span></div>
            </flux:card>

            <section class="flex flex-col gap-4">
                <flux:heading size="lg" level="2">Linha do tempo</flux:heading>
                <ol class="relative flex flex-col gap-5 border-s border-zinc-200 ps-5 dark:border-zinc-700">
                    @foreach ($order->statusHistory->reverse() as $entry)
                        <li wire:key="history-{{ $entry->id }}" class="relative">
                            <span class="absolute -start-[1.65rem] top-1 size-3 rounded-full border-2 border-white bg-zinc-400 dark:border-zinc-800"></span>
                            <div class="flex flex-wrap items-center gap-2">
                                <x-status-badge :status="$entry->to_status" />
                                <flux:text size="sm">{{ Dates::dateTime($entry->created_at) }}</flux:text>
                            </div>
                            <flux:text size="sm" class="mt-1">
                                {{ $entry->user?->name ?? 'Cliente (pelo link de acompanhamento)' }}
                            </flux:text>
                            @if ($entry->note)
                                <flux:text class="mt-1 whitespace-pre-line text-zinc-800 dark:text-zinc-200">{{ $entry->note }}</flux:text>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </section>
        </div>
    </div>

    {{-- Modal: mudar status --}}
    <flux:modal name="change-status" class="w-full max-w-md">
        <form wire:submit="changeStatus" class="flex flex-col gap-6">
            <div>
                <flux:heading size="lg">Mudar status</flux:heading>
                <flux:text class="mt-1">Atual: {{ $order->status->label() }}</flux:text>
            </div>
            <flux:select wire:model="newStatus" label="Novo status">
                @foreach ($order->status->allowedTransitions() as $status)
                    <flux:select.option :value="$status->value">{{ $status->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:textarea wire:model="statusNote" label="Observação" badge="Opcional" rows="3" placeholder="Ex.: peça chega na sexta" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Cancelar</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Confirmar</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Modal: editar dados --}}
    <flux:modal name="edit-details" class="w-full max-w-2xl">
        <form wire:submit="saveDetails" class="flex flex-col gap-6">
            <flux:heading size="lg">Editar dados da OS</flux:heading>
            <flux:textarea wire:model="form.reported_issue" label="Defeito relatado" rows="2" required />
            <flux:textarea wire:model="form.diagnosis" label="Laudo técnico" rows="4" />
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="form.accessories" label="Acessórios deixados" />
                <flux:input wire:model="form.device_password" label="Senha do aparelho" autocomplete="off" />
                <flux:select wire:model="form.technician_id" label="Técnico responsável">
                    <flux:select.option value="">Não definido</flux:select.option>
                    @foreach ($this->technicians as $technician)
                        <flux:select.option :value="$technician->id">{{ $technician->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="form.estimated_delivery_date" type="date" label="Previsão de entrega" />
                <flux:input wire:model="form.warranty_days" type="number" min="0" label="Garantia (dias)" />
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Cancelar</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Salvar</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Modal: item do orçamento --}}
    <flux:modal name="item-form" class="w-full max-w-lg">
        <form wire:submit="saveItem" class="flex flex-col gap-6">
            <flux:heading size="lg">{{ $itemForm->item ? 'Editar item' : 'Novo item' }}</flux:heading>
            <flux:radio.group wire:model="itemForm.type" variant="segmented" label="Tipo">
                @foreach ($itemTypes as $type)
                    <flux:radio :value="$type->value" :label="$type->label()" />
                @endforeach
            </flux:radio.group>
            <flux:input wire:model="itemForm.description" label="Descrição" required />
            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="itemForm.quantity" type="number" min="1" label="Quantidade" required />
                <flux:input
                    wire:model="itemForm.unit_price"
                    label="Valor unitário (R$)"
                    mask:dynamic="$money($input, ',', '.')"
                    inputmode="decimal"
                    placeholder="0,00"
                    required
                />
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Cancelar</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Salvar</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Modal: excluir OS --}}
    @can('delete', $order)
        <flux:modal name="delete-order" class="max-w-md">
            <div class="flex flex-col gap-6">
                <div>
                    <flux:heading size="lg">Excluir {{ $order->number }}?</flux:heading>
                    <flux:text class="mt-2">A OS deixa de aparecer nas listas. O número não será reaproveitado.</flux:text>
                </div>
                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button variant="ghost">Cancelar</flux:button></flux:modal.close>
                    <flux:button variant="danger" wire:click="deleteOrder">Excluir</flux:button>
                </div>
            </div>
        </flux:modal>
    @endcan
</div>
