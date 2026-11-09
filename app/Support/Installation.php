<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\File;

/**
 * The install lock file (spec 8.16). Its presence means the installer has
 * finished; it records the installed version for the upgrader.
 */
final class Installation
{
    public static function lockPath(): string
    {
        return storage_path((string) config('rentora.install.lock_file'));
    }

    public static function isInstalled(): bool
    {
        return is_file(self::lockPath());
    }

    public static function installedVersion(): ?string
    {
        $data = self::read();

        return isset($data['version']) ? (string) $data['version'] : null;
    }

    /** @return array<string, mixed> */
    public static function read(): array
    {
        if (! self::isInstalled()) {
            return [];
        }
        $decoded = json_decode((string) file_get_contents(self::lockPath()), true);

        return is_array($decoded) ? $decoded : [];
    }

    public static function markInstalled(?string $version = null): void
    {
        $path = self::lockPath();
        File::ensureDirectoryExists(dirname($path));
        $existing = self::read();
        file_put_contents($path, (string) json_encode([
            'version' => $version ?? config('rentora.version'),
            'installed_at' => $existing['installed_at'] ?? now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ], JSON_PRETTY_PRINT));
    }

    public static function forget(): void
    {
        if (self::isInstalled()) {
            unlink(self::lockPath());
        }
    }

    public static function keyMatches(?string $given): bool
    {
        $expected = (string) config('rentora.install.key');

        return $expected !== '' && $given !== null && hash_equals($expected, $given);
    }
}
