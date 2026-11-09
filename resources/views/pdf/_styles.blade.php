@php
    $color = $organization?->primary_color ?? config('rentora.defaults.primary_color');
@endphp
<style>
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #202124; margin: 0; }
    .header { border-bottom: 3px solid {{ $color }}; padding-bottom: 12px; margin-bottom: 20px; width: 100%; }
    .org { font-size: 16px; font-weight: bold; }
    .muted { color: #5F6368; }
    .title { font-size: 20px; font-weight: bold; color: {{ $color }}; text-align: right; }
    table { width: 100%; border-collapse: collapse; }
    .items th { text-align: left; background: #F8F9FA; padding: 6px 8px; border-bottom: 1px solid #E3E6EA; }
    .items td { padding: 6px 8px; border-bottom: 1px solid #E3E6EA; }
    .right { text-align: right; }
    .big { font-size: 18px; font-weight: bold; }
</style>
