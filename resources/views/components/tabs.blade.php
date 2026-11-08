{{--
    :tabs is [key => label]. Two modes:
    - client side: pass no :links and put <x-tabs.panel name="key"> blocks in the slot;
    - server side: pass :links as [key => url] and :active, and render the active panel yourself.
--}}
@props([
    'tabs' => [],
    'active' => null,
    'links' => null,
])

@php
    $active = $active ?? array_key_first($tabs);
@endphp

<div {{ $attributes }} @if (! $links) x-data="{ tab: @js($active) }" @endif>
    <div class="border-b border-border">
        <nav class="-mb-px flex gap-6 overflow-x-auto" role="tablist">
            @foreach ($tabs as $key => $label)
                @if ($links)
                    <a href="{{ $links[$key] ?? '#' }}" role="tab" aria-selected="{{ $key === $active ? 'true' : 'false' }}"
                        @class([
                            'whitespace-nowrap border-b-2 px-1 pb-3 text-sm font-medium',
                            'border-primary-600 text-primary-600' => $key === $active,
                            'border-transparent text-ink-muted hover:text-ink' => $key !== $active,
                        ])>{{ $label }}</a>
                @else
                    <button type="button" role="tab" id="tab-{{ $key }}" aria-controls="panel-{{ $key }}"
                        x-bind:aria-selected="(tab === @js($key)).toString()" @click="tab = @js($key)"
                        class="whitespace-nowrap border-b-2 px-1 pb-3 text-sm font-medium"
                        x-bind:class="tab === @js($key) ? 'border-primary-600 text-primary-600' : 'border-transparent text-ink-muted hover:text-ink'">
                        {{ $label }}
                    </button>
                @endif
            @endforeach
        </nav>
    </div>
    <div class="pt-5">{{ $slot }}</div>
</div>
