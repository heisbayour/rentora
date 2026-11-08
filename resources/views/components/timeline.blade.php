{{-- Wrap <x-timeline.item> entries, newest first. --}}
<ol {{ $attributes->class('relative space-y-6 border-l border-border pl-6') }}>
    {{ $slot }}
</ol>
