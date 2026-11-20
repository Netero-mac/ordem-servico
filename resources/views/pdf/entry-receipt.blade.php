@php use App\Support\Dates; @endphp
<x-pdf::layout :order="$order" :shop="$shop" title="Comprovante de entrada">
    <h2>Atendimento</h2>
    <table>
        <tr>
            <td style="width: 50%">
                <div class="label">Defeito relatado pelo cliente</div>
                {!! nl2br(e($order->reported_issue)) !!}
            </td>
            <td>
                <div class="label">Acessórios deixados</div>
                {{ $order->accessories ?? 'Nenhum' }}
                <br><br>
                <div class="label">Previsão de entrega</div>
                {{ $order->estimated_delivery_at ? Dates::date($order->estimated_delivery_at) : 'A combinar após a análise' }}
            </td>
        </tr>
    </table>

    @if ($order->items->isNotEmpty())
        <h2>Orçamento</h2>
        @include('pdf.partials-items')
    @endif

    <h2>Condições</h2>
    <ol>
        @foreach ($shop['entry_terms'] as $term)
            <li>{{ $term }}</li>
        @endforeach
    </ol>

    <h2>Termo de garantia</h2>
    <ol>
        @foreach ($shop['warranty_terms'] as $term)
            <li>{{ str_replace(':days', (string) $order->warranty_days, $term) }}</li>
        @endforeach
    </ol>

    <p class="muted" style="margin-top: 12px;">
        Acompanhe o andamento pelo link: {{ route('tracking.show', $order->public_token) }}
    </p>

    <table class="signatures">
        <tr>
            <td><div class="line">{{ $order->customer->name }}<br><span class="muted">Cliente — declaro estar de acordo com as condições acima</span></div></td>
            <td><div class="line">{{ $order->openedBy->name }}<br><span class="muted">{{ $shop['name'] }}</span></div></td>
        </tr>
    </table>
</x-pdf::layout>
