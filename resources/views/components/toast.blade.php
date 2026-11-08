{{--
    Toast region, rendered once per layout. Driven by session flash:
      ->with('toast', 'Saved.') or ->with('toast', ['message' => 'Saved.', 'type' => 'success'])
    and by the browser event: window.dispatchEvent(new CustomEvent('toast', { detail: { message, type } })).
--}}
@php
    $flash = session('toast');
    if (is_string($flash)) {
        $flash = ['message' => $flash, 'type' => 'success'];
    }
@endphp

<div x-data x-init="@if ($flash) $store.toasts.push(@js($flash['message'] ?? ''), @js($flash['type'] ?? 'success')) @endif"
    class="pointer-events-none fixed inset-x-0 bottom-20 z-[60] flex flex-col items-center gap-2 px-4 md:bottom-6 md:items-end md:px-6"
    aria-live="polite" aria-atomic="false">
    <template x-for="item in $store.toasts.items" :key="item.id">
        <div x-transition.opacity role="status"
            class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-control bg-ink px-4 py-3 text-sm text-white shadow-card">
            <span class="mt-0.5 h-2 w-2 shrink-0 rounded-full"
                :class="{ 'bg-success': item.type === 'success', 'bg-warning': item.type === 'warning', 'bg-danger': item.type === 'danger' || item.type === 'error', 'bg-info': item.type === 'info' }"
                aria-hidden="true"></span>
            <p class="flex-1" x-text="item.message"></p>
            <button type="button" class="text-white/70 hover:text-white" @click="$store.toasts.dismiss(item.id)">
                <span class="sr-only">{{ __('ui.close') }}</span>
                <x-heroicon-m-x-mark class="h-4 w-4" aria-hidden="true" />
            </button>
        </div>
    </template>
</div>
