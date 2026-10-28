<!DOCTYPE html>
<html lang="{{ $t['lang'] }}">
<head>
    <meta charset="utf-8">
    <title>{{ $t['title'] }} {{ $number }}</title>
    <style>
        @page { margin: 8px 10px; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 9px; color: #000; }
        .center { text-align: center; }
        .right { text-align: right; }
        .rule { border-top: 1px dashed #000; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; padding: 1px 0; }
        .big { font-size: 12px; font-weight: bold; }
    </style>
</head>
<body>
<div class="center">
    @if ($logo !== '') <img src="{{ $logo }}" alt="{{ $company['name'] }}" style="height: 36px;"><br> @endif
    <strong style="font-size: 12px;">{{ $company['name'] }}</strong><br>
    @if ($company['address'] !== '') {{ $company['address'] }}<br> @endif
    @if ($company['phone'] !== '') {{ $t['phone'] }} {{ $company['phone'] }}<br> @endif
</div>

<div class="rule"></div>
<div class="center"><strong>{{ $t['title'] }}</strong></div>
{{ $t['number'] }} {{ $number }}<br>
{{ $t['date'] }} {{ ($payment->paid_at ?? $payment->created_at ?? now())->format('d/m/Y H:i') }}<br>
{{ $t['order'] }} {{ $payment->order_number }}
@if ($customerName !== '')<br>{{ $t['received_from'] }} {{ $customerName }}@endif

@if (count($lines) > 0)
    <div class="rule"></div>
    <table>
        @foreach ($lines as $line)
            <tr><td colspan="2">{{ $line['description'] }}</td></tr>
            <tr><td>{{ $line['quantity'] }} x {{ $line['unit'] }}</td><td class="right">{{ $line['total'] }}</td></tr>
        @endforeach
    </table>
@endif

<div class="rule"></div>
<table>
    <tr><td class="big">{{ $t['amount'] }}</td><td class="right big">{{ $amount }}</td></tr>
    <tr><td>{{ $t['method'] }}</td><td class="right">{{ $methodLabel }}@if ($provider) — {{ $provider }}@endif</td></tr>
    @if ($payment->provider_reference)
        <tr><td>{{ $t['reference'] }}</td><td class="right">{{ $payment->provider_reference }}</td></tr>
    @endif
    @if ($tendered)
        <tr><td>{{ $t['tendered'] }}</td><td class="right">{{ $tendered }}</td></tr>
        <tr><td>{{ $t['change'] }}</td><td class="right">{{ $change }}</td></tr>
    @endif
    @if ($refunded)
        <tr><td>{{ $t['refunded'] }}</td><td class="right">− {{ $refunded }}</td></tr>
        <tr><td>{{ $t['net'] }}</td><td class="right">{{ $net }}</td></tr>
    @endif
</table>

<div class="rule"></div>
<div class="center">{{ $t['thanks'] }}</div>
<div class="center" style="font-size: 7px; margin-top: 4px;">{{ $t['footer'] }}</div>
</body>
</html>
