{{-- Lease table shared by the leases index and the tenant profile. Expects $leases and $showTenant. --}}
@if ($leases->isEmpty())
    <x-empty-state icon="document-text" :title="__('leases.empty_title')" :text="__('leases.empty_text')" />
@else
    <x-table :caption="__('leases.title')">
        <x-slot:head>
            @if ($showTenant)
                <th>{{ __('leases.columns.tenant') }}</th>
            @endif
            <th>{{ __('leases.columns.unit') }}</th>
            <th>
                @isset($sort)
                    <x-sort-link column="end" :sort="$sort" :direction="$direction">{{ __('leases.columns.period') }}</x-sort-link>
                @else
                    {{ __('leases.columns.period') }}
                @endisset
            </th>
            <th class="text-right">{{ __('leases.columns.rent') }}</th>
            <th>{{ __('leases.columns.status') }}</th>
        </x-slot:head>
        @foreach ($leases as $lease)
            <tr>
                @if ($showTenant)
                    <td data-label="{{ __('leases.columns.tenant') }}">
                        <a href="{{ route('app.leases.show', $lease) }}" class="font-medium text-ink hover:text-primary-600">{{ $lease->tenant->full_name }}</a>
                    </td>
                @endif
                <td data-label="{{ __('leases.columns.unit') }}">
                    @unless ($showTenant)
                        <a href="{{ route('app.leases.show', $lease) }}" class="font-medium text-ink hover:text-primary-600">{{ $lease->unit->label }}</a>
                    @else
                        {{ $lease->unit->label }}
                    @endunless
                    <span class="block text-xs text-ink-muted">{{ $lease->unit->property?->name }}</span>
                </td>
                <td data-label="{{ __('leases.columns.period') }}" class="whitespace-nowrap">
                    {{ $lease->start_date->format('j M Y') }} to {{ $lease->end_date->format('j M Y') }}
                </td>
                <td data-label="{{ __('leases.columns.rent') }}" class="text-right tabular-nums">
                    {{ $lease->rent()->format() }}
                    <span class="block text-xs text-ink-muted">{{ $lease->rent_frequency->label() }}</span>
                </td>
                <td data-label="{{ __('leases.columns.status') }}"><x-status-chip :status="$lease->status" /></td>
            </tr>
        @endforeach
    </x-table>
@endif
