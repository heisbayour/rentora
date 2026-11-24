{{-- Inbox list shared by the console and the portal. Needs $notifications, $filter, $indexRoute, $readRoute, $readAllRoute. --}}
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <div class="flex gap-2">
        <x-filter-chip :href="route($indexRoute)" :active="$filter === 'all'">{{ __('messaging.inbox.filter_all') }}</x-filter-chip>
        <x-filter-chip :href="route($indexRoute, ['filter' => 'unread'])" :active="$filter === 'unread'">{{ __('messaging.inbox.filter_unread') }}</x-filter-chip>
    </div>
    <form method="POST" action="{{ route($readAllRoute) }}">
        @csrf
        <x-button size="sm" variant="secondary" icon="check">{{ __('messaging.inbox.mark_all_read') }}</x-button>
    </form>
</div>

@if ($notifications->isEmpty())
    <x-empty-state icon="bell" :title="__('messaging.inbox.empty_title')" :text="__('messaging.inbox.empty_text')" />
@else
    <ul class="divide-y divide-border overflow-hidden rounded-card border border-border bg-surface shadow-card">
        @foreach ($notifications as $item)
            <li>
                <form method="POST" action="{{ route($readRoute, $item->id) }}">
                    @csrf
                    <button type="submit" class="flex w-full items-start gap-3 px-4 py-3 text-left hover:bg-canvas">
                        <span @class(['mt-1.5 h-2 w-2 shrink-0 rounded-full', 'bg-primary-600' => $item->read_at === null, 'bg-transparent' => $item->read_at !== null]) aria-hidden="true"></span>
                        <span class="min-w-0 flex-1">
                            <span @class(['block text-sm text-ink', 'font-semibold' => $item->read_at === null, 'font-medium' => $item->read_at !== null])>{{ $item->data['title'] ?? '' }}</span>
                            <span class="block text-sm text-ink-muted">{{ $item->data['body'] ?? '' }}</span>
                        </span>
                        <span class="shrink-0 text-xs text-ink-muted">{{ $item->created_at?->diffForHumans() }}</span>
                    </button>
                </form>
            </li>
        @endforeach
    </ul>
    <x-pagination :paginator="$notifications" class="mt-4" />
@endif
