{{-- Invoice table shared by the invoices index and the tenant profile. Expects $invoices and $showTenant. --}}
@if ($invoices->isEmpty())
    <x-empty-state icon="document-currency-dollar" :title="__('invoices.empty_title')" :text="__('invoices.empty_text')" />
@else
    <x-table :caption="__('invoices.title')">
        <x-slot:head>
            <th>{{ __('invoices.columns.number') }}</th>
            @if ($showTenant)
                <th>{{ __('invoices.columns.tenant') }}</th>
            @endif
            <th>{{ __('invoices.columns.type') }}</th>
            <th>{{ __('invoices.columns.due') }}</th>
            <th class="text-right">{{ __('invoices.columns.amount') }}</th>
            <th class="text-right">{{ __('invoices.columns.balance') }}</th>
            <th>{{ __('invoices.columns.status') }}</th>
        </x-slot:head>
        @foreach ($invoices as $invoice)
            <tr>
                <td data-label="{{ __('invoices.columns.number') }}">
                    <a href="{{ route('app.invoices.show', $invoice) }}" class="font-mono text-xs font-medium text-ink hover:text-primary-600">{{ $invoice->number }}</a>
                </td>
                @if ($showTenant)
                    <td data-label="{{ __('invoices.columns.tenant') }}">{{ $invoice->tenant->full_name }}
                        @if ($invoice->unit)<span class="block text-xs text-ink-muted">{{ $invoice->unit->label }}</span>@endif
                    </td>
                @endif
                <td data-label="{{ __('invoices.columns.type') }}">{{ $invoice->type->label() }}</td>
                <td data-label="{{ __('invoices.columns.due') }}" @class(['whitespace-nowrap', 'font-medium text-danger' => $invoice->isOverdue()])>{{ $invoice->due_date->format('j M Y') }}</td>
                <td data-label="{{ __('invoices.columns.amount') }}" class="text-right tabular-nums">{{ $invoice->total()->format() }}</td>
                <td data-label="{{ __('invoices.columns.balance') }}" class="text-right tabular-nums">{{ $invoice->outstanding()->format() }}</td>
                <td data-label="{{ __('invoices.columns.status') }}"><x-status-chip :status="$invoice->status" /></td>
            </tr>
        @endforeach
    </x-table>
@endif
