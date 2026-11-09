{{-- Statement table, shared by the staff and tenant statement pages. --}}
@php
    $money = fn (int $minor) => \App\Support\Money::forOrganization($minor)->format();
@endphp

<div class="mb-6 grid gap-4 sm:grid-cols-4">
    <x-stat-card :label="__('payments.statement.opening')" :value="$money($statement['opening'])" />
    <x-stat-card :label="__('payments.statement.charges')" :value="$money($statement['charges'])" />
    <x-stat-card :label="__('payments.statement.payments')" :value="$money($statement['payments'])" />
    <x-stat-card :label="__('payments.statement.closing')" :value="$money($statement['closing'])" />
</div>

@if ($statement['lines'] === [])
    <x-empty-state icon="document-text" :title="__('payments.statement.empty')" />
@else
    <x-table :caption="__('payments.statement.title')">
        <x-slot:head>
            <th>{{ __('payments.statement.date') }}</th>
            <th>{{ __('payments.statement.description') }}</th>
            <th>{{ __('payments.statement.reference') }}</th>
            <th class="text-right">{{ __('payments.statement.charge') }}</th>
            <th class="text-right">{{ __('payments.statement.payment') }}</th>
            <th class="text-right">{{ __('payments.statement.balance') }}</th>
        </x-slot:head>
        @foreach ($statement['lines'] as $line)
            <tr>
                <td data-label="{{ __('payments.statement.date') }}" class="whitespace-nowrap">{{ $line['date']->format('j M Y') }}</td>
                <td data-label="{{ __('payments.statement.description') }}">{{ $line['description'] }}</td>
                <td data-label="{{ __('payments.statement.reference') }}" class="font-mono text-xs">{{ $line['reference'] }}</td>
                <td data-label="{{ __('payments.statement.charge') }}" class="text-right tabular-nums">{{ $line['charge'] ? $money($line['charge']) : '' }}</td>
                <td data-label="{{ __('payments.statement.payment') }}" class="text-right tabular-nums">{{ $line['payment'] ? $money($line['payment']) : '' }}</td>
                <td data-label="{{ __('payments.statement.balance') }}" class="text-right font-medium tabular-nums">{{ $money($line['balance']) }}</td>
            </tr>
        @endforeach
    </x-table>
@endif
<p class="mt-3 text-xs text-ink-muted">{{ __('payments.statement.note') }}</p>
