<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The running version (FINISH_BUILD Section 6). The version comes from the
 * VERSION file through config('rentora.version'); the commit and build date
 * come from build-info.json, written by scripts/build-deploy.php. In a local
 * checkout without that file the commit is read from .git.
 */
final class AppVersion
{
    /** @var array{version?: string, commit?: string, built_at?: string}|null */
    private static ?array $info = null;

    public static function version(): string
    {
        return (string) config('rentora.version');
    }

    public static function commit(): ?string
    {
        return self::info()['commit'] ?? self::gitCommit();
    }

    public static function builtAt(): ?string
    {
        return self::info()['built_at'] ?? null;
    }

    /** "Rentora v1.0.0 (abc1234)" */
    public static function label(): string
    {
        $commit = self::commit();

        return config('rentora.name').' v'.self::version().($commit ? ' ('.$commit.')' : '');
    }

    public static function flush(): void
    {
        self::$info = null;
    }

    /** @return array{version?: string, commit?: string, built_at?: string} */
    private static function info(): array
    {
        if (self::$info !== null) {
            return self::$info;
        }
        $path = base_path('build-info.json');
        $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        return self::$info = is_array($data) ? array_filter($data, 'is_string') : [];
    }

    private static function gitCommit(): ?string
    {
        $head = base_path('.git/HEAD');
        if (! is_file($head)) {
            return null;
        }
        $ref = trim((string) file_get_contents($head));
        if (str_starts_with($ref, 'ref: ')) {
            $file = base_path('.git/'.substr($ref, 5));
            $ref = is_file($file) ? trim((string) file_get_contents($file)) : '';
        }

        return preg_match('/^[0-9a-f]{40}$/', $ref) === 1 ? substr($ref, 0, 7) : null;
    }
}
