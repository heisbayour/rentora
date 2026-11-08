{{--
    Sticky header table that becomes stacked cards under 768px.
    Give every <td> a data-label matching its column so the stacked card can label it.
--}}
@props([
    'caption' => null,
])

<div {{ $attributes->class('md:overflow-x-auto md:rounded-card md:border md:border-border md:bg-surface md:shadow-card') }}>
    <table class="rt-table w-full border-collapse text-left text-sm">
        @if ($caption)
            <caption class="sr-only">{{ $caption }}</caption>
        @endif
        @isset($head)
            <thead>
                <tr class="[&>th]:sticky [&>th]:top-0 [&>th]:z-10 [&>th]:border-b [&>th]:border-border [&>th]:bg-canvas [&>th]:px-4 [&>th]:py-3 [&>th]:text-xs [&>th]:font-medium [&>th]:uppercase [&>th]:tracking-wide [&>th]:text-ink-muted">
                    {{ $head }}
                </tr>
            </thead>
        @endisset
        <tbody class="md:[&>tr:hover]:bg-canvas md:[&>tr>td]:border-b md:[&>tr>td]:border-border md:[&>tr>td]:px-4 md:[&>tr>td]:py-3 md:[&>tr:last-child>td]:border-b-0">
            {{ $slot }}
        </tbody>
    </table>
</div>
