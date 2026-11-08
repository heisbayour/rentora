{{-- Pass an enum implementing HasPresentation as :status, or a :label with a :color. --}}
@props([
    'status' => null,
    'label' => null,
    'color' => null,
])

@php
    if ($status instanceof \App\Enums\Contracts\HasPresentation) {
        $label = $label ?? $status->label();
        $color = $color ?? $status->color();
    }
    $label = $label ?? (is_string($status) ? \Illuminate\Support\Str::headline($status) : '');
    $palette = [
        'success' => 'bg-success-bg text-success',
        'warning' => 'bg-warning-bg text-warning',
        'danger' => 'bg-danger-bg text-danger',
        'info' => 'bg-info-bg text-info',
        'neutral' => 'bg-canvas text-ink-muted ring-1 ring-inset ring-border',
    ];
@endphp

<span {{ $attributes->class('inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium '.($palette[$color ?? 'neutral'] ?? $palette['neutral'])) }}>
    <span class="h-1.5 w-1.5 rounded-full bg-current" aria-hidden="true"></span>
    {{ $label }}
</span>
