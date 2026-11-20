@php use App\Support\{Cpf, Dates, Phone}; @endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} – {{ $order->number }}</title>
    <style>
        @page { margin: 28px 32px; }
        * { font-family: "DejaVu Sans", sans-serif; }
        body { font-size: 10.5px; color: #27272a; line-height: 1.4; }
        h1 { font-size: 16px; margin: 0; }
        h2 { font-size: 11px; text-transform: uppercase; letter-spacing: .5px; color: #790b13; margin: 16px 0 6px; border-bottom: 1px solid #eadcc8; padding-bottom: 3px; }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; padding: 3px 4px; }
        .header td { padding: 0; }
        .muted { color: #71717a; }
        .right { text-align: right; }
        .number { font-size: 18px; font-weight: bold; }
        .label { color: #71717a; font-size: 9px; text-transform: uppercase; }
        .box { border: 1px solid #d4d4d8; padding: 6px 8px; }
        .items th { background: #fff0dc; text-align: left; font-size: 9.5px; border-bottom: 1px solid #d4d4d8; }
        .items td { border-bottom: 1px solid #e4e4e7; }
        .items th.right { text-align: right; }
        .totals td { padding: 2px 4px; }
        .totals .grand td { font-size: 13px; font-weight: bold; border-top: 1px solid #a1a1aa; padding-top: 4px; }
        ol { margin: 0; padding-left: 16px; }
        li { margin-bottom: 3px; }
        .signatures { margin-top: 48px; }
        .signatures td { width: 50%; text-align: center; padding: 0 20px; }
        .line { border-top: 1px solid #27272a; padding-top: 4px; }
        .footer { position: fixed; bottom: -10px; left: 0; right: 0; font-size: 8.5px; color: #a1a1aa; text-align: center; }
        /* Identidade da marca: bordô #790B13 e marfim #FFF0DC. */
        .header { border-bottom: 3px solid #790b13; padding-bottom: 8px; }
        .header h1, .header .number { color: #790b13; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td>
                <h1>{{ $shop['name'] }}</h1>
                <div class="muted">
                    @if ($shop['document']) CNPJ {{ $shop['document'] }}<br> @endif
                    @if ($shop['address']) {{ $shop['address'] }}<br> @endif
                    @if ($shop['phone']) {{ $shop['phone'] }} @endif
                </div>
            </td>
            <td class="right">
                <div class="label">{{ $title }}</div>
                <div class="number">{{ $order->number }}</div>
                <div class="muted">Entrada: {{ Dates::dateTime($order->created_at) }}</div>
            </td>
        </tr>
    </table>

    <h2>Cliente e aparelho</h2>
    <table>
        <tr>
            <td style="width: 50%">
                <div class="label">Cliente</div>
                <strong>{{ $order->customer->name }}</strong><br>
                {{ Phone::format($order->customer->phone) }}
                @if ($order->customer->document) · CPF {{ Cpf::format($order->customer->document) }} @endif
            </td>
            <td>
                <div class="label">Aparelho</div>
                <strong>{{ $order->device->description() }}</strong> ({{ $order->device->type->label() }})<br>
                @if ($order->device->color) Cor: {{ $order->device->color }} @endif
                @if ($order->device->serial_or_imei) · Série/IMEI: {{ $order->device->serial_or_imei }} @endif
            </td>
        </tr>
    </table>

    {{ $slot }}

    <div class="footer">
        {{ $shop['name'] }} · {{ $order->number }} · emitido em {{ Dates::dateTime(now()) }}
    </div>
</body>
</html>
