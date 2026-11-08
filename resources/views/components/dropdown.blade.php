{{-- Put the trigger in <x-slot:trigger> (it becomes a button) and menu entries, usually <x-dropdown.item>, in the slot. --}}
@props([
    'align' => 'right',
    'width' => 'w-56',
    'label' => null,
])

<div x-data="{ open: false }" class="relative" @keydown.escape.stop="open = false; $refs.trigger.focus()" @click.outside="open = false">
    <button type="button" x-ref="trigger" @click="open = !open" x-bind:aria-expanded="open.toString()" aria-haspopup="menu"
        @if ($label) aria-label="{{ $label }}" @endif
        {{ $trigger->attributes->class('inline-flex items-center gap-2 rounded-control') }}>
        {{ $trigger }}
    </button>
    <div x-show="open" x-cloak x-transition.origin.top role="menu"
        x-trap="open" @keydown.down.prevent="$focus.wrap().next()" @keydown.up.prevent="$focus.wrap().previous()"
        @click="open = false"
        {{ $attributes->class([
            'absolute z-40 mt-2 rounded-card border border-border bg-surface py-1 shadow-card',
            $width,
            'right-0' => $align === 'right',
            'left-0' => $align === 'left',
        ]) }}>
        {{ $slot }}
    </div>
</div>
