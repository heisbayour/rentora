@php
    $editing = $tenant->exists;
    $title = $editing ? __('tenants.edit') : __('tenants.new');
    $breadcrumbs = [__('tenants.title') => route('app.tenants.index')];
    if ($editing) {
        $breadcrumbs[$tenant->full_name] = route('app.tenants.show', $tenant);
    }
    $breadcrumbs[$title] = null;
@endphp

<x-layouts::admin :title="$title">
    <x-page-header :title="$title" :breadcrumbs="$breadcrumbs" />

    <form method="POST" action="{{ $editing ? route('app.tenants.update', $tenant) : route('app.tenants.store') }}" class="max-w-3xl space-y-6">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif
        <x-card :title="__('tenants.sections.contact')">
            @include('admin.tenants._fields', ['tenant' => $tenant])
        </x-card>
        <x-card :title="__('tenants.sections.preferences')">
            <div class="space-y-4">
                <x-toggle name="notification_prefs[email]" id="pref-email" :label="__('tenants.fields.notification_email')" :checked="$tenant->wants('email')" />
                <x-toggle name="notification_prefs[sms]" id="pref-sms" :label="__('tenants.fields.notification_sms')" :checked="$tenant->wants('sms')" />
                <x-textarea name="notes" :label="__('tenants.fields.notes')" :value="$tenant->notes" rows="3" optional />
            </div>
            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <x-button variant="secondary" :href="$editing ? route('app.tenants.show', $tenant) : route('app.tenants.index')">{{ __('ui.cancel') }}</x-button>
                    <x-button>{{ __('tenants.save') }}</x-button>
                </div>
            </x-slot:footer>
        </x-card>
    </form>
</x-layouts::admin>
