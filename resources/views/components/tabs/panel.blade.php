@props(['name'])

<div id="panel-{{ $name }}" role="tabpanel" aria-labelledby="tab-{{ $name }}" x-show="tab === @js($name)" x-cloak {{ $attributes }}>
    {{ $slot }}
</div>
