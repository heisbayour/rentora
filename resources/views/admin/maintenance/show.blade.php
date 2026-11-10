<x-layouts::admin :title="$request->number">
    <x-page-header :title="$request->title" :description="$request->number.' · '.$request->unit->label.', '.$request->unit->property->name"
        :breadcrumbs="[__('maintenance.title') => route('app.maintenance.index'), $request->number => null]" />

    @foreach (['status', 'photos'] as $field)
        @error($field)
            <x-alert type="danger" class="mb-4">{{ $message }}</x-alert>
        @enderror
    @endforeach

    @if ($request->isEmergency() && $request->status->isOpen())
        <x-alert type="danger" :title="__('enums.maintenance_priority.emergency')" class="mb-4">{{ $request->description }}</x-alert>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card>
                <div class="mb-4 flex flex-wrap items-center gap-2">
                    <x-status-chip :status="$request->status" />
                    <x-status-chip :status="$request->priority" />
                    <x-status-chip :status="$request->category" />
                </div>
                <p class="whitespace-pre-line text-sm text-ink">{{ $request->description }}</p>
                <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-2">
                    <div><dt class="text-ink-muted">{{ __('maintenance.details.reported_by') }}</dt><dd>{{ $request->reporter?->name ?? $request->tenant?->full_name }} · {{ __('maintenance.details.reported', ['when' => $request->created_at->diffForHumans()]) }}</dd></div>
                    <div>
                        <dt class="text-ink-muted">{{ __('maintenance.details.first_response') }}</dt>
                        <dd>
                            @if ($request->first_response_at)
                                {{ $request->first_response_at->format('j M Y, H:i') }} <span class="text-ink-muted">{{ __('maintenance.details.first_response_after', ['duration' => $request->created_at->diffForHumans($request->first_response_at, true)]) }}</span>
                            @else
                                <span class="font-medium text-warning">{{ __('maintenance.details.waiting', ['duration' => $request->created_at->diffForHumans(null, true)]) }}</span>
                            @endif
                        </dd>
                    </div>
                    @if ($request->tenant)
                        <div><dt class="text-ink-muted">{{ __('maintenance.fields.tenant') }}</dt><dd>{{ $request->tenant->full_name }} · <a href="tel:{{ $request->tenant->phone }}" class="tabular-nums text-primary-600">{{ $request->tenant->phone }}</a></dd></div>
                    @endif
                    @if ($request->preferred_time)
                        <div><dt class="text-ink-muted">{{ __('maintenance.details.preferred') }}</dt><dd>{{ $request->preferred_time }}</dd></div>
                    @endif
                    @if ($request->access_instructions)
                        <div><dt class="text-ink-muted">{{ __('maintenance.details.access') }}</dt><dd>{{ $request->access_instructions }}</dd></div>
                    @endif
                    @if ($request->scheduled_for)
                        <div><dt class="text-ink-muted">{{ __('maintenance.details.scheduled') }}</dt><dd>{{ $request->scheduled_for->format('j M Y, H:i') }}</dd></div>
                    @endif
                    @if ($request->cost())
                        <div><dt class="text-ink-muted">{{ __('maintenance.details.cost') }}</dt><dd class="tabular-nums">{{ $request->cost()->format() }}@if ($request->expense) <span class="text-ink-muted">· {{ __('maintenance.details.expense') }}</span>@endif</dd></div>
                    @endif
                    @if ($request->tenant_rating)
                        <div><dt class="text-ink-muted">{{ __('maintenance.details.rating') }}</dt><dd>{{ str_repeat('★', $request->tenant_rating) }}{{ str_repeat('☆', 5 - $request->tenant_rating) }} {{ $request->tenant_feedback }}</dd></div>
                    @endif
                </dl>
                @if ($request->attachments->isNotEmpty())
                    <h3 class="mt-5 text-sm font-medium text-ink">{{ __('maintenance.details.photos') }}</h3>
                    <ul class="mt-2 grid grid-cols-3 gap-2 sm:grid-cols-5">
                        @foreach ($request->attachments as $photo)
                            <li><a href="{{ route('files.maintenance', $photo) }}" target="_blank" rel="noopener">
                                <img src="{{ route('files.maintenance', $photo) }}" alt="" class="h-20 w-full rounded-control object-cover" loading="lazy">
                            </a></li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            <x-card :title="__('maintenance.details.thread')">
                <form method="POST" action="{{ route('app.maintenance.comment', $request) }}" enctype="multipart/form-data" class="mb-6 space-y-3">
                    @csrf
                    <x-textarea name="body" :label="__('maintenance.fields.comment')" rows="2" required />
                    <x-checkbox name="is_internal" :label="__('maintenance.fields.internal')" />
                    <x-file-dropzone name="photos[]" multiple :max="5" accept="image/jpeg,image/png,image/webp" />
                    <x-button size="sm">{{ __('maintenance.actions.comment') }}</x-button>
                </form>
                @include('maintenance._thread', ['updates' => $request->updates])
            </x-card>
        </div>

        <div class="space-y-6">
            @if ($nextStatuses !== [])
                <x-card :title="__('maintenance.actions.update_status')">
                    <form method="POST" action="{{ route('app.maintenance.status', $request) }}" class="space-y-4" x-data="{ next: @js(old('status', $nextStatuses[0]->value)) }">
                        @csrf
                        <x-select name="status" :label="__('maintenance.fields.status')" x-model="next"
                            :options="collect($nextStatuses)->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()" />
                        <div x-show="next === 'scheduled'" x-cloak>
                            <x-input name="scheduled_for" type="datetime-local" :label="__('maintenance.fields.scheduled_for')" />
                        </div>
                        <div x-show="next === 'resolved'" x-cloak class="space-y-3">
                            <x-money-input name="cost_amount" :label="__('maintenance.fields.cost_amount')" :symbol="$currencySymbol" optional />
                            <x-checkbox name="record_expense" :label="__('maintenance.fields.record_expense')" checked />
                        </div>
                        <x-textarea name="note" :label="__('maintenance.fields.note')" rows="2" optional />
                        <x-button class="w-full">{{ __('maintenance.actions.update_status') }}</x-button>
                    </form>
                </x-card>
            @endif

            <x-card :title="__('maintenance.columns.assigned')">
                <form method="POST" action="{{ route('app.maintenance.assign', $request) }}" class="space-y-4">
                    @csrf
                    <x-select name="assigned_user_id" :label="__('maintenance.fields.assignee')" :options="$staff" :value="$request->assigned_user_id" placeholder="-" />
                    <x-select name="vendor_id" :label="__('maintenance.fields.vendor')" :options="$vendors" :value="$request->vendor_id" placeholder="-" />
                    <x-button variant="secondary" class="w-full">{{ __('maintenance.actions.assign') }}</x-button>
                </form>
                @if ($vendorLink)
                    <div class="mt-4 border-t border-border pt-4" x-data="{ copied: false }">
                        <p class="text-sm font-medium text-ink">{{ __('maintenance.details.vendor_link') }}</p>
                        <p class="text-xs text-ink-muted">{{ __('maintenance.details.vendor_link_hint') }}</p>
                        <input type="text" readonly value="{{ $vendorLink }}" class="mt-2 h-9 w-full rounded-control border-border bg-canvas text-xs" aria-label="{{ __('maintenance.details.vendor_link') }}" onclick="this.select()">
                    </div>
                @endif
            </x-card>
        </div>
    </div>
</x-layouts::admin>
