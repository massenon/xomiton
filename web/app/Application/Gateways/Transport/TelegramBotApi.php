<?php

declare(strict_types=1);

namespace App\Application\Gateways\Transport;

use App\Application\Gateways\OutboundMessage;
use App\Application\Gateways\PublicImage;
use App\Application\Gateways\PublishResult;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Canal Telegram de l'organisation par la Bot API (P14-04, P19-11) ; le bot doit être administrateur du canal.
 *  - 2 images ou plus : `sendMediaGroup` (album de photos, 10 au plus, fichiers joints `attach://photoN`), la légende
 *    (message + lien, 1024 caractères au plus) sur la **première** photo seulement ;
 *  - 1 image : `sendPhoto` avec la même légende ;
 *  - aucune image : `sendMessage` (message + lien, aperçu du lien affiché).
 * La Bot API n'a pas de texte alternatif pour une photo : `alt` est ignoré (il reste sur la page publique de la BD).
 * Le jeton est dans l'adresse (`/bot<jeton>/…`) : il n'apparaît jamais dans un message d'erreur (GatewayError) ni dans un
 * journal ; une exception de connexion est réduite à `transport_error` par ApiPublisher.
 */
final class TelegramBotApi
{
    public const CAPTION_MAX = 1024;

    public const MESSAGE_MAX = 4096;

    public const ALBUM_MAX = 10;

    public function publish(string $token, string $chatId, OutboundMessage $message): PublishResult
    {
        $photos = [];

        foreach (array_slice($message->media, 0, self::ALBUM_MAX) as $index => $item) {
            $file = PublicImage::prepare($item['path']);

            if ($file === null) {
                return GatewayError::failed(GatewayError::MEDIA_UNAVAILABLE, null, basename($item['path']));
            }

            $photos['photo'.$index] = $file;
        }

        $caption = $message->fitted(self::CAPTION_MAX);

        if (count($photos) >= 2) {
            $media = [];
            $request = $this->client();

            foreach (array_keys($photos) as $position => $name) {
                $media[] = array_filter([
                    'type' => 'photo',
                    'media' => 'attach://'.$name,
                    'caption' => $position === 0 ? $caption : null,
                ], static fn (mixed $value): bool => $value !== null);
                $request = $request->attach($name, $photos[$name]['bytes'], $photos[$name]['name'], ['Content-Type' => $photos[$name]['mime']]);
            }

            $response = $request->post($this->url($token, 'sendMediaGroup'), [
                'chat_id' => $chatId,
                'media' => (string) json_encode($media, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        } elseif (count($photos) === 1) {
            $photo = $photos['photo0'];
            $response = $this->client()
                ->attach('photo', $photo['bytes'], $photo['name'], ['Content-Type' => $photo['mime']])
                ->post($this->url($token, 'sendPhoto'), ['chat_id' => $chatId, 'caption' => $caption]);
        } else {
            $response = $this->client()->asForm()->post($this->url($token, 'sendMessage'), [
                'chat_id' => $chatId,
                'text' => $message->fitted(self::MESSAGE_MAX),
            ]);
        }

        return $this->result($response, $token, $chatId);
    }

    private function result(Response $response, string $token, string $chatId): PublishResult
    {
        $body = $response->json();
        $body = is_array($body) ? $body : [];

        if ($response->successful() && ($body['ok'] ?? false) === true) {
            $result = $body['result'] ?? [];
            $first = is_array($result) && array_is_list($result) ? ($result[0] ?? []) : $result;
            $messageId = is_array($first) ? (string) ($first['message_id'] ?? '') : '';

            return PublishResult::published($messageId === '' ? 'telegram' : $messageId, self::messageUrl($chatId, $messageId));
        }

        $status = (int) ($body['error_code'] ?? $response->status());
        $description = (string) ($body['description'] ?? '');
        $retryAfter = is_array($body['parameters'] ?? null) ? (int) ($body['parameters']['retry_after'] ?? 0) : 0;

        if ($retryAfter > 0) {
            $description = trim($description.' ; '.__('admin_gateways.delivery_errors.retry_after', ['seconds' => $retryAfter]));
        }

        return GatewayError::failed(GatewayError::codeForStatus($status), $status, $description, $token);
    }

    /** Lien public du message : `t.me/<canal>/<id>` (canal public) ou `t.me/c/<id interne>/<id>` (canal privé). */
    public static function messageUrl(string $chatId, string $messageId): ?string
    {
        if ($messageId === '') {
            return null;
        }

        if (str_starts_with($chatId, '@')) {
            return 'https://t.me/'.substr($chatId, 1).'/'.$messageId;
        }

        return str_starts_with($chatId, '-100') ? 'https://t.me/c/'.substr($chatId, 4).'/'.$messageId : null;
    }

    private function client(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::acceptJson()->timeout((int) config('xomiton.gateways.timeout', 30))->connectTimeout(10);
    }

    private function url(string $token, string $method): string
    {
        return rtrim((string) config('xomiton.gateways.telegram.base_url', 'https://api.telegram.org'), '/').'/bot'.$token.'/'.$method;
    }
}
