<?php

declare(strict_types=1);

namespace App\Application\Newsletter\Campaigns;

use App\Application\Mail\EmailTemplateService;
use App\Application\Mail\SmtpSettings;
use App\Domain\Newsletter\Events\CampaignSent;
use App\Enums\CampaignStatus;
use App\Mail\TemplateMail;
use App\Models\AuditLog;
use App\Models\MailOutbox;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterCampaignRecipient;
use App\Models\NewsletterSubscriber;
use App\Support\Audit;
use DateTimeImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Envoi d'une campagne par lots (P14-03, RG-11, RG-12) :
 *  - start() : une campagne planifiée et échue passe « En cours » (verrou par mise à jour conditionnelle) et ses
 *    destinataires sont figés une fois pour toutes (abonné·es **actifs** du segment, `newsletter_campaign_recipients`,
 *    clé unique campagne × abonné·e) ;
 *  - sendBatch() : au plus NewsletterCampaign::BATCH_SIZE e-mails par appel (200 par minute, tâche `newsletter:process`).
 *    Chaque destinataire est **réservé avant l'envoi** (`sent_at` posé par une mise à jour conditionnelle) : au plus un
 *    envoi par personne et par campagne, même si deux lots se chevauchent. Une personne désabonnée depuis la
 *    constitution de la liste est écartée au moment de l'envoi. Refus définitif du serveur : rebond (BounceRecorder) ;
 *    refus temporaire : nouvel essai au lot suivant, trois au plus. Sans transport disponible, l'e-mail est déposé dans
 *    `mail_outbox` (renvoyé par `mail:flush-outbox`). Aucune adresse n'est écrite dans les journaux.
 */
final class CampaignSender
{
    public const MAX_ATTEMPTS = 3;

    public function __construct(
        private readonly CampaignRenderer $renderer,
        private readonly BounceRecorder $bounces,
    ) {}

    /** Démarre la campagne (planifiée, échue) et fige ses destinataires ; faux si un autre processus l'a déjà démarrée. */
    public function start(NewsletterCampaign $campaign): bool
    {
        $claimed = NewsletterCampaign::query()
            ->whereKey($campaign->getKey())
            ->where('status', CampaignStatus::Scheduled->value)
            ->update(['status' => CampaignStatus::Sending->value, 'started_at' => now(), 'updated_at' => now()]);

        if ($claimed === 0) {
            return false;
        }

        $campaign->refresh();
        $now = now();

        $campaign->targetQuery()->select('newsletter_subscribers.id')->orderBy('newsletter_subscribers.id')->chunkById(500, function ($subscribers) use ($campaign, $now): void {
            NewsletterCampaignRecipient::query()->insertOrIgnore($subscribers->map(static fn (NewsletterSubscriber $s): array => [
                'campaign_id' => $campaign->getKey(),
                'subscriber_id' => $s->getKey(),
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());
        }, 'newsletter_subscribers.id', 'id');

        $campaign->forceFill(['recipients_count' => $campaign->recipients()->count()])->save();
        Audit::logWithoutRequest('newsletter.campaign_started', $campaign, 'destinataires='.$campaign->recipients_count, AuditLog::ACTOR_SYSTEM);

        return true;
    }

    /** Destinataires encore à servir. */
    public function pendingCount(NewsletterCampaign $campaign): int
    {
        return $this->pendingQuery($campaign)->count();
    }

    /**
     * @return array{sent: int, failed: int, bounced: int, skipped: int, completed: bool}
     */
    public function sendBatch(NewsletterCampaign $campaign, int $limit = NewsletterCampaign::BATCH_SIZE): array
    {
        $result = ['sent' => 0, 'failed' => 0, 'bounced' => 0, 'skipped' => 0, 'completed' => false];
        $campaign->refresh();

        if ($campaign->status !== CampaignStatus::Sending) {
            return $result;
        }

        $limit = max(1, min($limit, NewsletterCampaign::BATCH_SIZE));
        $mailer = EmailTemplateService::resolveMailer();
        $recipients = $this->pendingQuery($campaign)->with('subscriber')->orderBy('id')->limit($limit)->get();

        foreach ($recipients as $recipient) {
            // Réservation : une seule exécution obtient la ligne (au plus un envoi par personne et par campagne).
            $claimed = NewsletterCampaignRecipient::query()->whereKey($recipient->getKey())->whereNull('sent_at')->update(['sent_at' => now()]);

            if ($claimed === 0) {
                continue;
            }

            $subscriber = $recipient->subscriber;

            if ($subscriber === null || ! NewsletterSubscriber::query()->deliverable()->whereKey($subscriber->getKey())->exists()) {
                NewsletterCampaignRecipient::query()->whereKey($recipient->getKey())->update(['sent_at' => null, 'error' => 'exclu', 'updated_at' => now()]);
                $result['skipped']++;

                continue;
            }

            try {
                $this->deliver($campaign, $recipient, $subscriber, $mailer);
                $result['sent']++;
            } catch (Throwable $exception) {
                $code = BounceRecorder::errorCode($exception);

                if (BounceRecorder::isPermanent($exception)) {
                    $this->bounces->record((int) $recipient->getKey(), $code);
                    $result['bounced']++;

                    continue;
                }

                $attempts = $recipient->attempts + 1;
                // Mise à jour directe : `sent_at` repasse à null même si le modèle chargé le croyait déjà null.
                NewsletterCampaignRecipient::query()->whereKey($recipient->getKey())->update([
                    'sent_at' => null,
                    'attempts' => $attempts,
                    'error' => $attempts >= self::MAX_ATTEMPTS ? $code : null,
                    'updated_at' => now(),
                ]);
                $result['failed']++;
                Log::warning('Campagne : échec temporaire d’envoi.', ['campaign' => $campaign->getKey(), 'recipient' => $recipient->getKey(), 'code' => $code]);
            }
        }

        $result['completed'] = $this->completeIfDone($campaign);

        return $result;
    }

    /**
     * @param  \Illuminate\Contracts\Mail\Mailer|null  $mailer  null : dépôt dans la boîte d'envoi
     */
    private function deliver(NewsletterCampaign $campaign, NewsletterCampaignRecipient $recipient, NewsletterSubscriber $subscriber, ?\Illuminate\Contracts\Mail\Mailer $mailer): void
    {
        $payload = $this->renderer->render($campaign, $subscriber, $recipient);
        $mail = new TemplateMail(
            subjectLine: $payload['subject'],
            htmlBody: $payload['html'],
            textBody: $payload['text'],
            fromAddress: SmtpSettings::fromAddress(),
            fromName: (string) $campaign->from_name,
            replyToAddress: (string) $campaign->reply_to,
            templateSlug: 'newsletter',
            extraHeaders: $payload['headers'],
        );

        if ($mailer === null) {
            MailOutbox::query()->create([
                'to' => $subscriber->email,
                'from_address' => $mail->fromAddress,
                'from_name' => mb_substr($mail->fromName, 0, 80),
                'reply_to' => $mail->replyToAddress,
                'subject' => mb_substr($mail->subjectLine, 0, 160),
                'html' => $mail->htmlBody,
                'text' => $mail->textBody,
                'headers' => $payload['headers'],
                'template_slug' => 'newsletter',
                'newsletter_recipient_id' => $recipient->getKey(),
            ]);

            return;
        }

        $mailer->to($subscriber->email)->send($mail);
    }

    /** Campagne terminée quand plus personne n'attend : « Envoyée », compteurs figés, évènement `CampaignSent`. */
    private function completeIfDone(NewsletterCampaign $campaign): bool
    {
        if ($this->pendingCount($campaign) > 0) {
            return false;
        }

        $done = NewsletterCampaign::query()->whereKey($campaign->getKey())->where('status', CampaignStatus::Sending->value)
            ->update(['status' => CampaignStatus::Sent->value, 'sent_at' => now(), 'updated_at' => now()]);

        if ($done === 0) {
            return false;
        }

        $campaign->refresh();
        $campaign->forceFill(['sent_count' => $campaign->recipients()->whereNotNull('sent_at')->where('bounced', false)->count()])->save();
        CampaignSent::dispatch((int) $campaign->getKey(), (int) $campaign->recipients_count, (int) $campaign->sent_count, new DateTimeImmutable);

        return true;
    }

    /** @return \Illuminate\Database\Eloquent\Builder<NewsletterCampaignRecipient> */
    private function pendingQuery(NewsletterCampaign $campaign): \Illuminate\Database\Eloquent\Builder
    {
        return NewsletterCampaignRecipient::query()
            ->where('campaign_id', $campaign->getKey())
            ->whereNull('sent_at')->where('bounced', false)->whereNull('error');
    }
}
