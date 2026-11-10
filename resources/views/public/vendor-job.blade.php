<x-layouts::base :title="__('maintenance.vendor.title', ['number' => $job->number])" body-class="bg-canvas">
    <main id="main" class="mx-auto max-w-portal px-4 py-8">
        <p class="text-sm font-medium text-ink-muted">{{ $job->organization?->name }}</p>
        <h1 class="mt-1 text-xl font-semibold text-ink">{{ __('maintenance.vendor.title', ['number' => $job->number]) }}</h1>
        <p class="mt-1 text-sm text-ink-muted">{{ __('maintenance.vendor.intro', ['name' => $vendor->name]) }}</p>

        <x-card class="mt-6">
            <div class="flex items-start justify-between gap-3">
                <h2 class="font-semibold text-ink">{{ $job->title }}</h2>
                <x-status-chip :status="$job->status" />
            </div>
            <div class="mt-2 flex gap-2"><x-status-chip :status="$job->priority" /><x-status-chip :status="$job->category" /></div>
            <p class="mt-4 whitespace-pre-line text-sm text-ink">{{ $job->description }}</p>
            <dl class="mt-4 space-y-2 text-sm">
                <div><dt class="text-ink-muted">{{ __('maintenance.vendor.location') }}</dt><dd>{{ $job->unit->label }}, {{ $job->unit->property->fullAddress() }}</dd></div>
                @if ($job->access_instructions)
                    <div><dt class="text-ink-muted">{{ __('maintenance.details.access') }}</dt><dd>{{ $job->access_instructions }}</dd></div>
                @endif
                @if ($job->preferred_time)
                    <div><dt class="text-ink-muted">{{ __('maintenance.details.preferred') }}</dt><dd>{{ $job->preferred_time }}</dd></div>
                @endif
                @if ($showTenantContact && $job->tenant)
                    <div><dt class="text-ink-muted">{{ __('maintenance.vendor.tenant_contact') }}</dt><dd>{{ $job->tenant->full_name }} · <a href="tel:{{ $job->tenant->phone }}" class="tabular-nums text-primary-600">{{ $job->tenant->phone }}</a></dd></div>
                @endif
            </dl>
            @if ($job->attachments->isNotEmpty())
                <ul class="mt-4 grid grid-cols-3 gap-2">
                    @foreach ($job->attachments as $photo)
                        @php($src = URL::temporarySignedRoute('vendor.job.photo', now()->addHour(), ['job' => $job->id, 'attachment' => $photo->id, 'vendor' => $vendor->id]))
                        <li><img src="{{ $src }}" alt="" class="h-20 w-full rounded-control object-cover" loading="lazy"></li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        @if ($canUpdate)
            <x-card :title="__('maintenance.vendor.update')" class="mt-6">
                <form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <x-select name="status" :label="__('maintenance.fields.status')" :placeholder="__('maintenance.vendor.no_change')"
                        :options="array_filter([
                            'in_progress' => $job->status->canMoveTo(\App\Enums\MaintenanceStatus::InProgress) ? __('maintenance.vendor.mark_in_progress') : null,
                            'resolved' => $job->status->canMoveTo(\App\Enums\MaintenanceStatus::Resolved) ? __('maintenance.vendor.mark_resolved') : null,
                        ])" />
                    <x-textarea name="body" :label="__('maintenance.fields.comment')" rows="3" optional />
                    <x-file-dropzone name="photos[]" multiple :max="5" accept="image/jpeg,image/png,image/webp" />
                    <x-button>{{ __('maintenance.actions.comment') }}</x-button>
                </form>
            </x-card>
        @else
            <x-alert class="mt-6">{{ __('maintenance.vendor.closed', ['status' => $job->status->label()]) }}</x-alert>
        @endif

        <x-card :title="__('maintenance.details.thread')" class="mt-6">
            @include('maintenance._thread', ['updates' => $updates])
        </x-card>
    </main>
</x-layouts::base>
