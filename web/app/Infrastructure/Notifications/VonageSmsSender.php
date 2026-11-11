<?php

declare(strict_types=1);

namespace App\Infrastructure\Notifications;

use App\Domain\Notifications\HttpRequestSpec;

/**
 * Pilote SMS Vonage (ex-Nexmo), API SMS REST `rest.nexmo.com/sms/json` (SMS_DRIVER=vonage). Une réponse HTTP 200 porte
 * un statut par segment : « 0 » = accepté, sinon `error-text` (solde, expéditeur refusé, numéro…).
 * REST via Http plutôt que `vonage/client-core` : installation Composer impossible dans l'environnement de
 * développement (accès GitHub refusé), et l'API REST s'intègre au Http::pool borné du DeliveryRunner.
 */
final class VonageSmsSender extends HttpSmsSender
{
    protected function driver(): string
    {
        return 'vonage';
    }

    protected function request(string $e164, string $text): ?HttpRequestSpec
    {
        $key = config('xomiton.sms.vonage.api_key');
        $secret = config('xomiton.sms.vonage.api_secret');

        if (! is_string($key) || $key === '' || ! is_string($secret) || $secret === '') {
            return null;
        }

        return new HttpRequestSpec(
            method: 'POST',
            url: 'https://rest.nexmo.com/sms/json',
            headers: ['Content-Type' => 'application/json'],
            json: [
                'api_key' => $key,
                'api_secret' => $secret,
                'from' => $this->sender(),
                'to' => ltrim($e164, '+'),
                'text' => $text,
            ],
        );
    }

    protected function parse(int $status, string $body): array
    {
        $json = json_decode($body, true);
        $messages = is_array($json) ? (array) ($json['messages'] ?? []) : [];

        if ($status !== 200 || $messages === []) {
            return ['ok' => false, 'error' => 'HTTP '.$status, 'id' => null];
        }

        foreach ($messages as $message) {
            if ((string) ($message['status'] ?? '') !== '0') {
                return ['ok' => false, 'error' => sprintf('status %s %s', $message['status'] ?? '?', mb_substr((string) ($message['error-text'] ?? ''), 0, 80)), 'id' => null];
            }
        }

        return ['ok' => true, 'error' => null, 'id' => (string) ($messages[0]['message-id'] ?? '')];
    }
}
