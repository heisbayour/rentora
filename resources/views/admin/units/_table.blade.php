{{-- Units table shared by the units index and the property page. Expects $units and $showProperty. --}}
<x-table :caption="__('units.title')">
    <x-slot:head>
        <th>
            @isset($sort)
                <x-sort-link column="label" :sort="$sort" :direction="$direction">{{ __('units.columns.unit') }}</x-sort-link>
            @else
                {{ __('units.columns.unit') }}
            @endisset
        </th>
        @if ($showProperty)
            <th>{{ __('units.columns.property') }}</th>
        @endif
        <th>{{ __('units.columns.type') }}</th>
        <th class="text-right">
            @isset($sort)
                <x-sort-link column="rent" :sort="$sort" :direction="$direction">{{ __('units.columns.rent') }}</x-sort-link>
            @else
                {{ __('units.columns.rent') }}
            @endisset
        </th>
        <th>{{ __('units.columns.status') }}</th>
        <th>{{ __('units.columns.listed') }}</th>
    </x-slot:head>
    @foreach ($units as $unit)
        <tr>
            <td data-label="{{ __('units.columns.unit') }}">
                <a href="{{ route('app.units.show', $unit) }}" class="font-medium text-ink hover:text-primary-600">{{ $unit->label }}</a>
            </td>
            @if ($showProperty)
                <td data-label="{{ __('units.columns.property') }}">{{ $unit->property->name }}</td>
            @endif
            <td data-label="{{ __('units.columns.type') }}">{{ $unit->type->label() }}</td>
            <td data-label="{{ __('units.columns.rent') }}" class="text-right tabular-nums">
                {{ $unit->rent()->format() }}
                <span class="block text-xs text-ink-muted">{{ $unit->rent_frequency->label() }}</span>
            </td>
            <td data-label="{{ __('units.columns.status') }}"><x-status-chip :status="$unit->status" /></td>
            <td data-label="{{ __('units.columns.listed') }}">
                <x-status-chip :label="$unit->is_listed ? __('units.listed') : __('units.not_listed')" :color="$unit->is_listed ? 'info' : 'neutral'" />
            </td>
        </tr>
    @endforeach
</x-table>
