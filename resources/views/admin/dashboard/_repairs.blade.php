<x-card :title="__('dashboard.lists.new_repairs')">
    @forelse ($repairs as $repair)
        <a href="{{ route('app.maintenance.show', $repair) }}" class="flex items-center justify-between gap-3 border-b border-border py-3 text-sm last:border-0 hover:text-primary-600">
            <span class="min-w-0"><span class="block truncate font-medium">{{ $repair->title }}</span><span class="text-xs text-ink-muted">{{ $repair->number }} · {{ $repair->unit->displayName() }}</span></span>
            <x-status-chip :status="$repair->status" />
        </a>
    @empty
        <p class="text-sm text-ink-muted">{{ __('dashboard.lists.no_repairs') }}</p>
    @endforelse
</x-card>
