<?php

declare(strict_types=1);

it('renders every component in the styleguide', function () {
    $response = $this->get(route('app.styleguide'));

    $response->assertOk();
    foreach (['button', 'form', 'stat-card', 'status-chip', 'avatar', 'table', 'empty-state', 'timeline',
        'alert', 'stepper', 'skeleton', 'tabs', 'overlays', 'dropdown'] as $component) {
        $response->assertSee('data-component="'.$component.'"', false);
    }
    $response->assertSee('role="dialog"', false)
        ->assertSee('role="switch"', false)
        ->assertSee('aria-live="polite"', false)
        ->assertSee('rt-table', false);
});

it('renders each layout', function (string $layout, string $marker) {
    $this->get(route('app.styleguide', ['layout' => $layout]))
        ->assertOk()
        ->assertSee($marker, false);
})->with([
    'admin' => ['admin', 'lg:w-sidebar'],
    'portal' => ['portal', 'max-w-portal'],
    'public' => ['public', 'id="contact"'],
    'auth' => ['auth', 'max-w-[400px]'],
    'install' => ['install', 'max-w-2xl'],
]);

it('rejects an unknown layout', function () {
    $this->get(route('app.styleguide', ['layout' => 'nope']))->assertNotFound();
});

it('is not available in production', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->get(route('app.styleguide'))->assertNotFound();
});

it('applies the organization brand colour as CSS variables', function () {
    $this->get(route('app.styleguide'))
        ->assertSee('--color-primary-600: 26 115 232;', false);
});
