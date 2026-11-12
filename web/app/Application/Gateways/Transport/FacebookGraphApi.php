<?php

declare(strict_types=1);

namespace App\Application\Gateways\Transport;

use App\Application\Gateways\OutboundMessage;
use App\Application\Gateways\PublicImage;
use App\Application\Gateways\PublishResult;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Page Facebook de l'organisation par la Graph API (P14-04, P19-11) ; jeton **de page** longue durée (permissions
 * `pages_manage_posts`, `pages_read_engagement`), envoyé dans l'en-tête `Authorization`, jamais dans l'adresse.
 *  - 2 images ou plus (`xomiton.gateways.facebook.multi_photo`, par défaut) : chaque image est téléversée **non publiée**
 *    (`POST /{page}/photos`, `published=false`, texte alternatif `alt_text_custom`), puis une seule publication les
 *    réunit (`POST /{page}/feed`, `message` = message + lien, `attached_media[n]={"media_fbid":…}`) ;
 *  - **repli documenté** : si cette voie est refusée (statut 400 hors jeton et limite de débit), ou s'il n'y a qu'une
 *    image, une seule photo publiée (`POST /{page}/photos`, `caption` = message + lien) — le visuel de partage ;
 *  - aucune image : `POST /{page}/feed` avec `message` et `link`.
 * Les photos téléversées non publiées et restées orphelines après un repli n'apparaissent pas sur la page.
 */
final class FacebookGraphApi
{
    public function publish(string $token, string $pageId, OutboundMessage $message): PublishResult
    {
        $files = [];

        foreach ($message->media as $item) {
            $file = PublicImage::prepare($item['path']);

            if ($file === null) {
                return GatewayError::failed(GatewayError::MEDIA_UNAVAILABLE, null, basename($item['path']));
            }

            $files[] = $file + ['alt' => $item['alt']];
        }

        if ($files === []) {
            $response = $this->client($token)->asForm()->post($this->url($pageId.'/feed'), array_filter([
                'message' => $message->text,
                'link' => $message->link,
            ], static fn (?string $value): bool => $value !== null && $value !== ''));

            return $this->postResult($response, $token);
        }

        if (count($files) >= 2 && (bool) config('xomiton.gateways.facebook.multi_photo', true)) {
            $result = $this->multiPhoto($token, $pageId, $message, $files);

            if ($result instanceof PublishResult) {
                return $result;
            }
        }

        return $this->singlePhoto($token, $pageId, $message, $files[0]);
    }

    /**
     * Publication multi-photos ; null quand la voie est refusée et que le repli « une photo » doit être tenté.
     *
     * @param  list<array{bytes: string, mime: string, name: string, alt: string}>  $files
     */
    private function multiPhoto(string $token, string $pageId, OutboundMessage $message, array $files): ?PublishResult
    {
        $attached = [];

        foreach ($files as $file) {
            $response = $this->client($token)
                ->attach('source', $file['bytes'], $file['name'], ['Content-Type' => $file['mime']])
                ->post($this->url($pageId.'/photos'), array_filter([
                    'published' => 'false',
                    'alt_text_custom' => mb_substr($file['alt'], 0, 1000),
                ], static fn (string $value): bool => $value !== ''));

            $id = (string) ($response->json('id') ?? '');

            if (! $response->successful() || $id === '') {
                return $this->fallbackOrFail($response, $token);
            }

            $attached[] = $id;
        }

        $fields = ['message' => $message->fullText()];

        foreach ($attached as $index => $id) {
            $fields['attached_media['.$index.']'] = (string) json_encode(['media_fbid' => $id]);
        }

        $response = $this->client($token)->asForm()->post($this->url($pageId.'/feed'), $fields);

        return $response->successful() && (string) ($response->json('id') ?? '') !== ''
            ? $this->postResult($response, $token)
            : $this->fallbackOrFail($response, $token);
    }

    /** @param  array{bytes: string, mime: string, name: string, alt: string}  $file */
    private function singlePhoto(string $token, string $pageId, OutboundMessage $message, array $file): PublishResult
    {
        $response = $this->client($token)
            ->attach('source', $file['bytes'], $file['name'], ['Content-Type' => $file['mime']])
            ->post($this->url($pageId.'/photos'), array_filter([
                'caption' => $message->fullText(),
                'published' => 'true',
                'alt_text_custom' => mb_substr($file['alt'], 0, 1000),
            ], static fn (string $value): bool => $value !== ''));

        return $this->postResult($response, $token);
    }

    /** Refus « de contenu » (400 hors jeton et débit) : repli ; sinon échec immédiat. */
    private function fallbackOrFail(Response $response, string $token): ?PublishResult
    {
        $failure = $this->failure($response, $token);

        return GatewayError::codeOf($failure->error) === GatewayError::REJECTED ? null : $failure;
    }

    private function postResult(Response $response, string $token): PublishResult
    {
        $id = (string) ($response->json('post_id') ?? $response->json('id') ?? '');

        if (! $response->successful() || $id === '') {
            return $this->failure($response, $token);
        }

        return PublishResult::published($id, 'https://www.facebook.com/'.rawurlencode($id));
    }

    private function failure(Response $response, string $token): PublishResult
    {
        $status = $response->status();
        $error = $response->json('error');
        $error = is_array($error) ? $error : [];
        $code = (int) ($error['code'] ?? 0);
        $kind = match (true) {
            in_array($code, [4, 17, 32, 613], true) => GatewayError::RATE_LIMITED,
            in_array($code, [10, 190, 200], true) => GatewayError::UNAUTHORIZED,
            default => GatewayError::codeForStatus($status),
        };
        $description = trim((string) ($error['message'] ?? '').($code > 0 ? ' (code '.$code.')' : ''));

        return GatewayError::failed($kind, $status, $description, $token);
    }

    private function client(string $token): PendingRequest
    {
        return Http::acceptJson()->withToken($token)->timeout((int) config('xomiton.gateways.timeout', 30))->connectTimeout(10);
    }

    private function url(string $path): string
    {
        $base = rtrim((string) config('xomiton.gateways.facebook.base_url', 'https://graph.facebook.com'), '/');

        return $base.'/'.trim((string) config('xomiton.gateways.facebook.graph_version', 'v21.0'), '/').'/'.$path;
    }
}
