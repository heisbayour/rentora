<x-layouts::admin :title="__('announcements.new')">
    <x-page-header :title="__('announcements.new')" :description="__('announcements.new_intro')"
        :breadcrumbs="[__('announcements.title') => route('app.announcements.index'), __('announcements.new') => null]" />

    <form method="POST" action="{{ route('app.announcements.store') }}" class="max-w-2xl"
        x-data="{
            audience: @js(old('audience', 'all_tenants')),
            count: null,
            async refresh() {
                const field = this.audience === 'property' ? 'property_ids[]' : 'tenant_ids[]';
                const ids = this.audience === 'all_tenants' ? [] : [...this.$root.querySelectorAll(`input[name='${field}']:checked`)].map(i => i.value);
                const params = new URLSearchParams({ audience: this.audience });
                ids.forEach(id => params.append('ids[]', id));
                const response = await fetch(@js(route('app.announcements.recipients')) + '?' + params, { headers: { Accept: 'application/json' } });
                this.count = response.ok ? (await response.json()).count : null;
            },
        }" x-init="refresh()" @change="refresh()">
        @csrf
        <x-card>
            <div class="space-y-5">
                <x-input name="title" :label="__('announcements.fields.title')" required maxlength="150" />
                <x-textarea name="body" :label="__('announcements.fields.body')" rows="5" required maxlength="2000" />

                <fieldset>
                    <legend class="mb-2 text-sm font-medium text-ink">{{ __('announcements.fields.audience') }}</legend>
                    <div class="flex flex-wrap gap-4">
                        @foreach ($audiences as $value => $label)
                            <label class="inline-flex items-center gap-2 text-sm text-ink">
                                <input type="radio" name="audience" value="{{ $value }}" x-model="audience" class="h-4 w-4 border-border text-primary-600 focus:ring-primary-600">
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                    @error('audience')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
                </fieldset>

                <div x-show="audience === 'property'" x-cloak class="max-h-56 space-y-2 overflow-y-auto rounded-control border border-border p-3">
                    @forelse ($properties as $id => $name)
                        <x-checkbox name="property_ids[]" :value="$id" :label="$name" />
                    @empty
                        <p class="text-sm text-ink-muted">{{ __('announcements.no_properties') }}</p>
                    @endforelse
                    @error('property_ids')<p class="text-sm text-danger">{{ $message }}</p>@enderror
                </div>

                <div x-show="audience === 'tenants'" x-cloak class="max-h-56 space-y-2 overflow-y-auto rounded-control border border-border p-3">
                    @forelse ($tenants as $id => $name)
                        <x-checkbox name="tenant_ids[]" :value="$id" :label="$name" />
                    @empty
                        <p class="text-sm text-ink-muted">{{ __('announcements.no_tenants') }}</p>
                    @endforelse
                    @error('tenant_ids')<p class="text-sm text-danger">{{ $message }}</p>@enderror
                </div>

                <fieldset>
                    <legend class="mb-2 text-sm font-medium text-ink">{{ __('announcements.fields.channels') }}</legend>
                    <div class="flex flex-wrap gap-5">
                        @foreach (\App\Models\Announcement::CHANNELS as $channel)
                            <x-checkbox name="channels[]" :value="$channel" :label="__('announcements.channels.'.$channel)" :checked="$channel === 'in_app'" />
                        @endforeach
                    </div>
                    @error('channels')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
                </fieldset>

                <p class="rounded-control bg-info-bg px-3 py-2 text-sm text-info" aria-live="polite">
                    <span x-show="count !== null" x-text="@js(__('announcements.recipients_prefix')) + ' ' + count"></span>
                    <span x-show="count === null">{{ __('announcements.recipients_loading') }}</span>
                </p>
            </div>
            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <x-button variant="secondary" :href="route('app.announcements.index')">{{ __('ui.cancel') }}</x-button>
                    <x-button icon="paper-airplane">{{ __('announcements.send') }}</x-button>
                </div>
            </x-slot:footer>
        </x-card>
    </form>
</x-layouts::admin>
