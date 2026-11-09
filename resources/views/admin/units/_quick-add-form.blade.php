{{--
    Quick-add units table form. Used by the quick-add page and the first-run wizard.
    Optional: $formAction, $submitLabel, $cancelUrl (null hides the cancel button).
--}}
@error('units')
    <x-alert type="danger" class="mb-4">{{ $message }}</x-alert>
@enderror

@php
    $initial = old('units') ?: array_fill(0, $rows, ['label' => '', 'type' => 'flat', 'rent_amount' => '', 'rent_frequency' => 'annual']);
@endphp

<form method="POST" action="{{ $formAction ?? route('app.properties.quick-add.store', $property) }}"
    x-data="{ rows: @js(array_values($initial)), max: {{ $max }} }">
    @csrf
    <x-card :padding="false">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[640px] text-left text-sm">
                <thead class="border-b border-border bg-canvas text-xs font-medium uppercase tracking-wide text-ink-muted">
                    <tr>
                        <th class="w-10 px-3 py-3">#</th>
                        <th class="px-3 py-3">{{ __('units.fields.label') }}</th>
                        <th class="px-3 py-3">{{ __('units.fields.type') }}</th>
                        <th class="px-3 py-3">{{ __('units.fields.rent_amount') }} ({{ $currencySymbol }})</th>
                        <th class="px-3 py-3">{{ __('units.fields.rent_frequency') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(row, index) in rows" :key="index">
                        <tr class="border-b border-border last:border-0">
                            <td class="px-3 py-2 text-ink-muted tabular-nums" x-text="index + 1"></td>
                            <td class="px-3 py-2">
                                <input type="text" :name="`units[${index}][label]`" x-model="row.label" maxlength="60"
                                    class="h-9 w-full rounded-control border-border text-sm focus:border-primary-600 focus:ring-primary-600"
                                    :aria-label="@js(__('units.fields.label')) + ' ' + (index + 1)">
                            </td>
                            <td class="px-3 py-2">
                                <select :name="`units[${index}][type]`" x-model="row.type"
                                    class="h-9 w-full rounded-control border-border text-sm focus:border-primary-600 focus:ring-primary-600"
                                    :aria-label="@js(__('units.fields.type')) + ' ' + (index + 1)">
                                    @foreach ($types as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-3 py-2" x-data="{ display: '' }" x-init="display = row.rent_amount === '' || row.rent_amount === null ? '' : (Number(row.rent_amount) / 100).toLocaleString('en-NG')">
                                <input type="text" inputmode="decimal" x-model="display"
                                    @input="const n = display.replace(/[^0-9.]/g, ''); row.rent_amount = n === '' ? '' : Math.round(Number(n) * 100)"
                                    class="h-9 w-full rounded-control border-border text-right text-sm tabular-nums focus:border-primary-600 focus:ring-primary-600"
                                    :aria-label="@js(__('units.fields.rent_amount')) + ' ' + (index + 1)">
                                <input type="hidden" :name="`units[${index}][rent_amount]`" :value="row.rent_amount">
                            </td>
                            <td class="px-3 py-2">
                                <select :name="`units[${index}][rent_frequency]`" x-model="row.rent_frequency"
                                    class="h-9 w-full rounded-control border-border text-sm focus:border-primary-600 focus:ring-primary-600"
                                    :aria-label="@js(__('units.fields.rent_frequency')) + ' ' + (index + 1)">
                                    @foreach ($frequencies as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        <x-slot:footer>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <x-button type="button" variant="ghost" icon="plus" x-show="rows.length < max"
                    x-on:click="rows.push({ label: '', type: 'flat', rent_amount: '', rent_frequency: 'annual' })">{{ __('units.quick_add.add_row') }}</x-button>
                <div class="ml-auto flex gap-2">
                    @php($cancelUrl = array_key_exists('cancelUrl', get_defined_vars()) ? $cancelUrl : route('app.properties.show', $property))
                    @if ($cancelUrl)
                        <x-button variant="secondary" :href="$cancelUrl">{{ __('ui.cancel') }}</x-button>
                    @endif
                    <x-button>{{ $submitLabel ?? __('units.quick_add.submit') }}</x-button>
                </div>
            </div>
        </x-slot:footer>
    </x-card>
</form>

@if ($errors->has('units.*'))
    <ul class="mt-4 space-y-1 text-sm text-danger">
        @foreach ($errors->get('units.*') as $messages)
            @foreach ($messages as $message)
                <li>{{ $message }}</li>
            @endforeach
        @endforeach
    </ul>
@endif
