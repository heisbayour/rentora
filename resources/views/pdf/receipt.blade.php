<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $payment->receipt_number }}</title>
    @include('pdf._styles')
</head>
<body>
    @include('pdf._header', ['documentTitle' => __('payments.receipt.title'), 'documentNumber' => $payment->receipt_number])

    <table style="margin-bottom: 18px;">
        <tr>
            <td style="width: 60%; vertical-align: top;">
                <div class="muted">{{ __('payments.receipt.received_from') }}</div>
                <strong>{{ $payment->tenant->full_name }}</strong><br>
                @if ($payment->lease?->unit){{ $payment->lease->unit->label }}, {{ $payment->lease->unit->property?->name }}<br>@endif
                <span class="muted">{{ __('payments.details.paid_at') }}: {{ $payment->paid_at?->format('j M Y') }}</span><br>
                <span class="muted">{{ __('payments.receipt.method') }}: {{ $payment->method->label() }}</span><br>
                <span class="muted">{{ __('payments.details.reference') }}: {{ $payment->reference }}</span>
            </td>
            <td style="width: 40%; vertical-align: top;" class="right">
                <div class="big">{{ $payment->money()->format() }}</div>
            </td>
        </tr>
    </table>

    <p><span class="muted">{{ __('payments.receipt.amount_words') }}:</span> {{ $amountInWords }}</p>

    @if ($payment->allocations->isNotEmpty())
        <table class="items" style="margin-top: 12px;">
            <thead><tr><th>{{ __('payments.receipt.allocations') }}</th><th class="right">{{ __('invoices.pdf.amount') }}</th></tr></thead>
            <tbody>
                @foreach ($payment->allocations as $allocation)
                    <tr><td>{{ $allocation->invoice->number }}: {{ $allocation->invoice->description }}</td><td class="right">{{ \App\Support\Money::of($allocation->amount, $organization?->currency)->format() }}</td></tr>
                @endforeach
                @if ($payment->amount > $payment->allocations->sum('amount'))
                    <tr><td>{{ __('payments.receipt.credit') }}</td><td class="right">{{ \App\Support\Money::of($payment->amount - $payment->allocations->sum('amount'), $organization?->currency)->format() }}</td></tr>
                @endif
            </tbody>
        </table>
    @endif

    <table style="margin-top: 28px;">
        <tr>
            <td style="width: 110px;"><img src="{{ $qr }}" style="width: 100px; height: 100px;" alt=""></td>
            <td class="muted" style="vertical-align: middle;">{{ __('payments.receipt.verify') }}<br>{{ $verifyUrl }}</td>
        </tr>
    </table>
</body>
</html>
