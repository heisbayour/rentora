<?php

declare(strict_types=1);

namespace App\Services\Messaging\Sms;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Termii (spec 9.2): POST {base_url}/api/sms/send. Termii assigns a base URL
 * per account, so it is configurable in Settings.
 */
final class TermiiSmsDriver implements SmsDriver
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $senderId,
        private readonly string $channel,
        private readonly string $baseUrl,
    ) {}

    public function send(string $to, string $message): SmsResult
    {
        if ($this->apiKey === '' || $this->senderId === '') {
            return SmsResult::failed(__('messaging.errors.termii_not_configured'));
        }

        try {
            $response = Http::timeout(15)->acceptJson()->post(rtrim($this->baseUrl, '/').'/api/sms/send', [
                'api_key' => $this->apiKey,
                'to' => ltrim($to, '+'),
                'from' => $this->senderId,
                'sms' => $message,
                'type' => 'plain',
                'channel' => $this->channel,
            ]);
        } catch (ConnectionException $e) {
            return SmsResult::failed('Termii connection failed: '.$e->getMessage());
        }

        if ($response->successful() && $response->json('message_id') !== null) {
            return SmsResult::sent((string) $response->json('message_id'));
        }

        return SmsResult::failed('Termii '.$response->status().': '.mb_substr((string) ($response->json('message') ?? $response->body()), 0, 300));
    }

    /**
     * Balance call for the System health check: proves the key works without
     * sending a message.
     *
     * @return array{ok: bool, detail: ?string, error: ?string}
     */
    public function balance(): array
    {
        if ($this->apiKey === '') {
            return ['ok' => false, 'detail' => null, 'error' => __('messaging.errors.termii_not_configured')];
        }

        try {
            $response = Http::timeout(15)->acceptJson()->get(rtrim($this->baseUrl, '/').'/api/get-balance', ['api_key' => $this->apiKey]);
        } catch (ConnectionException $e) {
            return ['ok' => false, 'detail' => null, 'error' => 'Termii connection failed: '.$e->getMessage()];
        }

        if ($response->successful() && $response->json('balance') !== null) {
            return ['ok' => true, 'detail' => trim($response->json('balance').' '.$response->json('currency')), 'error' => null];
        }

        return ['ok' => false, 'detail' => null, 'error' => 'Termii '.$response->status().': '.mb_substr((string) ($response->json('message') ?? $response->body()), 0, 300)];
    }
}
