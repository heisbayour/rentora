<?php

declare(strict_types=1);

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use App\Support\Installation;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

/**
 * Web upgrader (spec 8.16): owner signed in plus ?key=RENTORA_INSTALL_KEY.
 * Runs pending migrations and clears caches, since hosts have no SSH.
 */
class UpgradeController extends Controller
{
    public function __construct(private readonly Migrator $migrator) {}

    public function show(Request $request): View
    {
        $this->guard($request);

        return view('system.upgrade', [
            'codeVersion' => config('rentora.version'),
            'installedVersion' => Installation::installedVersion(),
            'pending' => $this->pendingMigrations(),
            'key' => (string) $request->query('key'),
        ]);
    }

    public function run(Request $request, ActivityLogger $logger): RedirectResponse
    {
        $this->guard($request);

        $pending = $this->pendingMigrations();
        Artisan::call('migrate', ['--force' => true]);
        Artisan::call('optimize:clear');
        Installation::markInstalled((string) config('rentora.version'));
        $logger->log('system.upgraded', null, ['version' => config('rentora.version'), 'migrations' => $pending]);

        return redirect()->route('system.upgrade', ['key' => $request->input('key')])
            ->with('toast', __('upgrade.done', ['count' => count($pending)]));
    }

    private function guard(Request $request): void
    {
        $key = $request->query('key', $request->input('key'));
        abort_unless(Installation::keyMatches(is_string($key) ? $key : null), 403, __('install.key_required'));
    }

    /** @return list<string> */
    private function pendingMigrations(): array
    {
        $files = $this->migrator->getMigrationFiles([database_path('migrations')]);
        $repository = $this->migrator->getRepository();
        $ran = $repository->repositoryExists() ? $repository->getRan() : [];

        return array_values(array_diff(array_keys($files), $ran));
    }
}
