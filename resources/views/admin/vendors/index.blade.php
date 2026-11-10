<x-layouts::admin :title="__('vendors.title')">
    <x-page-header :title="__('vendors.title')" :description="__('vendors.description')">
        @if ($canManage)
            <x-slot:actions>
                <x-button type="button" icon="plus" x-data x-on:click="$dispatch('open-modal', 'vendor-new')">{{ __('vendors.new') }}</x-button>
            </x-slot:actions>
        @endif
    </x-page-header>

    <form method="GET" class="mb-4 w-full sm:w-80" data-no-guard>
        <x-input name="q" :value="$search" :placeholder="__('vendors.search')" aria-label="{{ __('vendors.search') }}" />
    </form>

    @if ($vendors->isEmpty())
        <x-empty-state icon="wrench" :title="__('vendors.empty_title')" :text="__('vendors.empty_text')" />
    @else
        <x-table :caption="__('vendors.title')">
            <x-slot:head>
                <th>{{ __('vendors.columns.vendor') }}</th>
                <th>{{ __('vendors.columns.contact') }}</th>
                <th class="text-right">{{ __('vendors.columns.open_jobs') }}</th>
                <th>{{ __('vendors.columns.status') }}</th>
                @if ($canManage)<th><span class="sr-only">{{ __('vendors.edit', ['name' => '']) }}</span></th>@endif
            </x-slot:head>
            @foreach ($vendors as $vendor)
                <tr>
                    <td data-label="{{ __('vendors.columns.vendor') }}"><span class="font-medium">{{ $vendor->name }}</span><span class="block text-xs text-ink-muted">{{ $vendor->trade }}</span></td>
                    <td data-label="{{ __('vendors.columns.contact') }}" class="tabular-nums">{{ $vendor->phone }}<span class="block text-xs text-ink-muted">{{ $vendor->email }}</span></td>
                    <td data-label="{{ __('vendors.columns.open_jobs') }}" class="text-right tabular-nums">{{ $vendor->open_jobs_count }}</td>
                    <td data-label="{{ __('vendors.columns.status') }}"><x-status-chip :label="$vendor->is_active ? __('vendors.active') : __('vendors.inactive')" :color="$vendor->is_active ? 'success' : 'neutral'" /></td>
                    @if ($canManage)
                        <td data-label="" class="text-right"><x-button type="button" size="sm" variant="ghost" x-data x-on:click="$dispatch('open-modal', 'vendor-{{ $vendor->id }}')">{{ __('ui.edit') }}</x-button></td>
                    @endif
                </tr>
            @endforeach
        </x-table>
        <x-pagination :paginator="$vendors" class="mt-4" />
    @endif

    @if ($canManage)
        @foreach ([null, ...$vendors->items()] as $vendor)
            @php($key = $vendor ? 'vendor-'.$vendor->id : 'vendor-new')
            <x-modal :name="$key" :title="$vendor ? __('vendors.edit', ['name' => $vendor->name]) : __('vendors.new')">
                <form method="POST" action="{{ $vendor ? route('app.vendors.update', $vendor) : route('app.vendors.store') }}" class="space-y-4">
                    @csrf
                    @if ($vendor) @method('PUT') @endif
                    <x-input name="name" :id="$key.'-name'" :label="__('vendors.fields.name')" :value="$vendor?->name" required />
                    <x-input name="trade" :id="$key.'-trade'" :label="__('vendors.fields.trade')" :value="$vendor?->trade" optional />
                    <x-input name="phone" :id="$key.'-phone'" type="tel" :label="__('vendors.fields.phone')" :value="$vendor?->phone" />
                    <x-input name="email" :id="$key.'-email'" type="email" :label="__('vendors.fields.email')" :value="$vendor?->email" optional />
                    <x-textarea name="notes" :id="$key.'-notes'" :label="__('vendors.fields.notes')" :value="$vendor?->notes" rows="2" optional />
                    <x-toggle name="is_active" :id="$key.'-active'" :label="__('vendors.fields.is_active')" :hint="__('vendors.fields.is_active_hint')" :checked="$vendor?->is_active ?? true" />
                    <div class="flex justify-end gap-2 pt-2">
                        <x-button type="button" variant="secondary" x-on:click="$dispatch('close-modal', @js($key))">{{ __('ui.cancel') }}</x-button>
                        <x-button>{{ __('vendors.save') }}</x-button>
                    </div>
                </form>
            </x-modal>
        @endforeach
    @endif
</x-layouts::admin>
