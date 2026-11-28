<?php

declare(strict_types=1);

namespace App\Services\System;

use App\Models\Organization;
use App\Models\SystemState;
use App\Services\Messaging\MessagingConfig;
use App\Services\Messaging\Sms\TermiiSmsDriver;
use App\Services\Payments\PaymentGateway;
use App\Support\CurrentOrganization;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * The "Connections" card on System health (DECISIONS 108): Email (SMTP),
 * SMS (Termii) and Paystack, each "not set up", "working" or "failing".
 * Checks never send a real message: an SMTP connect and login, a Termii
 * balance call and a Paystack GET /bank. Results are stored per
 * organization in system_state; the scheduler runs them once a day.
 */
class ConnectionChecks
{
    public const KINDS = ['email', 'sms', 'paystack'];

    public const NOT_SET_UP = 'not_set_up';

    public const WORKING = 'working';

    public const FAILING = 'failing';

    public function __construct(
        private readonly MessagingConfig $messaging,
        private readonly PaymentGateway $gateway,
        private readonly CurrentOrganization $current,
        private readonly SmtpProbe $smtp,
    ) {}

    /** @return array{status: string, error: ?string, detail: ?string, checked_at: ?string} */
    public function run(string $kind): array
    {
        $checkedAt = now()->toIso8601String();
        try {
            [$status, $error, $detail] = match ($kind) {
                'email' => $this->email(),
                'sms' => $this->sms(),
                'paystack' => $this->paystack(),
            };
        } catch (Throwable $e) {
            [$status, $error, $detail] = [self::FAILING, mb_substr($e->getMessage(), 0, 300), null];
        }

        $result = ['status' => $status, 'error' => $error, 'detail' => $detail, 'checked_at' => $checkedAt];
        SystemState::put($this->key($kind), (string) json_encode($result));

        return $result;
    }

    /** @return array<string, array{status: string, error: ?string, detail: ?string, checked_at: ?string}> */
    public function runAll(): array
    {
        $results = [];
        foreach (self::KINDS as $kind) {
            $results[$kind] = $this->run($kind);
        }

        return $results;
    }

    /** Runs every check for every organization (the daily scheduler task). */
    public function runForAllOrganizations(): int
    {
        $count = 0;
        foreach (Organization::query()->get() as $organization) {
            $this->current->runAs($organization, function () use (&$count): void {
                $this->runAll();
                $count++;
            });
        }

        return $count;
    }

    /**
     * Stored results. A connection that is not configured now shows "not set
     * up" even if an older check stored something else.
     *
     * @return array<string, array{status: string, error: ?string, detail: ?string, checked_at: ?Carbon}>
     */
    public function results(): array
    {
        $results = [];
        foreach (self::KINDS as $kind) {
            $stored = json_decode((string) SystemState::getValue($this->key($kind)), true);
            $stored = is_array($stored) ? $stored : [];
            $status = $this->configured($kind) ? (string) ($stored['status'] ?? '') : self::NOT_SET_UP;

            $results[$kind] = [
                'status' => $status === '' ? 'unchecked' : $status,
                'error' => isset($stored['error']) && is_string($stored['error']) ? $stored['error'] : null,
                'detail' => isset($stored['detail']) && is_string($stored['detail']) ? $stored['detail'] : null,
                'checked_at' => isset($stored['checked_at']) && is_string($stored['checked_at']) ? Carbon::parse($stored['checked_at']) : null,
            ];
        }

        return $results;
    }

    /** @return list<string> configured connections whose last check failed */
    public function failing(): array
    {
        $failing = [];
        foreach ($this->results() as $kind => $result) {
            if ($result['status'] === self::FAILING) {
                $failing[] = $kind;
            }
        }

        return $failing;
    }

    public function configured(string $kind): bool
    {
        return match ($kind) {
            'email' => $this->messaging->smtpConfigured(),
            'sms' => $this->messaging->smsConfigured(),
            'paystack' => $this->gateway->isConfigured(),
            default => false,
        };
    }

    /** @return array{0: string, 1: ?string, 2: ?string} */
    private function email(): array
    {
        if (! $this->messaging->smtpConfigured()) {
            return [self::NOT_SET_UP, null, null];
        }
        $this->smtp->check($this->messaging->mailer());

        return [self::WORKING, null, null];
    }

    /** @return array{0: string, 1: ?string, 2: ?string} */
    private function sms(): array
    {
        $driver = $this->messaging->smsDriver();
        if (! $this->messaging->smsConfigured() || ! $driver instanceof TermiiSmsDriver) {
            return [self::NOT_SET_UP, null, null];
        }
        $balance = $driver->balance();

        return $balance['ok'] ? [self::WORKING, null, $balance['detail']] : [self::FAILING, $balance['error'], null];
    }

    /** @return array{0: string, 1: ?string, 2: ?string} */
    private function paystack(): array
    {
        if (! $this->gateway->isConfigured()) {
            return [self::NOT_SET_UP, null, null];
        }
        $error = $this->gateway->connectionError();

        return $error === null ? [self::WORKING, null, null] : [self::FAILING, $error, null];
    }

    private function key(string $kind): string
    {
        return 'connection.'.($this->current->id() ?? 0).'.'.$kind;
    }
}
