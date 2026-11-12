<?php

declare(strict_types=1);

namespace App\Application\Gateways\Transport;

use App\Application\Gateways\PublishResult;

/**
 * Message d'erreur d'un réseau, court et lisible, rangé dans `outbound_deliveries.error` (P19-11) :
 * `<code> (HTTP <statut>) : <description du réseau>`. Le code est traduit à l'écran (`admin_gateways.delivery_errors`),
 * la description reste celle du réseau. Le jeton est retiré de la description (au cas où un réseau le recopierait),
 * toute adresse aussi (l'URL de la Bot API contient le jeton) ; jamais la réponse brute.
 *
 * Codes : `unauthorized` (jeton refusé ou permissions manquantes), `rate_limited` (limite de débit : la remise est
 * reprise plus tard sans compter de tentative), `rejected` (requête refusée), `server_error` (panne du réseau),
 * `transport_error` (réseau injoignable), `media_unavailable` (image illisible).
 */
final class GatewayError
{
    public const UNAUTHORIZED = 'unauthorized';

    public const RATE_LIMITED = 'rate_limited';

    public const REJECTED = 'rejected';

    public const SERVER_ERROR = 'server_error';

    public const TRANSPORT_ERROR = 'transport_error';

    public const MEDIA_UNAVAILABLE = 'media_unavailable';

    public static function failed(string $code, ?int $status = null, ?string $description = null, string $token = ''): PublishResult
    {
        return PublishResult::failed(self::format($code, $status, $description, $token));
    }

    /** Code d'après le statut HTTP. */
    public static function codeForStatus(int $status): string
    {
        return match (true) {
            $status === 401, $status === 403 => self::UNAUTHORIZED,
            $status === 429 => self::RATE_LIMITED,
            $status >= 500 => self::SERVER_ERROR,
            default => self::REJECTED,
        };
    }

    public static function format(string $code, ?int $status = null, ?string $description = null, string $token = ''): string
    {
        $detail = trim((string) $description);

        if ($token !== '') {
            $detail = str_replace($token, '***', $detail);
        }

        $detail = (string) preg_replace('~https?://\S+~i', '[adresse]', $detail);
        $detail = (string) preg_replace('/\s+/u', ' ', $detail);
        $detail = mb_substr($detail, 0, 180);
        $head = $code.($status === null ? '' : ' (HTTP '.$status.')');

        return mb_substr($detail === '' ? $head : $head.' : '.$detail, 0, 255);
    }

    /** Code d'une erreur enregistrée (`rate_limited (HTTP 429) : …` → `rate_limited`). */
    public static function codeOf(?string $error): string
    {
        return (string) preg_replace('/[\s:(].*$/su', '', trim((string) $error));
    }
}
