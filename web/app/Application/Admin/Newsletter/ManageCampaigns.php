<?php

declare(strict_types=1);

namespace App\Application\Admin\Newsletter;

use App\Application\Mail\SmtpSettings;
use App\Application\Newsletter\Campaigns\CampaignRenderer;
use App\Application\Newsletter\Campaigns\ProcessCampaignQueue;
use App\Domain\Newsletter\Exceptions\CommunicationRefused;
use App\Enums\CampaignStatus;
use App\Models\MailOutbox;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterInterest;
use App\Models\NewsletterSubscriber;
use App\Models\NewsletterTag;
use App\Models\User;
use App\Support\Audit;
use App\Support\EmailHtml;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * Campagnes de la newsletter en administration (P14-02, `newsletter.manage_campaigns` pour rédiger, `newsletter.send` pour
 * programmer ou lancer). RG-12 : une campagne ne vise que des abonné·es **actif·ves** du segment (tags, centres d'intérêt, dates) ;
 * une campagne programmée ou lancée doit viser au moins une personne. Une campagne « En cours » n'est ni modifiable ni
 * supprimable. Le lancement ne fait que planifier « maintenant » : `newsletter:process` (ou « Traiter la file d'envoi »)
 * dépose les lots, aucun SMTP dans la requête. Le journal d'audit ne contient ni objet, ni corps, ni adresse.
 */
final class ManageCampaigns
{
    public const SUBJECT_MIN = 3;

    public const SUBJECT_MAX = 150;

    public const BODY_MIN = 10;

    public const TEST_PER_DAY = 20;

    /**
     * @param  array{subject: string, from_name: string, reply_to: string, preheader?: ?string, body: string, segment?: array<string, mixed>, scheduled_at?: ?CarbonInterface}  $data
     * @param  'draft'|'schedule'|'now'  $mode
     *
     * @throws CommunicationRefused
     */
    public function save(?NewsletterCampaign $campaign, array $data, string $mode, User $actor): NewsletterCampaign
    {
        if ($campaign !== null && ! $campaign->isEditable()) {
            throw new CommunicationRefused('campaign_locked');
        }

        $subject = trim($data['subject']);
        $body = EmailHtml::clean($data['body']);

        if (mb_strlen($subject) < self::SUBJECT_MIN || mb_strlen($subject) > self::SUBJECT_MAX) {
            throw new CommunicationRefused('subject_invalid', ['min' => self::SUBJECT_MIN, 'max' => self::SUBJECT_MAX]);
        }

        if (mb_strlen(trim(strip_tags($body))) < self::BODY_MIN) {
            throw new CommunicationRefused('body_invalid', ['min' => self::BODY_MIN]);
        }

        self::assertKnownVariables($subject, (string) ($data['preheader'] ?? ''), $body);

        $segment = $this->cleanSegment($data['segment'] ?? []);
        $recipients = $this->segmentCount($segment);
        $status = CampaignStatus::Draft;
        $scheduledAt = null;

        if ($mode !== 'draft') {
            if ($recipients === 0) {
                throw new CommunicationRefused('segment_empty');
            }

            $scheduledAt = $mode === 'now' ? now() : ($data['scheduled_at'] ?? null);

            if ($scheduledAt === null || ($mode === 'schedule' && $scheduledAt->lt(now()->addMinute()))) {
                throw new CommunicationRefused('scheduled_past');
            }

            $status = CampaignStatus::Scheduled;
        }

        $attributes = [
            'subject' => $subject,
            'from_name' => trim($data['from_name']),
            'reply_to' => trim($data['reply_to']),
            'preheader' => ($pre = trim((string) ($data['preheader'] ?? ''))) === '' ? null : $pre,
            'body' => $body,
            'segment' => $segment,
            'status' => $status,
            'scheduled_at' => $scheduledAt,
        ];

        $campaign = $campaign === null
            ? NewsletterCampaign::query()->create($attributes + ['created_by' => $actor->getKey()])
            : tap($campaign)->forceFill($attributes)->save();

        $action = match ($mode) {
            'schedule' => 'newsletter.campaign_scheduled',
            'now' => 'newsletter.campaign_launched',
            default => 'newsletter.campaign_saved',
        };
        Audit::log($action, $campaign, sprintf('campagne=%d ; destinataires=%d', $campaign->getKey(), $recipients), $actor);

        return $campaign;
    }

    /**
     * @throws CommunicationRefused
     */
    public function cancelSchedule(NewsletterCampaign $campaign, User $actor): NewsletterCampaign
    {
        if ($campaign->status !== CampaignStatus::Scheduled) {
            throw new CommunicationRefused('campaign_state');
        }

        $campaign->forceFill(['status' => CampaignStatus::Draft, 'scheduled_at' => null])->save();
        Audit::log('newsletter.campaign_unscheduled', $campaign, 'campagne='.$campaign->getKey(), $actor);

        return $campaign;
    }

    public function duplicate(NewsletterCampaign $campaign, User $actor): NewsletterCampaign
    {
        $copy = NewsletterCampaign::query()->create([
            'subject' => Str::limit($campaign->subject, self::SUBJECT_MAX - 9, '').' (copie)',
            'from_name' => $campaign->from_name,
            'reply_to' => $campaign->reply_to,
            'preheader' => $campaign->preheader,
            'body' => $campaign->body,
            'segment' => $campaign->segment,
            'status' => CampaignStatus::Draft,
            'created_by' => $actor->getKey(),
        ]);
        Audit::log('newsletter.campaign_duplicated', $copy, sprintf('campagne=%d ; copie de %d', $copy->getKey(), $campaign->getKey()), $actor);

        return $copy;
    }

    /**
     * @throws CommunicationRefused une campagne en cours ne se supprime pas
     */
    public function delete(NewsletterCampaign $campaign, User $actor): void
    {
        if ($campaign->status === CampaignStatus::Sending) {
            throw new CommunicationRefused('campaign_locked');
        }

        $campaign->delete();
        Audit::log('newsletter.campaign_deleted', $campaign, 'campagne='.$campaign->getKey(), $actor);
    }

    /** « Traiter la file d'envoi » : démarre les campagnes échues et dépose les lots (aucun SMTP dans la requête). */
    public function processQueue(User $actor): int
    {
        $result = app(ProcessCampaignQueue::class)->run();
        Audit::log('newsletter.queue_processed', null, sprintf('démarrées=%d ; lots=%d', $result['started'], $result['batches']), $actor);

        return $result['started'] + $result['batches'];
    }

    /**
     * Aperçu rendu pour une personne fictive.
     *
     * @param  array{subject: string, preheader?: ?string, body: string}  $draft
     * @return array{subject: string, html: string}
     */
    public function preview(array $draft): array
    {
        $draft['body'] = EmailHtml::clean($draft['body']);
        $rendered = app(CampaignRenderer::class)->render($draft, self::fakeSubscriber('awa@exemple.bj'));

        return ['subject' => $rendered['subject'], 'html' => $rendered['html']];
    }

    /**
     * Envoi de test : déposé dans la boîte d'envoi (jamais de SMTP dans la requête), sans lien de suivi.
     *
     * @param  array{subject: string, preheader?: ?string, body: string, from_name: string, reply_to: string}  $draft
     *
     * @throws CommunicationRefused
     */
    public function sendTest(array $draft, string $to, User $actor): void
    {
        $to = trim($to);

        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            throw new CommunicationRefused('test_address_invalid');
        }

        $draft['body'] = EmailHtml::clean($draft['body']);

        if (mb_strlen(trim(strip_tags($draft['body']))) < self::BODY_MIN) {
            throw new CommunicationRefused('body_invalid', ['min' => self::BODY_MIN]);
        }

        self::assertKnownVariables((string) $draft['subject'], (string) ($draft['preheader'] ?? ''), $draft['body']);
        self::assertTestQuota($actor);

        $rendered = app(CampaignRenderer::class)->render($draft, self::fakeSubscriber($to));

        MailOutbox::query()->create([
            'to' => $to,
            'from_address' => SmtpSettings::fromAddress(),
            'from_name' => mb_substr(trim($draft['from_name']) !== '' ? trim($draft['from_name']) : SmtpSettings::fromName(), 0, 80),
            'reply_to' => trim($draft['reply_to']) !== '' ? trim($draft['reply_to']) : SmtpSettings::replyTo(),
            'subject' => mb_substr('[TEST] '.$rendered['subject'], 0, 160),
            'html' => $rendered['html'],
            'text' => $rendered['text'],
            'headers' => $rendered['headers'],
            'template_slug' => 'newsletter-test',
        ]);
        Audit::log('newsletter.campaign_test_queued', null, null, $actor);
    }

    /** Abonné·es actif·ves visé·es par le segment (comptage en direct de la modale). */
    public function segmentCount(array $segment): int
    {
        return NewsletterSubscriber::query()->inSegment($this->cleanSegment($segment))->count();
    }

    /**
     * Segment assaini : tags et centres d'intérêt connus, dates AAAA-MM-JJ.
     *
     * @param  array<string, mixed>  $segment
     * @return array{tags?: list<string>, interests?: list<string>, since?: string, until?: string}
     */
    public function cleanSegment(array $segment): array
    {
        $clean = [];

        $tags = NewsletterTag::query()->whereIn('slug', array_map('strval', (array) ($segment['tags'] ?? [])))->orderBy('slug')->pluck('slug')->map(static fn (mixed $s): string => (string) $s)->all();
        $interests = NewsletterInterest::query()->whereIn('slug', array_map('strval', (array) ($segment['interests'] ?? [])))->orderBy('slug')->pluck('slug')->map(static fn (mixed $s): string => (string) $s)->all();

        if ($tags !== []) {
            $clean['tags'] = $tags;
        }

        if ($interests !== []) {
            $clean['interests'] = $interests;
        }

        foreach (['since', 'until'] as $key) {
            $value = (string) ($segment[$key] ?? '');

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 && strtotime($value) !== false) {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }

    /**
     * Seules les variables de CampaignRenderer::VARIABLES (et celles de la plateforme) sont acceptées : une autre resterait
     * affichée telle quelle chez les abonné·es.
     *
     * @throws CommunicationRefused
     */
    private static function assertKnownVariables(string ...$texts): void
    {
        $known = array_keys(app(CampaignRenderer::class)->variables(self::fakeSubscriber('exemple@exemple.bj')));
        $unknown = array_values(array_diff(\App\Models\EmailTemplate::extractVariables(...$texts), $known));

        if ($unknown !== []) {
            throw new CommunicationRefused('variables_unknown', ['list' => implode(', ', $unknown)]);
        }
    }

    /** Plafond quotidien d'envois de test par compte (en plus des 5 par minute de l'écran) : pas de relais de spam. */
    private static function assertTestQuota(User $actor): void
    {
        if (! \Illuminate\Support\Facades\RateLimiter::attempt('newsletter-test-day:'.$actor->getKey(), self::TEST_PER_DAY, static fn (): bool => true, 86400)) {
            throw new CommunicationRefused('test_quota');
        }
    }

    private static function fakeSubscriber(string $email): NewsletterSubscriber
    {
        return new NewsletterSubscriber(['email' => $email, 'name' => 'Awa Houngbédji', 'token' => str_repeat('0', NewsletterSubscriber::TOKEN_LENGTH)]);
    }
}
