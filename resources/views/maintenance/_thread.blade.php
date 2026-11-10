{{-- Request timeline, shared by staff, portal and vendor pages. Expects $updates (already filtered for the viewer). --}}
<x-timeline>
    @foreach ($updates as $update)
        @php
            $color = $update->is_internal ? 'warning' : ($update->type === \App\Models\MaintenanceUpdate::STATUS_CHANGE ? ($update->new_status?->color() ?? 'neutral') : 'neutral');
            $title = $update->type === \App\Models\MaintenanceUpdate::STATUS_CHANGE && $update->new_status
                ? $update->new_status->label()
                : $update->authorName();
        @endphp
        <x-timeline.item :title="$title" :time="$update->created_at?->diffForHumans()" :color="$color">
            @if ($update->type === \App\Models\MaintenanceUpdate::STATUS_CHANGE)
                <span class="text-xs">{{ $update->authorName() }}</span>
            @endif
            @if ($update->is_internal)
                <x-status-chip :label="__('maintenance.details.internal')" color="warning" class="mb-1" />
            @endif
            @if ($update->body)
                <p class="whitespace-pre-line text-ink">{{ $update->body }}</p>
            @endif
        </x-timeline.item>
    @endforeach
</x-timeline>
