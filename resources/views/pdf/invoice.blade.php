@php
    $color = $organization?->primary_color ?? config('rentora.defaults.primary_color');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->number }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #202124; margin: 0; }
        .header { border-bottom: 3px solid {{ $color }}; padding-bottom: 12px; margin-bottom: 20px; }
        .org { font-size: 16px; font-weight: bold; }
        .muted { color: #5F6368; }
        .title { font-size: 20px; font-weight: bold; color: {{ $color }}; text-align: right; }
        table { width: 100%; border-collapse: collapse; }
        .items th { text-align: left; background: #F8F9FA; padding: 8px; border-bottom: 1px solid #E3E6EA; }
        .items td { padding: 8px; border-bottom: 1px solid #E3E6EA; }
        .right { text-align: right; }
        .totals td { padding: 4px 8px; }
        .total { font-size: 13px; font-weight: bold; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 8px; background: #F8F9FA; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td>
                @if ($organization?->logo_path && is_file(public_path('uploads/'.$organization->logo_path)))
                    <img src="{{ public_path('uploads/'.$organization->logo_path) }}" style="height: 40px;" alt="">
                @endif
                <div class="org">{{ $organization?->name }}</div>
                <div class="muted">{{ $organization?->address }}</div>
                <div class="muted">{{ $organization?->phone }} {{ $organization?->email }}</div>
            </td>
            <td class="title">
                {{ __('invoices.pdf.title') }}<br>
                <span style="font-size: 12px; color: #202124;">{{ $invoice->number }}</span><br>
                <span class="badge">{{ $invoice->status->label() }}</span>
            </td>
        </tr>
    </table>

    <table style="margin-bottom: 20px;">
        <tr>
            <td style="width: 50%; vertical-align: top;">
                <div class="muted">{{ __('invoices.pdf.bill_to') }}</div>
                <strong>{{ $invoice->tenant->full_name }}</strong><br>
                @if ($invoice->unit){{ $invoice->unit->label }}, {{ $invoice->unit->property?->name }}<br>@endif
                {{ $invoice->tenant->phone }} {{ $invoice->tenant->email }}
            </td>
            <td style="width: 50%; vertical-align: top;" class="right">
                {{ __('invoices.details.issued') }}: {{ $invoice->issued_at?->format('j M Y') }}<br>
                {{ __('invoices.details.due') }}: <strong>{{ $invoice->due_date->format('j M Y') }}</strong>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead><tr><th>{{ __('invoices.pdf.description') }}</th><th class="right">{{ __('invoices.pdf.amount') }}</th></tr></thead>
        <tbody><tr><td>{{ $invoice->type->label() }}: {{ $invoice->description }}</td><td class="right">{{ $invoice->total()->format() }}</td></tr></tbody>
    </table>

    <table class="totals" style="width: 45%; margin-left: 55%; margin-top: 12px;">
        <tr><td>{{ __('invoices.details.total') }}</td><td class="right">{{ $invoice->total()->format() }}</td></tr>
        <tr><td>{{ __('invoices.details.paid') }}</td><td class="right">{{ $invoice->paid()->format() }}</td></tr>
        <tr class="total"><td>{{ __('invoices.details.balance') }}</td><td class="right">{{ $invoice->outstanding()->format() }}</td></tr>
    </table>

    <p class="muted" style="margin-top: 30px;">{{ __('invoices.pdf.thank_you') }}</p>
</body>
</html>
