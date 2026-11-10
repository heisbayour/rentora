<x-layouts::admin :title="__('maintenance.title')">
    <x-page-header :title="__('maintenance.title')" :description="__('maintenance.description')">
        <x-slot:actions>
            <div class="inline-flex rounded-control border border-border bg-surface p-0.5">
                <a href="{{ request()->fullUrlWithQuery(['view' => 'table', 'page' => null]) }}" @class(['rounded-control px-3 py-1.5 text-sm font-medium', 'bg-primary-50 text-primary-700' => $view === 'table', 'text-ink-muted' => $view !== 'table'])>{{ __('maintenance.view_table') }}</a>
                <a href="{{ request()->fullUrlWithQuery(['view' => 'kanban', 'page' => null]) }}" @class(['rounded-control px-3 py-1.5 text-sm font-medium', 'bg-primary-50 text-primary-700' => $view === 'kanban', 'text-ink-muted' => $view !== 'kanban'])>{{ __('maintenance.view_kanban') }}</a>
            </div>
            <x-button :href="route('app.maintenance.create')" icon="plus">{{ __('maintenance.new') }}</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
        <form method="GET" class="w-full sm:w-80" data-no-guard>
            @foreach (['view' => $view, 'status' => $status?->value, 'priority' => $priority?->value, 'mine' => $mine ? '1' : null] as $key => $value)
                @if ($value)
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endif
            @endforeach
            <x-input name="q" :value="$search" :placeholder="__('maintenance.search')" aria-label="{{ __('maintenance.search') }}" />
        </form>
        <div class="flex flex-wrap gap-2">
            <x-filter-chip :href="request()->fullUrlWithQuery(['status' => null, 'priority' => null, 'mine' => null, 'page' => null])" :active="! $status && ! $priority && ! $mine">{{ __('maintenance.all') }}</x-filter-chip>
            <x-filter-chip :href="request()->fullUrlWithQuery(['priority' => $priority ? null : 'emergency', 'page' => null])" :active="$priority !== null">{{ __('maintenance.emergency') }}</x-filter-chip>
            <x-filter-chip :href="request()->fullUrlWithQuery(['mine' => $mine ? null : '1', 'page' => null])" :active="$mine">{{ __('maintenance.mine') }}</x-filter-chip>
            @if ($view === 'table')
                @foreach ($statuses as $option)
                    <x-filter-chip :href="request()->fullUrlWithQuery(['status' => $option->value, 'page' => null])" :active="$status === $option">{{ $option->label() }}</x-filter-chip>
                @endforeach
            @endif
        </div>
    </div>

    @if ($view === 'kanban')
        <div class="flex gap-4 overflow-x-auto pb-4">
            @foreach (\App\Enums\MaintenanceStatus::cases() as $column)
                @continue(! $column->isOpen())
                <section class="w-72 shrink-0 rounded-card bg-canvas p-3" aria-label="{{ $column->label() }}">
                    <h2 class="mb-3 flex items-center justify-between text-sm font-semibold text-ink">
                        {{ $column->label() }}
                        <span class="rounded-full bg-surface px-2 text-xs text-ink-muted ring-1 ring-border">{{ $columns->get($column->value)?->count() ?? 0 }}</span>
                    </h2>
                    <div class="space-y-3">
                        @forelse ($columns->get($column->value, collect()) as $item)
                            <a href="{{ route('app.maintenance.show', $item) }}" @class([
                                'block rounded-card border bg-surface p-3 shadow-card hover:border-primary-600',
                                'border-danger' => $item->isEmergency(),
                                'border-border' => ! $item->isEmergency(),
                            ])>
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-mono text-xs text-ink-muted">{{ $item->number }}</span>
                                    <x-status-chip :status="$item->priority" />
                                </div>
                                <p class="mt-1 text-sm font-medium text-ink">{{ $item->title }}</p>
                                <p class="text-xs text-ink-muted">{{ $item->unit->label }}, {{ $item->unit->property->name }}</p>
                                <p class="mt-2 text-xs text-ink-muted">{{ $item->created_at->diffForHumans() }}@if ($item->assignee || $item->vendor) · {{ $item->assignee?->name ?? $item->vendor?->name }}@endif</p>
                            </a>
                        @empty
                            <p class="py-4 text-center text-xs text-ink-muted">{{ __('maintenance.empty_column') }}</p>
                        @endforelse
                    </div>
                </section>
            @endforeach
        </div>
    @elseif ($requests->isEmpty())
        @if ($search !== '' || $status || $priority || $mine)
            <x-empty-state icon="magnifying-glass" :title="__('maintenance.empty_search')" />
        @else
            <x-empty-state icon="wrench-screwdriver" :title="__('maintenance.empty_title')" :text="__('maintenance.empty_text')"
                :action-label="__('maintenance.new')" :action-href="route('app.maintenance.create')" />
        @endif
    @else
        <x-table :caption="__('maintenance.title')">
            <x-slot:head>
                <th>{{ __('maintenance.columns.request') }}</th>
                <th>{{ __('maintenance.columns.unit') }}</th>
                <th>{{ __('maintenance.columns.priority') }}</th>
                <th>{{ __('maintenance.columns.status') }}</th>
                <th>{{ __('maintenance.columns.age') }}</th>
                <th>{{ __('maintenance.columns.assigned') }}</th>
            </x-slot:head>
            @foreach ($requests as $item)
                <tr @class(['bg-danger-bg/40' => $item->isEmergency() && $item->status->isOpen()])>
                    <td data-label="{{ __('maintenance.columns.request') }}">
                        <a href="{{ route('app.maintenance.show', $item) }}" class="font-medium text-ink hover:text-primary-600">{{ $item->title }}</a>
                        <span class="block font-mono text-xs text-ink-muted">{{ $item->number }}</span>
                    </td>
                    <td data-label="{{ __('maintenance.columns.unit') }}">{{ $item->unit->label }}<span class="block text-xs text-ink-muted">{{ $item->unit->property->name }}</span></td>
                    <td data-label="{{ __('maintenance.columns.priority') }}"><x-status-chip :status="$item->priority" /></td>
                    <td data-label="{{ __('maintenance.columns.status') }}"><x-status-chip :status="$item->status" /></td>
                    <td data-label="{{ __('maintenance.columns.age') }}" class="whitespace-nowrap text-ink-muted">{{ $item->created_at->diffForHumans(null, true) }}</td>
                    <td data-label="{{ __('maintenance.columns.assigned') }}">{{ $item->assignee?->name ?? $item->vendor?->name ?? '' }}</td>
                </tr>
            @endforeach
        </x-table>
        <x-pagination :paginator="$requests" class="mt-4" />
    @endif
</x-layouts::admin>
