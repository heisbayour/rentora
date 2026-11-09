<x-layouts::admin :title="$unit->label">
    <x-page-header :title="$unit->label" :description="$unit->property->name"
        :breadcrumbs="[__('units.title') => route('app.units.index'), $unit->property->name => $canManage ? route('app.properties.show', $unit->property) : null, $unit->label => null]">
        @if ($canManage)
            <x-slot:actions>
                <x-button variant="secondary" :href="route('app.units.edit', $unit)" icon="pencil-square">{{ __('units.edit') }}</x-button>
                <x-button type="button" variant="ghost" class="text-danger" x-data x-on:click="$dispatch('open-modal', 'delete-unit')">{{ __('units.delete') }}</x-button>
            </x-slot:actions>
        @endif
    </x-page-header>

    @error('unit')
        <x-alert type="danger" class="mb-4">{{ $message }}</x-alert>
    @enderror

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card :title="__('units.current_tenant')" class="lg:col-span-3">
            @if ($tenant = $unit->activeLease?->tenant)
                <div class="flex flex-wrap items-center gap-4 text-sm">
                    <x-avatar :name="$tenant->full_name" />
                    <div>
                        <a href="{{ route('app.tenants.show', $tenant) }}" class="font-medium text-ink hover:text-primary-600">{{ $tenant->full_name }}</a>
                        <p class="text-ink-muted"><a href="tel:{{ $tenant->phone }}" class="tabular-nums hover:underline">{{ $tenant->phone }}</a>
                            @if ($tenant->email) · <a href="mailto:{{ $tenant->email }}" class="hover:underline">{{ $tenant->email }}</a>@endif</p>
                    </div>
                    @if ($canManage)
                        <x-button size="sm" variant="secondary" class="ml-auto" :href="route('app.leases.show', $unit->activeLease)">{{ __('leases.title') }}</x-button>
                    @endif
                </div>
            @else
                <p class="text-sm text-ink-muted">{{ __('units.no_tenant') }}</p>
            @endif
        </x-card>

        <x-card :title="__('units.details')" class="lg:col-span-1">
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between gap-4"><dt class="text-ink-muted">{{ __('units.fields.status') }}</dt><dd><x-status-chip :status="$unit->status" /></dd></div>
                <div class="flex justify-between gap-4"><dt class="text-ink-muted">{{ __('units.fields.type') }}</dt><dd>{{ $unit->type->label() }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-ink-muted">{{ __('units.fields.bedrooms') }}</dt><dd class="tabular-nums">{{ $unit->bedrooms }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-ink-muted">{{ __('units.fields.bathrooms') }}</dt><dd class="tabular-nums">{{ $unit->bathrooms }}</dd></div>
                @if ($unit->size_sqm)
                    <div class="flex justify-between gap-4"><dt class="text-ink-muted">{{ __('units.fields.size_sqm') }}</dt><dd class="tabular-nums">{{ rtrim(rtrim((string) $unit->size_sqm, '0'), '.') }}</dd></div>
                @endif
                <div class="flex justify-between gap-4"><dt class="text-ink-muted">{{ __('units.fields.rent_amount') }}</dt><dd class="text-right tabular-nums">{{ $unit->rent()->format() }}<span class="block text-xs text-ink-muted">{{ $unit->rent_frequency->label() }}</span></dd></div>
                <div class="flex justify-between gap-4"><dt class="text-ink-muted">{{ __('units.fields.service_charge_amount') }}</dt><dd class="tabular-nums">{{ $unit->serviceCharge()->format() }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-ink-muted">{{ __('units.fields.caution_deposit_amount') }}</dt><dd class="tabular-nums">{{ $unit->cautionDeposit()->format() }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-ink-muted">{{ __('units.fields.is_listed') }}</dt><dd><x-status-chip :label="$unit->is_listed ? __('units.listed') : __('units.not_listed')" :color="$unit->is_listed ? 'info' : 'neutral'" /></dd></div>
            </dl>
            @if ($unit->description)
                <p class="mt-4 whitespace-pre-line border-t border-border pt-4 text-sm text-ink">{{ $unit->description }}</p>
            @endif
        </x-card>

        <x-card :title="__('units.photos.title')" :description="__('units.photos.intro')" class="lg:col-span-2">
            @if ($unit->photos->isEmpty())
                <p class="text-sm text-ink-muted">{{ __('units.photos.empty') }}</p>
            @else
                <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    @foreach ($unit->photos as $photo)
                        <li class="overflow-hidden rounded-control border border-border">
                            <img src="{{ $photo->url() }}" alt="{{ $unit->label }} {{ $loop->iteration }}" class="h-32 w-full object-cover" loading="lazy">
                            @if ($canManage)
                                <div class="flex items-center justify-between gap-1 px-2 py-1.5">
                                    <div class="flex gap-1">
                                        <form method="POST" action="{{ route('app.units.photos.move', [$unit, $photo]) }}">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="direction" value="up">
                                            <button class="rounded-control p-1 text-ink-muted hover:bg-canvas disabled:opacity-40" @disabled($loop->first) aria-label="{{ __('units.photos.move_up') }}">
                                                <x-heroicon-m-arrow-left class="h-4 w-4" aria-hidden="true" />
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('app.units.photos.move', [$unit, $photo]) }}">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="direction" value="down">
                                            <button class="rounded-control p-1 text-ink-muted hover:bg-canvas disabled:opacity-40" @disabled($loop->last) aria-label="{{ __('units.photos.move_down') }}">
                                                <x-heroicon-m-arrow-right class="h-4 w-4" aria-hidden="true" />
                                            </button>
                                        </form>
                                    </div>
                                    <form method="POST" action="{{ route('app.units.photos.destroy', [$unit, $photo]) }}">
                                        @csrf @method('DELETE')
                                        <button class="rounded-control p-1 text-danger hover:bg-danger-bg" aria-label="{{ __('units.photos.delete') }}">
                                            <x-heroicon-m-trash class="h-4 w-4" aria-hidden="true" />
                                        </button>
                                    </form>
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($canManage)
                <form method="POST" action="{{ route('app.units.photos.store', $unit) }}" enctype="multipart/form-data" class="mt-5 space-y-3 border-t border-border pt-5">
                    @csrf
                    <x-file-dropzone name="photos[]" multiple :max="20" accept="image/jpeg,image/png,image/webp" />
                    <x-button icon="arrow-up-tray">{{ __('units.photos.upload') }}</x-button>
                </form>
            @endif
        </x-card>
    </div>

    @if ($canManage)
        <x-confirm name="delete-unit" :action="route('app.units.destroy', $unit)"
            :title="__('units.delete_title', ['label' => $unit->label])" :consequences="__('units.delete_consequences')"
            :confirm-label="__('units.delete')" />
    @endif
</x-layouts::admin>
