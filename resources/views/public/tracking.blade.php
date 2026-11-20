@php use App\Enums\ServiceOrderStatus; @endphp

<x-layouts::public :title="'OS '.$order['number']">
    <header class="flex flex-col gap-1">
        <div class="mb-2 flex items-center gap-2">
            <x-brand-mark size="size-8" />
            <span class="font-semibold text-zinc-800 dark:text-ivory">{{ $order['shop']['name'] }}</span>
        </div>
        <flux:heading size="xl" level="1">Olá, {{ $order['customer_first_name'] }}!</flux:heading>
        <flux:text>Acompanhe aqui a sua ordem de serviço.</flux:text>
    </header>

    @if (session('success'))
        <flux:callout icon="check-circle" color="green" role="status">
            <flux:callout.text>{{ session('success') }}</flux:callout.text>
        </flux:callout>
    @endif
    @if (session('error') || $errors->any())
        <flux:callout icon="exclamation-triangle" color="red" role="alert">
            <flux:callout.text>{{ session('error') ?? $errors->first() }}</flux:callout.text>
        </flux:callout>
    @endif

    <flux:card class="flex flex-col gap-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <flux:heading size="lg">{{ $order['number'] }}</flux:heading>
            <x-status-badge :status="$order['status']" />
        </div>
        <dl class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <dt class="text-zinc-500">Aparelho</dt>
                <dd class="font-medium">{{ $order['device'] }}</dd>
                <dd class="text-zinc-500">{{ $order['device_type'] }}</dd>
            </div>
            <div>
                <dt class="text-zinc-500">Entrada</dt>
                <dd class="font-medium">{{ $order['opened_at'] }}</dd>
            </div>
            @if ($order['delivered_at'])
                <div>
                    <dt class="text-zinc-500">Entregue em</dt>
                    <dd class="font-medium">{{ $order['delivered_at'] }}</dd>
                </div>
                <div>
                    <dt class="text-zinc-500">Garantia</dt>
                    <dd class="font-medium">{{ $order['warranty_days'] }} dias</dd>
                </div>
            @elseif ($order['estimated_delivery'] && $order['status']->isOpen())
                <div>
                    <dt class="text-zinc-500">Previsão de entrega</dt>
                    <dd class="font-medium">{{ $order['estimated_delivery'] }}</dd>
                </div>
            @endif
        </dl>
        @if ($order['status'] === ServiceOrderStatus::Ready)
            <flux:callout icon="check-badge" color="lime" variant="secondary">
                <flux:callout.text>Seu aparelho está pronto para retirada.</flux:callout.text>
            </flux:callout>
        @endif
    </flux:card>

    @if ($order['items'] !== [])
        <flux:card class="flex flex-col gap-4">
            <flux:heading size="lg">Orçamento</flux:heading>

            <ul class="flex flex-col divide-y divide-zinc-200 dark:divide-zinc-700">
                @foreach ($order['items'] as $item)
                    <li class="flex justify-between gap-4 py-2 text-sm">
                        <span>
                            <span class="font-medium">{{ $item['description'] }}</span>
                            <span class="block text-zinc-500">{{ $item['type'] }} · {{ $item['quantity'] }} × {{ $item['unit_price'] }}</span>
                        </span>
                        <span class="whitespace-nowrap font-medium">{{ $item['total'] }}</span>
                    </li>
                @endforeach
            </ul>

            <div class="flex flex-col gap-1 text-sm">
                <div class="flex justify-between"><span>Peças</span><span>{{ $order['parts_total'] }}</span></div>
                <div class="flex justify-between"><span>Mão de obra</span><span>{{ $order['labor_total'] }}</span></div>
                <div class="flex justify-between border-t border-zinc-200 pt-2 text-base font-semibold dark:border-zinc-700">
                    <span>Total</span><span data-test="public-total">{{ $order['total'] }}</span>
                </div>
            </div>

            @if ($order['awaiting_approval'])
                <div class="flex flex-col gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                    <flux:text>O orçamento está aguardando a sua resposta.</flux:text>
                    <div class="grid grid-cols-2 gap-3">
                        <flux:button variant="primary" color="green" icon="check" onclick="document.getElementById('approve-dialog').showModal()">Aprovar</flux:button>
                        <flux:button variant="danger" icon="x-mark" onclick="document.getElementById('reject-dialog').showModal()">Recusar</flux:button>
                    </div>
                </div>
            @endif
        </flux:card>
    @endif

    <flux:card class="flex flex-col gap-4">
        <flux:heading size="lg">Andamento</flux:heading>
        <ol class="relative flex flex-col gap-4 border-s border-zinc-200 ps-5 dark:border-zinc-700">
            @foreach ($order['timeline'] as $entry)
                <li class="relative">
                    <span class="absolute -start-[1.65rem] top-1 size-3 rounded-full border-2 border-white bg-zinc-400 dark:border-zinc-800"></span>
                    <div class="flex flex-wrap items-center gap-2">
                        <x-status-badge :status="$entry['status']" />
                        <flux:text size="sm">{{ $entry['at'] }}</flux:text>
                    </div>
                </li>
            @endforeach
        </ol>
    </flux:card>

    <footer class="flex flex-col gap-1 text-center text-sm text-zinc-500">
        <span class="font-medium">{{ $order['shop']['name'] }}</span>
        @if ($order['shop']['phone']) <span>{{ $order['shop']['phone'] }}</span> @endif
        @if ($order['shop']['address']) <span>{{ $order['shop']['address'] }}</span> @endif
    </footer>

    @if ($order['awaiting_approval'])
        @foreach (['approve' => ['Aprovar o orçamento?', 'Ao confirmar, autorizamos o conserto no valor de '.$order['total'].'.', 'Sim, aprovar', 'primary'], 'reject' => ['Recusar o orçamento?', 'O aparelho não será consertado e ficará disponível para retirada.', 'Sim, recusar', 'danger']] as $decision => [$title, $text, $confirm, $variant])
            <dialog id="{{ $decision }}-dialog" class="m-auto w-[min(28rem,calc(100%-2rem))] rounded-xl bg-white p-6 text-zinc-800 shadow-xl backdrop:bg-black/50 dark:bg-zinc-800 dark:text-zinc-100">
                <form method="POST" action="{{ route('tracking.decide', request()->route('token')) }}" class="flex flex-col gap-4">
                    @csrf
                    <input type="hidden" name="decision" value="{{ $decision }}">
                    <input type="hidden" name="confirmed" value="1">
                    <flux:heading size="lg">{{ $title }}</flux:heading>
                    <flux:text>{{ $text }}</flux:text>
                    @if ($decision === 'reject')
                        <flux:textarea name="reason" label="Quer contar o motivo?" badge="Opcional" rows="2" maxlength="500" />
                    @endif
                    <div class="flex justify-end gap-2">
                        <flux:button type="button" variant="ghost" onclick="this.closest('dialog').close()">Voltar</flux:button>
                        <flux:button type="submit" :variant="$variant" :color="$decision === 'approve' ? 'green' : null">{{ $confirm }}</flux:button>
                    </div>
                </form>
            </dialog>
        @endforeach
    @endif
</x-layouts::public>
