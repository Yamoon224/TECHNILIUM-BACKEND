<!DOCTYPE html>
<html lang="{{ $t['lang'] }}">
<head>
    <meta charset="utf-8">
    <title>{{ $t['title'] }} BL-{{ sprintf('%06d', $shipment->id) }}</title>
    <style>
        body { font-family: "DejaVu Sans", sans-serif; font-size: 11px; color: #1a1a1a; }
        h1 { font-size: 20px; margin: 0 0 4px 0; }
        .muted { color: #666; }
        .row { width: 100%; }
        .row td { vertical-align: top; }
        .box { border: 1px solid #ccc; padding: 8px; }
        table.lines { width: 100%; border-collapse: collapse; margin-top: 18px; }
        table.lines th { text-align: left; border-bottom: 1px solid #333; padding: 6px 4px; font-size: 10px; }
        table.lines td { border-bottom: 1px solid #ddd; padding: 8px 4px; }
        .num { text-align: right; white-space: nowrap; }
        .sign td { width: 50%; height: 90px; border: 1px solid #999; vertical-align: top; padding: 8px; font-size: 10px; color: #444; }
        .note { margin-top: 16px; font-size: 9px; color: #666; }
    </style>
</head>
<body>
<table class="row">
    <tr>
        <td style="width: 55%">
            <h1>{{ $company['name'] }}</h1>
            @if ($company['address'] !== '') <div>{{ $company['address'] }}</div> @endif
            @if ($company['phone'] !== '') <div>{{ $t['phone_short'] }} {{ $company['phone'] }}</div> @endif
        </td>
        <td style="width: 45%" class="box">
            <strong style="font-size: 14px;">{{ $t['title'] }}</strong><br>
            {{ $t['number'] }} BL-{{ sprintf('%06d', $shipment->id) }}<br>
            {{ $t['date'] }} {{ ($shipment->created_at ?? now())->format('d/m/Y') }}<br>
            {{ $t['order'] }} {{ $shipment->order_number }}
        </td>
    </tr>
</table>

<p style="margin-top: 18px;">
    <span class="muted">{{ $t['deliver_to'] }}</span><br>
    <strong>{{ $shipment->customer_name }}</strong><br>
    {{ $shipment->delivery_address }}@if ($shipment->delivery_city), {{ $shipment->delivery_city }}@endif<br>
    {{ $t['phone'] }} {{ $shipment->customer_phone }}
</p>

<table class="lines">
    <thead>
        <tr>
            <th>{{ $t['items'] }} — {{ $t['item'] }}</th>
            <th class="num">{{ $t['quantity'] }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($lines as $line)
            <tr>
                <td>{{ $line['description'] }}</td>
                <td class="num">{{ $line['quantity'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

@if ($shipment->notes)
    <p><span class="muted">{{ $t['notes'] }} :</span> {{ $shipment->notes }}</p>
@endif

<table class="row sign" style="margin-top: 28px; border-collapse: separate; border-spacing: 8px 0;">
    <tr>
        <td>{{ $t['receipt'] }} — {{ $t['customer_signature'] }}</td>
        <td>{{ $t['courier_signature'] }}</td>
    </tr>
</table>

<div class="note">{{ $t['code_note'] }}</div>
</body>
</html>
