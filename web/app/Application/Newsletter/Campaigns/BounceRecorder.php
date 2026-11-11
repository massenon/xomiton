<?php

declare(strict_types=1);

namespace App\Application\Newsletter\Campaigns;

use App\Models\AuditLog;
use App\Models\NewsletterCampaignRecipient;
use App\Support\Audit;
use Throwable;

/**
 * Rebonds de campagne (P14-03, RG-11) : un refus **définitif** du serveur (5xx : adresse ou boîte inexistante) marque le
 * destinataire en rebond et l'abonné·e en `rebond` — cette adresse n'est plus jamais ciblée. Un refus temporaire n'est pas
 * un rebond. Rien n'est écrit avec l'adresse : ni message brut du serveur (il la cite), ni journal.
 */
final class BounceRecorder
{
    /** Codes SMTP d'un refus définitif de l'adresse (552 « boîte pleine » reste temporaire). */
    private const PERMANENT_CODES = [550, 551, 553, 554];

    public function record(int $recipientId, string $code): void
    {
        $recipient = NewsletterCampaignRecipient::query()->with('subscriber')->find($recipientId);

        if ($recipient === null) {
            return;
        }

        $recipient->forceFill(['bounced' => true, 'error' => mb_substr($code, 0, 255)])->save();
        $subscriber = $recipient->subscriber;

        if ($subscriber !== null && $subscriber->status !== \App\Enums\SubscriberStatus::Bounced) {
            $subscriber->markBounced($code);
        }

        Audit::logWithoutRequest('newsletter.bounced', $subscriber, sprintf('campagne=%d ; code=%s', $recipient->campaign_id, $code), AuditLog::ACTOR_SYSTEM);
    }

    /** Code SMTP lu dans l'exception (« got code "550" »), sinon null. */
    public static function smtpCode(Throwable $exception): ?int
    {
        $message = $exception->getMessage();

        // « Expected response code "250" but got code "550" » : c'est la réponse reçue (« got code »), pas l'attendue.
        return preg_match('/got code\s+"?(\d{3})"?/i', $message, $matches) === 1 || preg_match('/^\D*(\d{3})\b/', $message, $matches) === 1 ? (int) $matches[1] : null;
    }

    public static function isPermanent(Throwable $exception): bool
    {
        return in_array(self::smtpCode($exception), self::PERMANENT_CODES, true);
    }

    /** Code consigné à la place du message du serveur : `smtp_550`, `smtp_error` si illisible. */
    public static function errorCode(Throwable $exception): string
    {
        $code = self::smtpCode($exception);

        return $code === null ? 'smtp_error' : 'smtp_'.$code;
    }

    /** Adresse masquée pour les journaux (campagnes). */
    public static function maskAddress(string $address): string
    {
        return $address === '' ? '' : '***';
    }
}
