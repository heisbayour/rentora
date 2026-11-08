{{--
    Confirmation modal for destructive actions. States the consequences and,
    when :word is set, requires the user to type it before the button enables.
    Open with $dispatch('open-modal', name).
--}}
@props([
    'name',
    'action',
    'method' => 'DELETE',
    'title',
    'consequences' => null,
    'word' => null,
    'confirmLabel' => null,
    'variant' => 'danger',
])

<x-modal :name="$name" :title="$title" max-width="sm">
    <form method="POST" action="{{ $action }}" x-data="typedConfirm(@js($word ?? ''))" id="confirm-{{ $name }}">
        @csrf
        @if (strtoupper($method) !== 'POST')
            @method($method)
        @endif
        @if ($consequences)
            <p class="text-sm text-ink-muted">{{ $consequences }}</p>
        @endif
        {{ $slot }}
        @if ($word)
            <div class="mt-4">
                <x-input :name="'confirm_word_'.$name" :label="__('ui.confirm_type', ['word' => $word])" x-model="typed" autocomplete="off" />
            </div>
        @endif
        <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <x-button type="button" variant="secondary" x-on:click="$dispatch('close-modal', @js($name))">{{ __('ui.cancel') }}</x-button>
            <x-button :variant="$variant" x-bind:disabled="!confirmed">{{ $confirmLabel ?? __('ui.confirm') }}</x-button>
        </div>
    </form>
</x-modal>
