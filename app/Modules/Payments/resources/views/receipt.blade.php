<!DOCTYPE html>
<html lang="{{ $t['lang'] }}">
<head>
    <meta charset="utf-8">
    <title>{{ $t['title'] }} {{ $number }}</title>
    <style>
        body { font-family: "DejaVu Sans", sans-serif; font-size: 11px; color: #1a1a1a; }
        h1 { font-size: 18px; margin: 0 0 4px 0; }
        .muted { color: #666; }
        .row { width: 100%; }
        .row td { vertical-align: top; }
        .box { border: 1px solid #ccc; padding: 8px; }
        table.rows { width: 100%; border-collapse: collapse; margin-top: 14px; }
        table.rows td { border-bottom: 1px solid #ddd; padding: 6px 4px; }
        table.rows td.value { text-align: right; font-weight: bold; }
        .big { font-size: 16px; }
        .sign { margin-top: 22px; text-align: right; font-size: 10px; color: #444; }
        .footer { margin-top: 14px; font-size: 8px; color: #666; }
    </style>
</head>
<body>
<table class="row">
    <tr>
        <td style="width: 55%">
            <h1>{{ $company['name'] }}</h1>
            @if ($company['address'] !== '') <div>{{ $company['address'] }}</div> @endif
            @if ($company['phone'] !== '') <div>{{ $t['phone'] }} {{ $company['phone'] }}</div> @endif
        </td>
        <td style="width: 45%" class="box">
            <strong style="font-size: 14px;">{{ $t['title'] }}</strong><br>
            {{ $t['number'] }} {{ $number }}<br>
            {{ $t['date'] }} {{ ($payment->paid_at ?? $payment->created_at ?? now())->format('d/m/Y') }}<br>
            {{ $t['order'] }} {{ $payment->order_number }}
        </td>
    </tr>
</table>

@if ($customerName !== '')
    <p style="margin-top: 14px;"><span class="muted">{{ $t['received_from'] }}</span><br><strong>{{ $customerName }}</strong></p>
@endif

<table class="rows">
    <tr><td>{{ $t['method'] }}</td><td class="value">{{ $methodLabel }}@if ($provider) — {{ $provider }}@endif</td></tr>
    @if ($payment->provider_reference)
        <tr><td>{{ $t['reference'] }}</td><td class="value">{{ $payment->provider_reference }}</td></tr>
    @endif
    <tr><td class="big">{{ $t['amount'] }}</td><td class="value big">{{ $amount }}</td></tr>
    @if ($refunded)
        <tr><td>{{ $t['refunded'] }}</td><td class="value">− {{ $refunded }}</td></tr>
        <tr><td>{{ $t['net'] }}</td><td class="value">{{ $net }}</td></tr>
    @endif
</table>

<div class="sign">{{ $t['signature'] }}<br><br><br>{{ $company['name'] }}</div>
<div class="footer">{{ $t['footer'] }}</div>
</body>
</html>
