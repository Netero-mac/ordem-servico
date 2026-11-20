@php use App\Support\Money; @endphp
<table class="items">
    <thead>
        <tr>
            <th>Descrição</th>
            <th>Tipo</th>
            <th class="right">Qtd.</th>
            <th class="right">Unitário</th>
            <th class="right">Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($order->items as $item)
            <tr>
                <td>{{ $item->description }}</td>
                <td>{{ $item->type->label() }}</td>
                <td class="right">{{ $item->quantity }}</td>
                <td class="right">{{ Money::format($item->unit_price_cents) }}</td>
                <td class="right">{{ Money::format($item->totalCents()) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
<table class="totals" style="width: 40%; margin-left: 60%; margin-top: 6px;">
    <tr><td>Peças</td><td class="right">{{ Money::format($totals['parts']) }}</td></tr>
    <tr><td>Mão de obra</td><td class="right">{{ Money::format($totals['labor']) }}</td></tr>
    <tr class="grand"><td>Total</td><td class="right">{{ Money::format($totals['total']) }}</td></tr>
</table>
