<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;

/** Server checks shown by the installer (spec 8.16) and the system health page. */
class Requirements
{
    /**
     * @return list<array{label: string, ok: bool, detail: string}>
     */
    public function checks(): array
    {
        $required = (string) config('rentora.install.required_php');
        $checks = [[
            'label' => __('install.check_php', ['version' => $required]),
            'ok' => version_compare(PHP_VERSION, $required, '>='),
            'detail' => PHP_VERSION,
        ]];

        foreach ($this->requiredExtensions() as $extension) {
            $loaded = extension_loaded((string) $extension);
            $checks[] = [
                'label' => __('install.check_extension', ['name' => $extension]),
                'ok' => $loaded,
                'detail' => $loaded ? __('install.loaded') : __('install.missing'),
            ];
        }

        foreach ((array) config('rentora.install.writable') as $directory) {
            $path = base_path((string) $directory);
            if (! is_dir($path)) {
                @mkdir($path, 0755, true);
            }
            $writable = is_dir($path) && is_writable($path);
            $checks[] = [
                'label' => __('install.check_writable', ['path' => $directory]),
                'ok' => $writable,
                'detail' => $writable ? __('install.writable') : __('install.not_writable'),
            ];
        }

        return $checks;
    }

    /**
     * The configured list, with pdo_mysql swapped for the PDO driver of the
     * database actually in use (production is MySQL; tests use SQLite).
     *
     * @return list<string>
     */
    public function requiredExtensions(): array
    {
        $driver = (string) config('database.connections.'.config('database.default').'.driver', 'mysql');
        $pdo = 'pdo_'.($driver === 'mariadb' ? 'mysql' : $driver);

        return array_values(array_unique(array_map(
            fn ($extension) => $extension === 'pdo_mysql' ? $pdo : (string) $extension,
            (array) config('rentora.install.required_extensions'),
        )));
    }

    public function allPass(): bool
    {
        foreach ($this->checks() as $check) {
            if (! $check['ok']) {
                return false;
            }
        }

        return true;
    }

    /** Null when the database connects, otherwise the error message to show. */
    public function databaseError(): ?string
    {
        try {
            DB::connection()->getPdo();

            return null;
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }

    /** True when APP_DEBUG is on in production, which the installer warns about (spec 11). */
    public function debugWarning(): bool
    {
        return app()->isProduction() && (bool) config('app.debug');
    }
}
