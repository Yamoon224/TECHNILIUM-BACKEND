<!DOCTYPE html>
<html lang="{{ $t['lang'] }}">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} {{ $invoice->number }}</title>
    <style>
        /* DejaVu Sans ships with dompdf and covers French accents and the euro sign. */
        body { font-family: "DejaVu Sans", sans-serif; font-size: 10px; color: #1a1a1a; }
        h1 { font-size: 20px; margin: 0 0 4px 0; }
        .muted { color: #666; }
        .row { width: 100%; }
        .row td { vertical-align: top; }
        .box { border: 1px solid #ccc; padding: 8px; }
        table.lines { width: 100%; border-collapse: collapse; margin-top: 18px; }
        table.lines th { text-align: left; border-bottom: 1px solid #333; padding: 6px 4px; font-size: 9px; }
        table.lines td { border-bottom: 1px solid #ddd; padding: 6px 4px; }
        .num { text-align: right; white-space: nowrap; }
        table.totals { width: 55%; margin-left: 45%; margin-top: 14px; border-collapse: collapse; }
        table.totals td { padding: 4px; }
        table.totals tr.grand td { border-top: 2px solid #333; font-weight: bold; font-size: 12px; }
        .words { margin-top: 16px; padding: 8px; background: #f4f4f4; }
        .footer { margin-top: 28px; font-size: 8px; color: #666; }
    </style>
</head>
<body>
@php
    $money = fn (int $amount) => \App\Modules\Invoicing\Domain\Money::format($amount, $invoice->currency);
    $isCredit = $invoice->type->value === 'credit_note';
    $footer = str_replace([':issuer', ':number'], [$company['name'], $invoice->number], $isCredit ? $t['footer_credit'] : $t['footer_invoice']);
@endphp

<table class="row">
    <tr>
        <td style="width: 55%">
            <h1>{{ $company['name'] }}</h1>
            @if ($company['address'] !== '') <div>{{ $company['address'] }}</div> @endif
            @if ($company['phone'] !== '') <div>{{ $t['phone'] }} {{ $company['phone'] }}</div> @endif
            @if ($company['email'] !== '') <div>{{ $company['email'] }}</div> @endif
        </td>
        <td style="width: 45%" class="box">
            <strong style="font-size: 14px;">{{ $title }}</strong><br>
            {{ $t['number'] }} {{ $invoice->number }}<br>
            {{ $t['date'] }} {{ $invoice->issued_at->format('d/m/Y') }}<br>
            {{ $t['order'] }} {{ $invoice->order_number }}
            @if ($creditedNumber !== null)
                <br>{{ $t['cancels'] }} {{ $creditedNumber }}
            @endif
        </td>
    </tr>
</table>

<p style="margin-top: 18px;">
    <span class="muted">{{ $t['customer'] }}</span><br>
    <strong>{{ $invoice->customer_name }}</strong>
</p>

@if ($isCredit && $invoice->reason)
    <p><span class="muted">{{ $t['credit_reason'] }}</span> {{ $invoice->reason }}</p>
@endif

<table class="lines">
    <thead>
        <tr>
            <th>{{ $t['description'] }}</th>
            <th class="num">{{ $t['quantity'] }}</th>
            <th class="num">{{ $t['unit_price'] }}</th>
            <th class="num">{{ $t['line_total'] }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($invoice->lines as $line)
            <tr>
                <td>{{ $line->description }}</td>
                <td class="num">{{ $line->quantity }}</td>
                <td class="num">{{ $money($line->unit_price_amount) }}</td>
                <td class="num">{{ $money($line->line_total_amount) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table class="totals">
    @if ($invoice->discount_amount > 0)
        <tr>
            <td>{{ $t['discount'] }}{{ $invoice->coupon_code ? ' ('.$invoice->coupon_code.')' : '' }}</td>
            <td class="num">− {{ $money($invoice->discount_amount) }}</td>
        </tr>
    @endif
    <tr>
        <td>{{ $t['total_net'] }}</td>
        <td class="num">{{ $money($invoice->net_amount) }}</td>
    </tr>
    <tr>
        <td>{{ $t['vat'] }} ({{ rtrim(rtrim(number_format($invoice->vat_basis_points / 100, 2, ',', ''), '0'), ',') }} %)</td>
        <td class="num">{{ $money($invoice->vat_amount) }}</td>
    </tr>
    <tr class="grand">
        <td>{{ $isCredit ? $t['total_gross_credit'] : $t['total_gross_invoice'] }}</td>
        <td class="num">{{ $money($invoice->total_amount) }}</td>
    </tr>
</table>

<div class="words">
    {{ $isCredit ? $t['words_credit'] : $t['words_invoice'] }}
    <strong>{{ $amountInWords }}</strong>.
</div>

<div class="footer">
    @if ($company['legal'] !== '') <div>{{ $company['legal'] }}</div> @endif
    <div>{{ $footer }}</div>
</div>

</body>
</html>
