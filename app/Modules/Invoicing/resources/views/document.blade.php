<!DOCTYPE html>
<html lang="fr">
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
@endphp

<table class="row">
    <tr>
        <td style="width: 55%">
            <h1>{{ $company['name'] }}</h1>
            @if ($company['address'] !== '') <div>{{ $company['address'] }}</div> @endif
            @if ($company['phone'] !== '') <div>Tél. : {{ $company['phone'] }}</div> @endif
            @if ($company['email'] !== '') <div>{{ $company['email'] }}</div> @endif
        </td>
        <td style="width: 45%" class="box">
            <strong style="font-size: 14px;">{{ $title }}</strong><br>
            N° {{ $invoice->number }}<br>
            Date : {{ $invoice->issued_at->format('d/m/Y') }}<br>
            Commande : {{ $invoice->order_number }}
            @if ($creditedNumber !== null)
                <br>Annule la facture : {{ $creditedNumber }}
            @endif
        </td>
    </tr>
</table>

<p style="margin-top: 18px;">
    <span class="muted">Client</span><br>
    <strong>{{ $invoice->customer_name }}</strong>
</p>

@if ($isCredit && $invoice->reason)
    <p><span class="muted">Motif de l'avoir :</span> {{ $invoice->reason }}</p>
@endif

<table class="lines">
    <thead>
        <tr>
            <th>Désignation</th>
            <th class="num">Qté</th>
            <th class="num">Prix unitaire TTC</th>
            <th class="num">Total TTC</th>
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
            <td>Remise{{ $invoice->coupon_code ? ' ('.$invoice->coupon_code.')' : '' }}</td>
            <td class="num">− {{ $money($invoice->discount_amount) }}</td>
        </tr>
    @endif
    <tr>
        <td>Total HT</td>
        <td class="num">{{ $money($invoice->net_amount) }}</td>
    </tr>
    <tr>
        <td>TVA ({{ rtrim(rtrim(number_format($invoice->vat_basis_points / 100, 2, ',', ''), '0'), ',') }} %)</td>
        <td class="num">{{ $money($invoice->vat_amount) }}</td>
    </tr>
    <tr class="grand">
        <td>{{ $isCredit ? 'Total de l\'avoir TTC' : 'Total TTC' }}</td>
        <td class="num">{{ $money($invoice->total_amount) }}</td>
    </tr>
</table>

<div class="words">
    Arrêté {{ $isCredit ? 'le présent avoir' : 'la présente facture' }} à la somme de
    <strong>{{ $amountInWords }}</strong>.
</div>

<div class="footer">
    @if ($company['legal'] !== '') <div>{{ $company['legal'] }}</div> @endif
    <div>Document {{ $isCredit ? 'd\'avoir' : 'de facturation' }} émis par {{ $company['name'] }} — n° {{ $invoice->number }}. Un document émis ne se modifie pas : toute correction passe par un avoir.</div>
</div>

</body>
</html>
