<?php

declare(strict_types=1);

namespace App\Application\Import;

use App\Enums\CampaignStatus;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterCampaignRecipient;
use App\Models\NewsletterClick;
use App\Models\NewsletterSubscriber;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * newsletter_campaigns.json → newsletter_campaigns (segment : intérêts en slugs, status, dates, created_by
 * e-mail → FK) + newsletter_campaign_recipients (recipients / opens / bounces / unsubs éclatés par
 * abonné·e) + newsletter_clicks (url, compteur). Clé naturelle : sujet + date de création.
 */
final class ImportNewsletterCampaigns extends AbstractImporter
{
    public static function collection(): string
    {
        return 'newsletter_campaigns';
    }

    public static function isDemo(): bool
    {
        return true;
    }

    protected function importRow(array $row, int $index): void
    {
        $prototypeId = $this->prototypeId($row, $index);
        $subject = $this->required($row['subject'] ?? null, 'subject', 150);
        $createdAt = $this->ctx->dateTime($row['created'] ?? null) ?? now();
        $status = CampaignStatus::fromPrototype((string) ($row['status'] ?? 'brouillon'));
        $sentAt = $this->ctx->dateTime($row['sent_at'] ?? null);
        $recipients = array_values(array_filter(array_map('intval', is_array($row['recipients'] ?? null) ? $row['recipients'] : [])));

        $campaign = NewsletterCampaign::withTrashed()
            ->where('subject', $subject)
            ->where('created_at', $createdAt)
            ->first() ?? new NewsletterCampaign;

        $campaign->fill([
            'subject' => $subject,
            'from_name' => $this->text($row['from_name'] ?? null, 60) ?? 'Xomiton',
            'reply_to' => $this->text($row['reply_to'] ?? null, 190) ?? (string) config('mail.from.address', 'contact@xomiton.bj'),
            'preheader' => $this->text($row['preheader'] ?? null, 150),
            'body' => $this->required($row['body'] ?? null, 'body'),
            'segment' => $this->segment(is_array($row['segment'] ?? null) ? $row['segment'] : []),
            'status' => $status,
            'scheduled_at' => $this->ctx->dateTime($row['scheduled_at'] ?? null),
            'started_at' => $this->ctx->dateTime($row['started_at'] ?? null),
            'sent_at' => $sentAt,
            'recipients_count' => count($recipients),
            'sent_count' => $status === CampaignStatus::Sent ? count($recipients) : 0,
            'created_by' => $this->ctx->userIdByEmail((string) ($row['created_by'] ?? '')),
        ]);
        $campaign->created_at = $createdAt;
        $campaign->deleted_at = null;
        $campaign->save();

        $this->syncRecipients($campaign, $row, $recipients, $sentAt, $prototypeId);

        $this->ctx->remember('campaigns', $prototypeId, (int) $campaign->getKey());
        $this->track($campaign);
    }

    /**
     * Segment du prototype (`{tags[], interests[] en libellés}`) → `{tags[], interests[] en slugs, since, until}`.
     *
     * @param  array<string, mixed>  $segment
     * @return array<string, mixed>
     */
    private function segment(array $segment): array
    {
        $slugs = static fn (mixed $values): array => array_values(array_filter(array_map(
            static fn (mixed $value): string => Str::slug(trim((string) $value)),
            is_array($values) ? $values : [],
        )));

        return [
            'tags' => $slugs($segment['tags'] ?? []),
            'interests' => $slugs($segment['interests'] ?? []),
            'since' => $this->text($segment['since'] ?? null),
            'until' => $this->text($segment['until'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<int>  $recipients
     */
    private function syncRecipients(NewsletterCampaign $campaign, array $row, array $recipients, ?Carbon $sentAt, string|int $prototypeId): void
    {
        $opens = is_array($row['opens'] ?? null) ? $row['opens'] : [];
        $clicks = is_array($row['clicks'] ?? null) ? $row['clicks'] : [];
        $bounces = array_map('intval', is_array($row['bounces'] ?? null) ? $row['bounces'] : []);
        $unsubs = array_map('intval', is_array($row['unsubs'] ?? null) ? $row['unsubs'] : []);

        foreach ($recipients as $subscriberPrototypeId) {
            $subscriberId = $this->ctx->subscriberId($subscriberPrototypeId);

            if ($subscriberId === null) {
                $this->ctx->note(sprintf('campagne #%s : abonné·e #%d inconnu·e, destinataire ignoré·e', (string) $prototypeId, $subscriberPrototypeId));

                continue;
            }

            $openedAt = $this->ctx->dateTime($opens[(string) $subscriberPrototypeId] ?? $opens[$subscriberPrototypeId] ?? null);
            $bounced = in_array($subscriberPrototypeId, $bounces, true);

            $recipient = NewsletterCampaignRecipient::query()->updateOrCreate(
                ['campaign_id' => $campaign->getKey(), 'subscriber_id' => $subscriberId],
                [
                    'sent_at' => $sentAt,
                    'opened_at' => $openedAt,
                    'bounced' => $bounced,
                    'unsubscribed' => in_array($subscriberPrototypeId, $unsubs, true),
                    'error' => $bounced ? 'rebond' : null,
                ],
            );

            if ($openedAt !== null) {
                NewsletterSubscriber::query()->whereKey($subscriberId)
                    ->where(fn ($query) => $query->whereNull('last_opened_at')->orWhere('last_opened_at', '<', $openedAt))
                    ->update(['last_opened_at' => $openedAt]);
            }

            $urls = $clicks[(string) $subscriberPrototypeId] ?? $clicks[$subscriberPrototypeId] ?? [];

            foreach (array_count_values(array_map('strval', is_array($urls) ? $urls : [])) as $url => $count) {
                $url = trim((string) $url);

                if ($url === '') {
                    continue;
                }

                NewsletterClick::query()->updateOrCreate(
                    ['recipient_id' => $recipient->getKey(), 'url_hash' => NewsletterClick::hashUrl($url)],
                    ['url' => $url, 'clicked_at' => $openedAt ?? $sentAt ?? now(), 'clicks_count' => $count],
                );
            }
        }
    }
}
