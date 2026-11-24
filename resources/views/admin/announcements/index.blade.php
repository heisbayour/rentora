<x-layouts::admin :title="__('announcements.title')">
    <x-page-header :title="__('announcements.title')" :description="__('announcements.description')">
        <x-slot:actions>
            <x-button :href="route('app.announcements.create')" icon="plus">{{ __('announcements.new') }}</x-button>
        </x-slot:actions>
    </x-page-header>

    @if ($announcements->isEmpty())
        <x-empty-state icon="megaphone" :title="__('announcements.empty_title')" :text="__('announcements.empty_text')"
            :action-label="__('announcements.new')" :action-href="route('app.announcements.create')" />
    @else
        <x-table :caption="__('announcements.title')">
            <x-slot:head>
                <th>{{ __('announcements.columns.title') }}</th>
                <th>{{ __('announcements.columns.audience') }}</th>
                <th>{{ __('announcements.columns.channels') }}</th>
                <th class="text-right">{{ __('announcements.columns.recipients') }}</th>
                <th>{{ __('announcements.columns.sent') }}</th>
            </x-slot:head>
            @foreach ($announcements as $item)
                <tr>
                    <td data-label="{{ __('announcements.columns.title') }}">
                        <span class="font-medium">{{ $item->title }}</span>
                        <span class="block max-w-md truncate text-xs text-ink-muted">{{ $item->body }}</span>
                    </td>
                    <td data-label="{{ __('announcements.columns.audience') }}">{{ $item->audience->label() }}</td>
                    <td data-label="{{ __('announcements.columns.channels') }}">{{ collect($item->channels)->map(fn ($c) => __('announcements.channels.'.$c))->join(', ') }}</td>
                    <td data-label="{{ __('announcements.columns.recipients') }}" class="text-right tabular-nums">{{ $item->recipient_count }}</td>
                    <td data-label="{{ __('announcements.columns.sent') }}">{{ $item->sent_at?->timezone(config('rentora.defaults.timezone'))->format('j M Y H:i') }}<span class="block text-xs text-ink-muted">{{ $item->author?->name }}</span></td>
                </tr>
            @endforeach
        </x-table>
        <x-pagination :paginator="$announcements" class="mt-4" />
    @endif
</x-layouts::admin>
