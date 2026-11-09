<x-layouts::admin :title="$tenant->full_name">
    <x-page-header :title="$tenant->full_name" :breadcrumbs="[__('tenants.title') => route('app.tenants.index'), $tenant->full_name => null]">
        <x-slot:actions>
            <x-button variant="secondary" :href="'https://wa.me/'.$whatsappPhone" target="_blank" rel="noopener" icon="chat-bubble-left-ellipsis">{{ __('tenants.whatsapp') }}</x-button>
            @if ($canManage)
                @unless ($tenant->hasPortalAccount())
                    <form method="POST" action="{{ route('app.tenants.invite', $tenant) }}">
                        @csrf
                        <x-button variant="secondary" icon="paper-airplane">{{ $tenant->invite_sent_at ? __('tenants.resend_invite') : __('tenants.invite') }}</x-button>
                    </form>
                @endunless
                <x-dropdown>
                    <x-slot:trigger class="h-10 border border-border px-3 text-sm font-medium text-ink hover:bg-canvas" aria-label="{{ __('tenants.edit') }}">
                        <x-heroicon-m-ellipsis-horizontal class="h-5 w-5" aria-hidden="true" />
                    </x-slot:trigger>
                    <x-dropdown.item :href="route('app.tenants.edit', $tenant)" icon="pencil-square">{{ __('tenants.edit') }}</x-dropdown.item>
                    <x-dropdown.item :href="route('app.leases.wizard.start', ['tenant' => $tenant->id])" icon="document-plus">{{ __('tenants.overview.new_lease') }}</x-dropdown.item>
                    <x-dropdown.item icon="trash" danger x-on:click="$dispatch('open-modal', 'delete-tenant')">{{ __('tenants.delete') }}</x-dropdown.item>
                </x-dropdown>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($errors->has('tenant'))
        <x-alert type="danger" class="mb-4">{{ $errors->first('tenant') }}</x-alert>
    @endif

    @php
        $tabLabels = collect($tabs)->mapWithKeys(fn ($t) => [$t => __('tenants.tabs.'.$t)])->all();
        $tabLinks = collect($tabs)->mapWithKeys(fn ($t) => [$t => route('app.tenants.show', [$tenant, 'tab' => $t])])->all();
    @endphp

    <x-tabs :tabs="$tabLabels" :links="$tabLinks" :active="$tab">
        @if ($tab === 'overview')
            <div class="grid gap-6 lg:grid-cols-3">
                <x-card :title="__('tenants.overview.current_lease')" class="lg:col-span-2">
                    @if ($lease = $tenant->activeLease)
                        <dl class="grid gap-4 text-sm sm:grid-cols-2">
                            <div><dt class="text-ink-muted">{{ __('leases.summary.unit') }}</dt><dd class="font-medium">{{ $lease->unit->label }}, {{ $lease->unit->property->name }}</dd></div>
                            <div><dt class="text-ink-muted">{{ __('leases.summary.period') }}</dt><dd>{{ $lease->start_date->format('j M Y') }} to {{ $lease->end_date->format('j M Y') }}</dd></div>
                            @if ($canManage)
                                <div><dt class="text-ink-muted">{{ __('leases.fields.rent_amount') }}</dt><dd class="tabular-nums">{{ $lease->rent()->format() }} <span class="text-ink-muted">{{ $lease->rent_frequency->label() }}</span></dd></div>
                                <div><dt class="text-ink-muted">{{ __('tenants.overview.balance') }}</dt><dd class="tabular-nums">{{ $tenant->creditBalance()->format() }}</dd></div>
                            @endif
                        </dl>
                        @if ($canManage)
                            <x-button size="sm" variant="secondary" class="mt-4" :href="route('app.leases.show', $lease)">{{ __('leases.title') }}</x-button>
                        @endif
                    @else
                        <p class="text-sm text-ink-muted">{{ __('tenants.overview.no_lease') }}</p>
                        @if ($canManage)
                            <x-button size="sm" class="mt-4" :href="route('app.leases.wizard.start', ['tenant' => $tenant->id])" icon="plus">{{ __('tenants.overview.new_lease') }}</x-button>
                        @endif
                    @endif
                </x-card>

                <x-card :title="__('tenants.overview.contacts')">
                    <dl class="space-y-3 text-sm">
                        <div><dt class="text-ink-muted">{{ __('tenants.fields.phone') }}</dt><dd class="tabular-nums"><a href="tel:{{ $tenant->phone }}" class="text-primary-600 hover:underline">{{ $tenant->phone }}</a></dd></div>
                        @if ($tenant->alt_phone)
                            <div><dt class="text-ink-muted">{{ __('tenants.fields.alt_phone') }}</dt><dd class="tabular-nums">{{ $tenant->alt_phone }}</dd></div>
                        @endif
                        @if ($tenant->email)
                            <div><dt class="text-ink-muted">{{ __('tenants.fields.email') }}</dt><dd class="break-all"><a href="mailto:{{ $tenant->email }}" class="text-primary-600 hover:underline">{{ $tenant->email }}</a></dd></div>
                        @endif
                        @if ($tenant->emergency_contact_name)
                            <div><dt class="text-ink-muted">{{ __('tenants.fields.emergency_contact_name') }}</dt><dd>{{ $tenant->emergency_contact_name }} <span class="tabular-nums text-ink-muted">{{ $tenant->emergency_contact_phone }}</span></dd></div>
                        @endif
                        @if ($canManage)
                            <div>
                                <dt class="text-ink-muted">{{ __('tenants.overview.portal') }}</dt>
                                <dd>
                                    @if ($tenant->hasPortalAccount())
                                        {{ __('tenants.overview.portal_active') }}
                                    @elseif ($tenant->invite_sent_at)
                                        {{ __('tenants.overview.portal_invited', ['when' => $tenant->invite_sent_at->diffForHumans()]) }}
                                    @else
                                        {{ __('tenants.overview.portal_none') }}
                                    @endif
                                </dd>
                            </div>
                        @endif
                    </dl>
                </x-card>
            </div>
        @elseif ($tab === 'leases')
            @include('admin.leases._table', ['leases' => $leases, 'showTenant' => false])
        @elseif ($tab === 'documents')
            <x-documents :documentable="$tenant" :documents="$documents" :visibilities="$visibilities" :can-manage="$canManage" />
        @elseif ($tab === 'activity')
            @if ($activity->isEmpty())
                <x-empty-state icon="clock" :title="__('tenants.no_activity')" />
            @else
                <x-card>
                    <x-timeline>
                        @foreach ($activity as $entry)
                            <x-timeline.item :title="\Illuminate\Support\Str::headline(str_replace('.', ' ', $entry->action))" :time="$entry->created_at?->diffForHumans()">
                                {{ $entry->user?->name }}
                            </x-timeline.item>
                        @endforeach
                    </x-timeline>
                </x-card>
            @endif
        @endif
    </x-tabs>

    @if ($canManage)
        <x-confirm name="delete-tenant" :action="route('app.tenants.destroy', $tenant)"
            :title="__('tenants.delete_title', ['name' => $tenant->full_name])" :consequences="__('tenants.delete_consequences')"
            :confirm-label="__('tenants.delete')" />
    @endif
</x-layouts::admin>
