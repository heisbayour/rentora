<?php

declare(strict_types=1);

namespace App\Http\Controllers\System;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\System\InstallOrganizationRequest;
use App\Models\Organization;
use App\Models\User;
use App\Support\CurrentOrganization;
use App\Support\Installation;
use App\Support\Requirements;
use Database\Seeders\DefaultsSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Web installer (spec 8.16): requirements, database check, migrations,
 * organization and owner, then the lock file. Guarded by InstallerGuard.
 */
class InstallController extends Controller
{
    public function __construct(private readonly Requirements $requirements) {}

    public function show(Request $request): View
    {
        $databaseError = $this->requirements->databaseError();
        $migrated = $databaseError === null && $this->migrated();
        $stage = match (true) {
            ! $this->requirements->allPass() || $databaseError !== null => 'requirements',
            ! $migrated => 'migrate',
            default => 'organization',
        };

        return view('install.show', [
            'stage' => $stage,
            'checks' => $this->requirements->checks(),
            'databaseError' => $databaseError,
            'debugWarning' => $this->requirements->debugWarning(),
            'key' => (string) $request->query('key', $request->input('key')),
        ]);
    }

    public function migrate(Request $request): RedirectResponse
    {
        abort_unless($this->requirements->allPass() && $this->requirements->databaseError() === null, 422);

        Artisan::call('migrate', ['--force' => true]);
        (new DefaultsSeeder)->run();

        return redirect()->route('install.show', ['key' => $request->input('key')])
            ->with('toast', __('install.migrated'));
    }

    public function store(InstallOrganizationRequest $request, CurrentOrganization $current): RedirectResponse
    {
        abort_unless($this->migrated(), 422);

        DB::transaction(function () use ($request, $current): void {
            $organization = Organization::query()->create([
                'name' => $request->validated('organization_name'),
                'slug' => Str::slug((string) $request->validated('organization_name')) ?: 'organization',
                'primary_color' => config('rentora.defaults.primary_color'),
                'currency' => config('rentora.defaults.currency'),
                'timezone' => config('rentora.defaults.timezone'),
                'email' => Str::lower((string) $request->validated('email')),
            ]);
            $current->set($organization);

            User::query()->create([
                'organization_id' => $organization->getKey(),
                'name' => $request->validated('name'),
                'email' => Str::lower((string) $request->validated('email')),
                'phone' => $request->validated('phone'),
                'password' => $request->validated('password'),
                'role' => Role::Owner,
                'is_active' => true,
            ]);
        });

        Installation::markInstalled();

        return redirect()->route('login')->with('toast', __('install.finished'));
    }

    private function migrated(): bool
    {
        try {
            return Schema::hasTable('organizations') && Schema::hasTable('users');
        } catch (\Throwable) {
            return false;
        }
    }
}
