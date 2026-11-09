<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\Role;
use App\Models\User;
use App\Services\SettingsService;
use App\Support\CurrentOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CurrentOrganization::class);
        $this->app->scoped(SettingsService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);
        Model::preventLazyLoading(! $this->app->isProduction());
        Password::defaults(fn () => Password::min(8));

        // Layouts live in resources/views/layouts and are used as <x-layouts::admin>.
        Blade::anonymousComponentPath(resource_path('views/layouts'), 'layouts');

        $this->defineGates();
    }

    /** Area-level permissions (spec 1.2). Per-record rules live in app/Policies. */
    private function defineGates(): void
    {
        Gate::define('manage-settings', fn (User $user) => $user->hasRole(Role::Owner));
        Gate::define('manage-staff', fn (User $user) => $user->hasRole(Role::Owner));
        Gate::define('view-system-health', fn (User $user) => $user->hasRole(Role::Owner));
        Gate::define('view-audit-log', fn (User $user) => $user->hasRole(Role::Owner));
        Gate::define('manage-money', fn (User $user) => $user->hasRole(Role::Owner, Role::Manager));
        Gate::define('manage-portfolio', fn (User $user) => $user->hasRole(Role::Owner, Role::Manager));
        Gate::define('reverse-payments', fn (User $user) => $user->hasRole(Role::Owner));
        Gate::define('use-maintenance', fn (User $user) => $user->hasRole(Role::Owner, Role::Manager, Role::Caretaker));
    }
}
