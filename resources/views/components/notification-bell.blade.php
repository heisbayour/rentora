<x-dropdown width="w-80" :label="__('ui.notifications')">
    <x-slot:trigger class="relative p-2 text-ink-muted hover:bg-canvas">
        <x-heroicon-o-bell class="h-6 w-6" aria-hidden="true" />
        @if ($unread > 0)
            <span class="absolute right-1 top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-danger px-1 text-[10px] font-semibold text-white" data-testid="unread-count">
                {{ $unread > 99 ? '99+' : $unread }}
            </span>
            <span class="sr-only">{{ __('messaging.inbox.unread', ['count' => $unread]) }}</span>
        @endif
    </x-slot:trigger>
    <div class="flex items-center justify-between border-b border-border px-4 py-2">
        <span class="text-sm font-semibold text-ink">{{ __('ui.notifications') }}</span>
        @if ($unread > 0)
            <form method="POST" action="{{ route($readAllRoute) }}">
                @csrf
                <button type="submit" class="text-xs font-medium text-primary-600 hover:underline">{{ __('messaging.inbox.mark_all_read') }}</button>
            </form>
        @endif
    </div>
    @forelse ($latest as $item)
        <form method="POST" action="{{ route($readRoute, $item->id) }}">
            @csrf
            <button type="submit" role="menuitem" class="block w-full px-4 py-2 text-left hover:bg-canvas focus:bg-canvas focus:outline-none">
                <span class="flex items-start gap-2">
                    @if ($item->read_at === null)
                        <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-primary-600" aria-hidden="true"></span>
                    @endif
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-medium text-ink">{{ $item->data['title'] ?? '' }}</span>
                        <span class="block truncate text-xs text-ink-muted">{{ $item->data['body'] ?? '' }}</span>
                        <span class="block text-xs text-ink-muted">{{ $item->created_at?->diffForHumans() }}</span>
                    </span>
                </span>
            </button>
        </form>
    @empty
        <p class="px-4 py-6 text-center text-sm text-ink-muted">{{ __('ui.no_notifications') }}</p>
    @endforelse
    <a href="{{ route($indexRoute) }}" class="block border-t border-border px-4 py-2 text-center text-sm font-medium text-primary-600 hover:bg-canvas">{{ __('messaging.inbox.view_all') }}</a>
</x-dropdown>
