@php use App\Support\Dates; @endphp
<x-pdf::layout :order="$order" :shop="$shop" title="Comprovante de entrega">
    <h2>Serviço</h2>
    <table>
        <tr>
            <td style="width: 50%">
                <div class="label">Defeito relatado</div>
                {!! nl2br(e($order->reported_issue)) !!}
            </td>
            <td>
                <div class="label">Laudo técnico</div>
                {!! $order->diagnosis ? nl2br(e($order->diagnosis)) : '—' !!}
            </td>
        </tr>
    </table>

    @if ($order->items->isNotEmpty() && $order->approved_at)
        <h2>Peças e serviços</h2>
        @include('pdf.partials-items')
    @elseif (! $order->approved_at)
        <p><strong>Orçamento não aprovado:</strong> o aparelho foi devolvido sem reparo.</p>
    @endif

    <h2>Entrega e garantia</h2>
    <table>
        <tr>
            <td style="width: 33%">
                <div class="label">Data da entrega</div>
                {{ $order->delivered_at ? Dates::dateTime($order->delivered_at) : '____/____/________' }}
            </td>
            <td style="width: 33%">
                <div class="label">Acessórios devolvidos</div>
                {{ $order->accessories ?? 'Nenhum' }}
            </td>
            <td>
                @if ($order->approved_at)
                    <div class="label">Garantia</div>
                    {{ $order->warranty_days }} dias
                    @if ($order->warrantyEndsAt()) — até {{ Dates::date($order->warrantyEndsAt()) }} @endif
                @endif
            </td>
        </tr>
    </table>

    @if ($order->approved_at)
        <h2>Termo de garantia</h2>
        <ol>
            @foreach ($shop['warranty_terms'] as $term)
                <li>{{ str_replace(':days', (string) $order->warranty_days, $term) }}</li>
            @endforeach
        </ol>
    @endif

    <table class="signatures">
        <tr>
            <td><div class="line">{{ $order->customer->name }}<br><span class="muted">Recebi o aparelho e os acessórios descritos acima</span></div></td>
            <td><div class="line">{{ $shop['name'] }}<br><span class="muted">Responsável pela entrega</span></div></td>
        </tr>
    </table>
</x-pdf::layout>
