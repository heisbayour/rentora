{{-- Document shell shared by every layout: head, assets, brand colour and the toast region. --}}
@props([
    'title' => null,
    'brandColor' => null,
    'manifest' => null,
    'description' => null,
    'bodyClass' => '',
])

@php
    $appName = config('rentora.name');
    $fullTitle = $title ? $title.' - '.$appName : $appName;
    $brandColor = $brandColor ?? config('rentora.defaults.primary_color');
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $fullTitle }}</title>
    @if ($description)
        <meta name="description" content="{{ $description }}">
    @endif
    <meta name="theme-color" content="{{ $brandColor }}">
    @if ($manifest)
        <link rel="manifest" href="{{ $manifest }}">
    @endif
    <style>:root { {{ \App\Support\BrandColor::cssVariables($brandColor) }} }</style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="h-full font-sans {{ $bodyClass }}">
    <a href="#main" class="sr-only z-[70] rounded-control bg-surface px-4 py-2 text-sm font-medium text-primary-600 focus:not-sr-only focus:fixed focus:left-4 focus:top-4">
        {{ __('ui.skip_to_content') }}
    </a>
    {{ $slot }}
    <x-toast />
    @stack('scripts')
</body>
</html>
