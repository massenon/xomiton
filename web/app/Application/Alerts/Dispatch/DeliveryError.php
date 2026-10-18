<?php

declare(strict_types=1);

namespace App\Application\Alerts\Dispatch;

use Throwable;

/**
 * Message d'erreur consigné (`alert_notifications.last_error`, affiché en administration, et journaux) : classe de
 * l'exception et message, sans donnée qui identifie la personne : URL réduites à leur hôte (un jeton FCM ou un
 * endpoint Web Push figure dans le chemin), adresses e-mail et numéros de téléphone masqués (un fournisseur ou le
 * SMTP peut recopier le destinataire dans son erreur), 255 caractères au plus.
 */
final class DeliveryError
{
    public const MASK = '[masqué]';

    private function __construct() {}

    public static function fromThrowable(Throwable $e): string
    {
        return self::mask(class_basename($e).' : '.$e->getMessage());
    }

    public static function mask(string $message): string
    {
        $masked = (string) preg_replace_callback(
            '~\b([a-z][a-z0-9+.\-]*)://([^/\s?#@"\'<>]+@)?([^/\s:?#"\'<>]+)(:\d+)?([^\s"\'<>]*)~iu',
            static fn (array $m): string => $m[1].'://'.$m[3].($m[5] !== '' ? '/'.self::MASK : ''),
            $message,
        );
        $masked = (string) preg_replace('/[^\s@<>"\'():;,]+@[^\s@<>"\'():;,]+\.[a-z]{2,}/iu', self::MASK, $masked);
        // Numéro : au moins 8 chiffres (un code SMTP « 550 5.1.1 » ou une durée restent lisibles).
        $masked = (string) preg_replace_callback(
            '/\+?\d(?:[\d\s.\-]{6,}\d)/u',
            static fn (array $m): string => preg_match_all('/\d/', $m[0]) >= 8 ? self::MASK : $m[0],
            $masked,
        );

        return mb_substr(trim($masked), 0, 255);
    }
}
