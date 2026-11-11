<?php

declare(strict_types=1);

namespace App\Infrastructure\Notifications;

use App\Domain\Notifications\Contracts\PoolableSmsSender;
use App\Domain\Notifications\Contracts\SmsSender;
use App\Domain\Notifications\DeliveryResult;
use App\Domain\Notifications\HttpRequestSpec;
use App\Domain\Notifications\PhoneNumber;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Base des pilotes SMS REST (Vonage, Twilio) : numéro normalisé E.164, requête décrite pour le Http::pool du
 * DeliveryRunner (délai borné) ou envoyée par send(). Journal sans contenu ni numéro en clair (empreinte, longueur,
 * identifiant du message chez le fournisseur). Une erreur est un `failed()` rejoué par `notifications:retry`.
 */
abstract class HttpSmsSender implements PoolableSmsSender, SmsSender
{
    abstract protected function driver(): string;

    abstract protected function request(string $e164, string $text): ?HttpRequestSpec;

    /** @return array{ok: bool, error: string|null, id: string|null} */
    abstract protected function parse(int $status, string $body): array;

    public function send(string $to, string $text): DeliveryResult
    {
        try {
            $spec = $this->describeRequest($to, $text);

            if ($spec === null) {
                return $this->invalid($to);
            }

            $response = Http::withHeaders($spec->headers)
                ->timeout((float) config('xomiton.alerts.provider_timeout_seconds', 3))
                ->withBody((string) ($spec->body ?? json_encode($spec->json)), $spec->headers['Content-Type'] ?? 'application/json')
                ->send($spec->method, $spec->url);

            return $this->interpretResponse($response->status(), (string) $response->body());
        } catch (Throwable $e) {
            return $this->interpretTransportError($e::class);
        }
    }

    public function describeRequest(string $to, string $text): ?HttpRequestSpec
    {
        $e164 = PhoneNumber::e164($to);

        return $e164 === null ? null : $this->request($e164, $text);
    }

    public function interpretResponse(int $status, string $body): DeliveryResult
    {
        $parsed = $this->parse($status, $body);

        if ($parsed['ok']) {
            Log::info('sms.accepted', ['driver' => $this->driver(), 'message_id' => $parsed['id']]);

            return DeliveryResult::sent();
        }

        Log::warning('sms.failed', ['driver' => $this->driver(), 'error' => $parsed['error']]);

        return DeliveryResult::failed($this->driver().': '.($parsed['error'] ?? 'HTTP '.$status));
    }

    public function interpretTransportError(string $error): DeliveryResult
    {
        return DeliveryResult::failed($this->driver().': transport error '.mb_substr($error, 0, 80));
    }

    private function invalid(string $to): DeliveryResult
    {
        Log::warning('sms.invalid_number', ['driver' => $this->driver(), 'to' => PhoneNumber::fingerprint($to)]);

        return DeliveryResult::failed($this->driver().': invalid number or credentials missing');
    }

    protected function sender(): string
    {
        return (string) config('xomiton.sms.sender', 'Xomiton');
    }
}
