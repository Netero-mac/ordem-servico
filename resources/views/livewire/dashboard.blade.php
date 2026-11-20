@php use App\Support\{Dates, Money}; @endphp

<div class="flex flex-col gap-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">Painel</flux:heading>
            <flux:text>{{ $month }}</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" :href="route('service-orders.create')" wire:navigate>Nova OS</flux:button>
    </div>

    {{-- Indicadores --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <flux:card class="flex flex-col gap-1">
            <flux:text>OS em aberto</flux:text>
            <span class="text-3xl font-semibold tabular-nums text-zinc-900 dark:text-white" data-test="open-total">{{ $openTotal }}</span>
            <flux:link :href="route('service-orders.index', ['status' => 'open'])" wire:navigate class="text-sm">Ver lista</flux:link>
        </flux:card>

        <flux:card class="flex flex-col gap-1">
            <flux:text class="flex items-center gap-1">
                @if ($overdueCount > 0)
                    <flux:icon.exclamation-triangle variant="micro" class="text-red-600 dark:text-red-400" />
                @endif
                Atrasadas
            </flux:text>
            <span @class([
                'text-3xl font-semibold tabular-nums',
                'text-red-600 dark:text-red-400' => $overdueCount > 0,
                'text-zinc-900 dark:text-white' => $overdueCount === 0,
            ]) data-test="overdue-total">{{ $overdueCount }}</span>
            <flux:link :href="route('service-orders.index', ['atrasadas' => 1])" wire:navigate class="text-sm">
                {{ $overdueCount > 0 ? 'Ver atrasadas' : 'Nenhuma atrasada' }}
            </flux:link>
        </flux:card>

        <flux:card class="flex flex-col gap-1">
            <flux:text>Entregues no mês</flux:text>
            <span class="text-3xl font-semibold tabular-nums text-zinc-900 dark:text-white" data-test="delivered-total">{{ $delivered }}</span>
            <flux:text size="sm">até {{ $today }}</flux:text>
        </flux:card>

        <flux:card class="flex flex-col gap-1">
            <flux:text>Faturamento do mês</flux:text>
            <span class="text-3xl font-semibold tabular-nums text-zinc-900 dark:text-white" data-test="revenue">{{ Money::format($revenue) }}</span>
            <flux:text size="sm" class="flex items-center gap-1">
                @if ($revenueChange === null)
                    Sem faturamento no mês anterior
                @else
                    @if ($revenueChange >= 0)
                        <flux:icon.arrow-trending-up variant="micro" />
                    @else
                        <flux:icon.arrow-trending-down variant="micro" />
                    @endif
                    {{ $revenueChange >= 0 ? '+' : '' }}{{ number_format($revenueChange, 0, ',', '.') }}% vs. mês anterior ({{ Money::format($revenueLastMonth) }})
                @endif
            </flux:text>
        </flux:card>
    </div>

    <div class="grid gap-8 lg:grid-cols-2">
        {{-- OS em aberto por status: uma série, uma cor; o nome do status é texto. --}}
        <section class="flex flex-col gap-4">
            <flux:heading size="lg" level="2">OS em aberto por status</flux:heading>

            <flux:card>
                <ul class="flex flex-col gap-3" aria-label="Quantidade de OS em aberto por status">
                    @php
                        // Mesma cor do badge de cada status. Classes literais para o Tailwind enxergar.
                        $barColors = [
                            'zinc' => 'bg-zinc-400 dark:bg-zinc-400',
                            'sky' => 'bg-sky-500 dark:bg-sky-400',
                            'amber' => 'bg-amber-500 dark:bg-amber-400',
                            'emerald' => 'bg-emerald-500 dark:bg-emerald-400',
                            'rose' => 'bg-rose-500 dark:bg-rose-400',
                            'orange' => 'bg-orange-500 dark:bg-orange-400',
                            'indigo' => 'bg-indigo-500 dark:bg-indigo-400',
                            'lime' => 'bg-lime-500 dark:bg-lime-400',
                            'green' => 'bg-green-600 dark:bg-green-400',
                            'red' => 'bg-red-500 dark:bg-red-400',
                        ];
                    @endphp
                    @foreach ($byStatus as $row)
                        <li>
                            <a
                                href="{{ route('service-orders.index', ['status' => $row['status']->value]) }}"
                                wire:navigate
                                title="{{ $row['status']->label() }}: {{ $row['count'] }} OS"
                                class="group grid grid-cols-[10rem_1fr_2.5rem] items-center gap-3 rounded-md py-1 text-sm hover:bg-zinc-50 dark:hover:bg-zinc-700/40"
                            >
                                <span class="truncate text-zinc-600 dark:text-zinc-300">{{ $row['status']->label() }}</span>
                                <span class="h-2.5 w-full">
                                    @if ($row['count'] > 0)
                                        <span
                                            class="block h-full rounded-e transition-opacity group-hover:opacity-80 {{ $barColors[$row['status']->color()] }}"
                                            style="width: {{ max(2, round($row['count'] / $maxCount * 100)) }}%"
                                        ></span>
                                    @endif
                                </span>
                                <span class="text-end font-medium tabular-nums text-zinc-900 dark:text-white">{{ $row['count'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </flux:card>
        </section>

        {{-- Atrasadas --}}
        <section class="flex flex-col gap-4">
            <flux:heading size="lg" level="2">Atrasadas há mais tempo</flux:heading>

            @if ($mostOverdue->isEmpty())
                <flux:card>
                    <flux:text class="flex items-center gap-2">
                        <flux:icon.check-circle variant="mini" class="text-green-600 dark:text-green-400" />
                        Nenhuma OS atrasada. Tudo em dia!
                    </flux:text>
                </flux:card>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>OS</flux:table.column>
                        <flux:table.column>Cliente</flux:table.column>
                        <flux:table.column>Status</flux:table.column>
                        <flux:table.column>Previsão</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($mostOverdue as $order)
                            <flux:table.row :key="$order->id">
                                <flux:table.cell variant="strong">
                                    <flux:link :href="route('service-orders.show', $order)" wire:navigate>{{ $order->number }}</flux:link>
                                </flux:table.cell>
                                <flux:table.cell class="max-w-40 truncate">{{ $order->customer->name }}</flux:table.cell>
                                <flux:table.cell><x-status-badge :status="$order->status" /></flux:table.cell>
                                <flux:table.cell class="font-medium text-red-600 dark:text-red-400">{{ Dates::date($order->estimated_delivery_at) }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </section>
    </div>
</div>
