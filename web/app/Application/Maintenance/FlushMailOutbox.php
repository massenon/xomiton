<?php

declare(strict_types=1);

namespace App\Application\Maintenance;

use App\Application\Newsletter\Campaigns\BounceRecorder;
use App\Enums\MailTransport;
use App\Models\MailLog;
use App\Models\MailOutbox;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Renvoi des e-mails déposés dans `mail_outbox` faute de SMTP (P1-16, P3-01) : dès qu'un transport réel est
 * configuré (`MAIL_MAILER` autre que `log` / `array`), les e-mails en attente sont expédiés dans l'ordre de
 * dépôt, par lots de 50 au plus par exécution ; chaque tentative incrémente `attempts`, consigne `last_error`
 * et laisse une trace dans `mail_logs`. Idempotent : un e-mail envoyé (`sent_at`) n'est jamais renvoyé.
 */
final class FlushMailOutbox
{
    public const DEFAULT_LIMIT = 50;

    /** Mailers qui n'acheminent rien : la boîte d'envoi reste en attente. */
    public const INERT_MAILERS = ['log', 'array'];

    public const LOG_CONTEXT = 'outbox';

    public function mailer(): string
    {
        return (string) config('mail.default', 'log');
    }

    public function transportAvailable(): bool
    {
        $mailer = $this->mailer();

        return $mailer !== '' && ! in_array($mailer, self::INERT_MAILERS, true);
    }

    public function run(int $limit = self::DEFAULT_LIMIT): OutboxFlushResult
    {
        $limit = max(1, $limit);

        if (! $this->transportAvailable()) {
            return OutboxFlushResult::unavailable($this->mailer(), MailOutbox::query()->flushable()->count());
        }

        $sent = 0;
        $failed = 0;

        MailOutbox::query()->flushable()->limit($limit)->get()->each(function (MailOutbox $mail) use (&$sent, &$failed): void {
            if ($this->deliver($mail)) {
                $sent++;
            } else {
                $failed++;
            }
        });

        if ($sent + $failed > 0) {
            Log::info('Boîte d’envoi : e-mails traités.', ['sent' => $sent, 'failed' => $failed, 'mailer' => $this->mailer()]);
        }

        return new OutboxFlushResult(
            $this->mailer(),
            true,
            $sent,
            $failed,
            MailOutbox::query()->flushable()->count(),
        );
    }

    private function deliver(MailOutbox $mail): bool
    {
        // Campagne : une personne désabonnée (ou suspendue) depuis le dépôt ne reçoit plus rien.
        if ($mail->newsletter_recipient_id !== null && ! $this->recipientStillReachable((int) $mail->newsletter_recipient_id)) {
            $mail->forceFill(['attempts' => MailOutbox::MAX_ATTEMPTS, 'last_error' => 'exclu'])->save();

            return false;
        }

        try {
            Mail::html((string) $mail->html, function (Message $message) use ($mail): void {
                $message->to($mail->to)
                    ->from($mail->from_address, $mail->from_name)
                    ->subject($mail->subject);

                if ($mail->reply_to !== null && $mail->reply_to !== '') {
                    $message->replyTo($mail->reply_to);
                }

                if ($mail->text !== null && $mail->text !== '') {
                    $message->text($mail->text);
                }

                foreach ((array) $mail->headers as $name => $value) {
                    $message->getHeaders()->addTextHeader((string) $name, (string) $value);
                }
            });
        } catch (Throwable $exception) {
            $newsletter = $mail->newsletter_recipient_id !== null;
            // Campagne : jamais le message brut du serveur (il cite l'adresse), seulement un code.
            $error = $newsletter ? BounceRecorder::errorCode($exception) : $exception->getMessage();
            $mail->markFailed($error);
            $this->trace($mail, false, $error);

            if ($newsletter && BounceRecorder::isPermanent($exception)) {
                app(BounceRecorder::class)->record((int) $mail->newsletter_recipient_id, $error);
                // Adresse inexistante : inutile de réessayer.
                $mail->forceFill(['attempts' => MailOutbox::MAX_ATTEMPTS])->save();
            }

            Log::warning('Boîte d’envoi : échec d’envoi.', [
                'mail_outbox_id' => $mail->getKey(),
                'attempts' => $mail->attempts,
                'error' => $error,
            ]);

            return false;
        }

        $mail->markSent();
        $this->trace($mail, true);

        return true;
    }

    private function recipientStillReachable(int $recipientId): bool
    {
        $subscriberId = \App\Models\NewsletterCampaignRecipient::query()->whereKey($recipientId)->value('subscriber_id');

        return $subscriberId !== null && \App\Models\NewsletterSubscriber::query()->deliverable()->whereKey($subscriberId)->exists();
    }

    private function trace(MailOutbox $mail, bool $ok, ?string $error = null): void
    {
        MailLog::query()->create([
            // Une campagne ne laisse jamais l'adresse du destinataire dans les journaux.
            'to' => $mail->newsletter_recipient_id !== null ? BounceRecorder::maskAddress((string) $mail->to) : $mail->to,
            // L'objet d'une campagne est personnalisé (prénom) : il n'est pas conservé dans les journaux.
            'subject' => $mail->newsletter_recipient_id !== null ? '[newsletter]' : mb_substr((string) $mail->subject, 0, 160),
            'template_slug' => $mail->template_slug,
            'context' => self::LOG_CONTEXT,
            'transport' => MailTransport::Smtp,
            'ok' => $ok,
            'error' => $error === null ? null : mb_substr($error, 0, 255),
            'message_id' => null,
        ]);
    }
}
