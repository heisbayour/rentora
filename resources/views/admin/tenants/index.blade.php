<x-layouts::admin :title="__('tenants.title')">
    <x-page-header :title="__('tenants.title')" :description="__('tenants.description')">
        @if ($canManage)
            <x-slot:actions>
                <x-button variant="secondary" :href="route('app.tenants.create')">{{ __('tenants.new') }}</x-button>
                <x-button :href="route('app.leases.wizard.start')" icon="plus">{{ __('tenants.new_with_lease') }}</x-button>
            </x-slot:actions>
        @endif
    </x-page-header>

    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
        <form method="GET" class="w-full sm:w-80" data-no-guard>
            @if ($status)
                <input type="hidden" name="status" value="{{ $status->value }}">
            @endif
            <x-input name="q" :value="$search" :placeholder="__('tenants.search')" aria-label="{{ __('tenants.search') }}" />
        </form>
        <div class="flex flex-wrap gap-2">
            <x-filter-chip :href="request()->fullUrlWithQuery(['status' => null, 'page' => null])" :active="$status === null">{{ __('tenants.all_statuses') }}</x-filter-chip>
            @foreach ($statuses as $option)
                <x-filter-chip :href="request()->fullUrlWithQuery(['status' => $option->value, 'page' => null])" :active="$status === $option">{{ $option->label() }}</x-filter-chip>
            @endforeach
        </div>
    </div>

    @if ($tenants->isEmpty())
        @if ($search !== '' || $status)
            <x-empty-state icon="magnifying-glass" :title="__('tenants.empty_search')" />
        @else
            <x-empty-state icon="users" :title="__('tenants.empty_title')" :text="__('tenants.empty_text')"
                :action-label="$canManage ? __('tenants.new_with_lease') : null" :action-href="$canManage ? route('app.leases.wizard.start') : null" />
        @endif
    @else
        <x-table :caption="__('tenants.title')">
            <x-slot:head>
                <th><x-sort-link column="name" :sort="$sort" :direction="$direction">{{ __('tenants.columns.tenant') }}</x-sort-link></th>
                <th>{{ __('tenants.columns.unit') }}</th>
                <th>{{ __('tenants.columns.phone') }}</th>
                <th><x-sort-link column="status" :sort="$sort" :direction="$direction">{{ __('tenants.columns.status') }}</x-sort-link></th>
            </x-slot:head>
            @foreach ($tenants as $tenant)
                <tr>
                    <td data-label="{{ __('tenants.columns.tenant') }}">
                        <a href="{{ route('app.tenants.show', $tenant) }}" class="flex items-center gap-3 text-left">
                            <x-avatar :name="$tenant->full_name" size="sm" />
                            <span>
                                <span class="block font-medium text-ink hover:text-primary-600">{{ $tenant->full_name }}</span>
                                @if ($tenant->email)
                                    <span class="block text-xs text-ink-muted">{{ $tenant->email }}</span>
                                @endif
                            </span>
                        </a>
                    </td>
                    <td data-label="{{ __('tenants.columns.unit') }}">
                        @if ($tenant->activeLease)
                            {{ $tenant->activeLease->unit->label }}
                            <span class="block text-xs text-ink-muted">{{ $tenant->activeLease->unit->property->name }}</span>
                        @else
                            <span class="text-ink-muted">{{ __('tenants.no_unit') }}</span>
                        @endif
                    </td>
                    <td data-label="{{ __('tenants.columns.phone') }}" class="tabular-nums">{{ $tenant->phone }}</td>
                    <td data-label="{{ __('tenants.columns.status') }}"><x-status-chip :status="$tenant->status" /></td>
                </tr>
            @endforeach
        </x-table>
        <x-pagination :paginator="$tenants" class="mt-4" />
    @endif
</x-layouts::admin>
