<x-layouts::portal :title="__('portal.home.title')">
    <h1 class="text-xl font-semibold text-ink">{{ __('portal.home.greeting', ['name' => auth()->user()->name]) }}</h1>
    <x-empty-state class="mt-6" icon="home" :title="__('portal.home.empty_title')" :text="__('portal.home.empty_text')" />
</x-layouts::portal>
