<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ __('payments.statement.title') }}</title>
    @include('pdf._styles')
</head>
<body>
    @php
        $money = fn (int $minor) => \App\Support\Money::of($minor, $organization?->currency)->format();
    @endphp
    @include('pdf._header', ['documentTitle' => strtoupper(__('payments.statement.title')), 'documentNumber' => $from->format('j M Y').' to '.$to->format('j M Y')])

    <p><strong>{{ $tenant->full_name }}</strong><br><span class="muted">{{ $tenant->phone }} {{ $tenant->email }}</span></p>

    <table class="items">
        <thead>
            <tr>
                <th>{{ __('payments.statement.date') }}</th>
                <th>{{ __('payments.statement.description') }}</th>
                <th>{{ __('payments.statement.reference') }}</th>
                <th class="right">{{ __('payments.statement.charge') }}</th>
                <th class="right">{{ __('payments.statement.payment') }}</th>
                <th class="right">{{ __('payments.statement.balance') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr><td colspan="5"><strong>{{ __('payments.statement.opening') }}</strong></td><td class="right"><strong>{{ $money($statement['opening']) }}</strong></td></tr>
            @foreach ($statement['lines'] as $line)
                <tr>
                    <td>{{ $line['date']->format('j M Y') }}</td>
                    <td>{{ $line['description'] }}</td>
                    <td>{{ $line['reference'] }}</td>
                    <td class="right">{{ $line['charge'] ? $money($line['charge']) : '' }}</td>
                    <td class="right">{{ $line['payment'] ? $money($line['payment']) : '' }}</td>
                    <td class="right">{{ $money($line['balance']) }}</td>
                </tr>
            @endforeach
            <tr><td colspan="5"><strong>{{ __('payments.statement.closing') }}</strong></td><td class="right"><strong>{{ $money($statement['closing']) }}</strong></td></tr>
        </tbody>
    </table>
    <p class="muted" style="margin-top: 12px;">{{ __('payments.statement.note') }}</p>
</body>
</html>
