<x-layouts::admin :title="__('audit.title')">
    <x-page-header :title="__('audit.title')" :description="__('audit.description')" />

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3" data-no-guard>
        <x-select name="action" :label="__('audit.fields.action')" :options="$actions" :value="$filters['action'] ?? null" :placeholder="__('audit.all')" class="w-full sm:w-56" />
        <x-select name="user_id" :label="__('audit.fields.user')" :options="$users" :value="$filters['user_id'] ?? null" :placeholder="__('audit.all')" class="w-full sm:w-48" />
        <x-date-input name="from" :label="__('reports.filters.from')" :value="$filters['from'] ?? null" />
        <x-date-input name="to" :label="__('reports.filters.to')" :value="$filters['to'] ?? null" />
        <x-button variant="secondary" icon="funnel">{{ __('reports.filters.apply') }}</x-button>
    </form>

    @if ($logs->isEmpty())
        <x-empty-state icon="shield-check" :title="__('audit.empty_title')" :text="__('audit.empty_text')" />
    @else
        <x-table :caption="__('audit.title')">
            <x-slot:head>
                <th>{{ __('audit.fields.when') }}</th>
                <th>{{ __('audit.fields.user') }}</th>
                <th>{{ __('audit.fields.action') }}</th>
                <th>{{ __('audit.fields.subject') }}</th>
                <th>{{ __('audit.fields.details') }}</th>
                <th>{{ __('audit.fields.ip') }}</th>
            </x-slot:head>
            @foreach ($logs as $log)
                <tr>
                    <td data-label="{{ __('audit.fields.when') }}" class="whitespace-nowrap">{{ $log->created_at?->timezone(config('rentora.defaults.timezone'))->format('j M Y H:i') }}</td>
                    <td data-label="{{ __('audit.fields.user') }}">{{ $log->user?->name ?? __('audit.system') }}</td>
                    <td data-label="{{ __('audit.fields.action') }}"><code class="text-xs">{{ $log->action }}</code></td>
                    <td data-label="{{ __('audit.fields.subject') }}">{{ $log->subject_type ? $log->subject_type.' #'.$log->subject_id : '-' }}</td>
                    <td data-label="{{ __('audit.fields.details') }}" class="max-w-xs truncate text-xs text-ink-muted">{{ $log->properties ? json_encode($log->properties, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '' }}</td>
                    <td data-label="{{ __('audit.fields.ip') }}" class="text-xs tabular-nums">{{ $log->ip }}</td>
                </tr>
            @endforeach
        </x-table>
        <x-pagination :paginator="$logs" class="mt-4" />
    @endif
</x-layouts::admin>
