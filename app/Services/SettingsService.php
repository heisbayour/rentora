<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use App\Support\CurrentOrganization;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Organization settings stored in the settings table (spec 8.12). Secrets are
 * encrypted with Crypt (so APP_KEY must never change after install) and are
 * only ever shown masked. Callers pass the .env/config fallback as $default.
 */
class SettingsService
{
    /** @var array<int, array<string, Setting>> */
    private array $cache = [];

    public function __construct(
        private readonly CurrentOrganization $organization,
        private readonly ActivityLogger $logger,
    ) {}

    public function get(string $key, ?string $default = null): ?string
    {
        $setting = $this->all()[$key] ?? null;
        if ($setting === null || $setting->value === null || $setting->value === '') {
            return $default;
        }
        if (! $setting->is_encrypted) {
            return $setting->value;
        }
        try {
            return Crypt::decryptString($setting->value);
        } catch (DecryptException) {
            Log::warning('Setting could not be decrypted; was APP_KEY changed?', ['key' => $key]);

            return $default;
        }
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->get($key);

        return $value === null ? $default : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->get($key);

        return $value === null || ! is_numeric($value) ? $default : (int) $value;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function set(string $key, ?string $value, bool $encrypted = false): void
    {
        $organizationId = $this->organizationId();
        $stored = $value === null || $value === '' ? null : ($encrypted ? Crypt::encryptString($value) : $value);
        $previous = $this->get($key);

        $setting = Setting::query()->updateOrCreate(
            ['organization_id' => $organizationId, 'key' => $key],
            ['value' => $stored, 'is_encrypted' => $encrypted],
        );
        $this->cache[$organizationId][$key] = $setting;

        if ($previous !== $value) {
            // Never log the value itself: it may be a secret.
            $this->logger->log('settings.changed', null, ['key' => $key]);
        }
    }

    /**
     * Writes several settings. Keys listed in $encryptedKeys are stored encrypted.
     *
     * @param  array<string, ?string>  $values
     * @param  list<string>  $encryptedKeys
     */
    public function setMany(array $values, array $encryptedKeys = []): void
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, in_array($key, $encryptedKeys, true));
        }
    }

    public function forget(string $key): void
    {
        $organizationId = $this->organizationId();
        Setting::query()->where('organization_id', $organizationId)->where('key', $key)->delete();
        unset($this->cache[$organizationId][$key]);
    }

    /** A secret for display: only its last 4 characters, never the whole value. */
    public function masked(string $key): ?string
    {
        return self::mask($this->get($key));
    }

    public static function mask(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return str_repeat('*', 8).mb_substr($value, -4);
    }

    public function flush(): void
    {
        $this->cache = [];
    }

    /** @return array<string, Setting> */
    private function all(): array
    {
        $organizationId = $this->organization->id();
        if ($organizationId === null) {
            return [];
        }

        return $this->cache[$organizationId] ??= Setting::query()
            ->where('organization_id', $organizationId)
            ->get()
            ->keyBy('key')
            ->all();
    }

    private function organizationId(): int
    {
        $id = $this->organization->id();
        if ($id === null) {
            throw new \LogicException('Settings need a current organization.');
        }

        return $id;
    }
}
