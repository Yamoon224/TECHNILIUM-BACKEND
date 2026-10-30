<!DOCTYPE html>
<html lang="{{ $t['lang'] }}">
<head>
    <meta charset="utf-8">
    <title>{{ $t['title'] }} {{ $number }}</title>
    <style>
        body { font-family: "DejaVu Sans", sans-serif; font-size: 10px; color: #1a1a1a; }
        h1 { font-size: 16px; margin: 0 0 4px 0; }
        .muted { color: #666; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        td, th { border-bottom: 1px solid #ddd; padding: 4px; text-align: left; }
        .num { text-align: right; white-space: nowrap; }
        .big td { font-size: 12px; font-weight: bold; }
        .sign { margin-top: 28px; text-align: right; font-size: 9px; }
    </style>
</head>
<body>
@if ($logo !== '') <img src="{{ $logo }}" alt="{{ $company['name'] }}" style="height: 40px;"><br> @endif
<h1>{{ $company['name'] }} - {{ $t['title'] }}</h1>
<div class="muted">
    {{ $t['number'] }} {{ $number }} · {{ $t['closed'] }} {{ $closing->closed_at->format('d/m/Y H:i') }}<br>
    {{ $t['cashier'] }} : {{ $cashier }}
    @if ($closing->period_start) · {{ $t['period'] }} {{ $closing->period_start->format('d/m/Y H:i') }} @endif
</div>

<table>
    <tr><td>{{ $t['sales'] }}</td><td class="num">{{ $closing->sales_count }}</td></tr>
    <tr><td>{{ $t['cancellations'] }}</td><td class="num">{{ $closing->cancellations_count }}</td></tr>
    <tr><td>{{ $t['cash_sales'] }}</td><td class="num">{{ $cashSales }}</td></tr>
    <tr><td>{{ $t['cash_refunds'] }}</td><td class="num">− {{ $cashRefunds }}</td></tr>
    <tr class="big"><td>{{ $t['expected'] }}</td><td class="num">{{ $expected }}</td></tr>
    <tr class="big"><td>{{ $t['counted'] }}</td><td class="num">{{ $counted }}</td></tr>
    <tr class="big"><td>{{ $t['variance'] }}</td><td class="num">{{ $variance }}</td></tr>
    <tr><td>{{ $t['mobile'] }}</td><td class="num">{{ $mobile }}</td></tr>
</table>

@if ($closing->note)
    <p><span class="muted">{{ $t['note'] }} :</span> {{ $closing->note }}</p>
@endif

@if (count($rows) > 0)
    <table>
        <tr><th>{{ $t['order'] }}</th><th>{{ $t['method'] }}</th><th class="num">{{ $t['amount'] }}</th></tr>
        @foreach ($rows as $row)
            <tr>
                <td>{{ $row['order'] }}@if ($row['cancelled']) ({{ $t['cancelled'] }})@endif</td>
                <td>{{ $row['method'] }}</td>
                <td class="num">@if ($row['cancelled']) − @endif{{ $row['amount'] }}</td>
            </tr>
        @endforeach
    </table>
@endif

<div class="sign">{{ $t['signature'] }}<br><br><br>{{ $cashier }}</div>
</body>
</html>
