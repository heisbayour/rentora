{{-- Payment table shared by the payments index and the tenant profile. Expects $payments and $showTenant. --}}
@if ($payments->isEmpty())
    <x-empty-state icon="banknotes" :title="__('payments.empty_title')" :text="__('payments.empty_text')" />
@else
    <x-table :caption="__('payments.title')">
        <x-slot:head>
            <th>{{ __('payments.columns.reference') }}</th>
            @if ($showTenant)
                <th>{{ __('payments.columns.tenant') }}</th>
            @endif
            <th>{{ __('payments.columns.method') }}</th>
            <th>{{ __('payments.columns.date') }}</th>
            <th class="text-right">{{ __('payments.columns.amount') }}</th>
            <th>{{ __('payments.columns.status') }}</th>
        </x-slot:head>
        @foreach ($payments as $payment)
            <tr>
                <td data-label="{{ __('payments.columns.reference') }}">
                    <a href="{{ route('app.payments.show', $payment) }}" class="font-mono text-xs font-medium text-ink hover:text-primary-600">{{ $payment->receipt_number ?? $payment->reference }}</a>
                </td>
                @if ($showTenant)
                    <td data-label="{{ __('payments.columns.tenant') }}">{{ $payment->tenant->full_name }}</td>
                @endif
                <td data-label="{{ __('payments.columns.method') }}">{{ $payment->method->label() }}</td>
                <td data-label="{{ __('payments.columns.date') }}" class="whitespace-nowrap">{{ ($payment->paid_at ?? $payment->created_at)?->format('j M Y') }}</td>
                <td data-label="{{ __('payments.columns.amount') }}" class="text-right tabular-nums">{{ $payment->money()->format() }}</td>
                <td data-label="{{ __('payments.columns.status') }}"><x-status-chip :status="$payment->status" /></td>
            </tr>
        @endforeach
    </x-table>
@endif
