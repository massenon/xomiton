<?php

declare(strict_types=1);

namespace App\Infrastructure\Notifications;

use App\Domain\Notifications\HttpRequestSpec;

/**
 * Pilote SMS Twilio, API REST `Messages.json` (SMS_DRIVER=twilio), authentification Basic (SID, jeton). Expéditeur :
 * `TWILIO_MESSAGING_SERVICE_SID` s'il est défini, sinon `SMS_SENDER` (numéro E.164 ou nom alphanumérique).
 * REST via Http plutôt que `twilio/sdk` : installation Composer impossible dans l'environnement de développement
 * (accès GitHub refusé), et l'API REST s'intègre au Http::pool borné du DeliveryRunner.
 */
final class TwilioSmsSender extends HttpSmsSender
{
    protected function driver(): string
    {
        return 'twilio';
    }

    protected function request(string $e164, string $text): ?HttpRequestSpec
    {
        $sid = config('xomiton.sms.twilio.account_sid');
        $token = config('xomiton.sms.twilio.auth_token');

        if (! is_string($sid) || $sid === '' || ! is_string($token) || $token === '') {
            return null;
        }

        $service = config('xomiton.sms.twilio.messaging_service_sid');
        $form = ['To' => $e164, 'Body' => $text] + (is_string($service) && $service !== '' ? ['MessagingServiceSid' => $service] : ['From' => $this->sender()]);

        return new HttpRequestSpec(
            method: 'POST',
            url: sprintf('https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json', rawurlencode($sid)),
            headers: ['Authorization' => 'Basic '.base64_encode($sid.':'.$token), 'Content-Type' => 'application/x-www-form-urlencoded'],
            body: http_build_query($form),
        );
    }

    protected function parse(int $status, string $body): array
    {
        $json = json_decode($body, true);
        $json = is_array($json) ? $json : [];

        if ($status >= 200 && $status < 300) {
            return ['ok' => true, 'error' => null, 'id' => (string) ($json['sid'] ?? '')];
        }

        return ['ok' => false, 'error' => sprintf('HTTP %d code %s', $status, $json['code'] ?? '?'), 'id' => null];
    }
}
