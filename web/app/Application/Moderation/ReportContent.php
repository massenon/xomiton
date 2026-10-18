<?php

declare(strict_types=1);

namespace App\Application\Moderation;

use App\Application\Feed\FeedCounters;
use App\Domain\Feed\ValueObjects\ContentVerdict;
use App\Domain\Moderation\AutoHideRule;
use App\Domain\Moderation\Events\ContentReported;
use App\Enums\ContentReportReason;
use App\Enums\ModerationKind;
use App\Enums\ModerationStatus;
use App\Enums\PostStatus;
use App\Models\AuditLog;
use App\Models\Comment;
use App\Models\ModerationReport;
use App\Models\Post;
use App\Models\User;
use App\Support\Audit;
use App\Support\Settings;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Signalement d'une publication ou d'un commentaire (P7-02, FR-SOC-06) → `moderation_reports` (file de modération,
 * P12) + événement ContentReported (cloche des comptes `moderation.manage`, App\Application\Moderation\Listeners\NotifyModerators).
 *
 *  - une personne ne signale un contenu qu'une fois : un second envoi renvoie le signalement existant, sans double compte
 *    (contrôle et insertion sous verrou de la ligne du sujet, `lockForUpdate`) ;
 *  - `posts.reports_count` compte les signalements des membres (jamais ceux du filtre automatique) ;
 *  - RG-16 (AutoHideRule) : au seuil de signalements **en attente** de personnes distinctes, le contenu est masqué
 *    (`status = masque`, motif lisible par l'équipe), journalisé `moderation.auto_hidden` par le système ;
 *  - l'extrait (255 car.) est figé pour rester lisible si le contenu est supprimé ; il ne sort jamais dans une notification.
 */
final class ReportContent
{
    /** Surcharge administrable du seuil RG-16 (`settings.key`), à défaut `config('xomiton.feed.auto_hide_after_reports')`. */
    public const SETTING_THRESHOLD = 'moderation_auto_hide_reports';

    private const EXCERPT_LENGTH = 250;

    public function __construct(private readonly FeedCounters $counters) {}

    public static function autoHideRule(): AutoHideRule
    {
        $default = (int) config('xomiton.feed.auto_hide_after_reports', 3);

        try {
            $threshold = Settings::has(self::SETTING_THRESHOLD) ? Settings::int(self::SETTING_THRESHOLD, $default) : $default;
        } catch (Throwable) {
            $threshold = $default;
        }

        return new AutoHideRule(max(0, $threshold));
    }

    public function byMember(User $reporter, Post|Comment $subject, ContentReportReason $reason, ?string $details = null): ReportOutcome
    {
        /** @var array{0: ModerationReport, 1: bool, 2: bool} $result */
        $result = DB::transaction(function () use ($reporter, $subject, $reason, $details): array {
            // Verrou sur la ligne du sujet : deux envois simultanés (même personne, ou deux personnes au seuil RG-16)
            // passent l'un après l'autre, sans doublon ni seuil franchi sans masquage.
            $subject->newQuery()->whereKey($subject->getKey())->lockForUpdate()->value($subject->getKeyName());

            $existing = ModerationReport::query()->forSubject($subject)->where('reporter_user_id', $reporter->getKey())->first();

            if ($existing !== null) {
                return [$existing, false, false];
            }

            $report = $this->createReport($subject, [
                'reporter_user_id' => $reporter->getKey(),
                'reason' => $reason->label(),
                'details' => $details === null || trim($details) === '' ? null : Str::limit(trim($details), 500, ''),
            ]);

            if ($subject instanceof Post) {
                $this->counters->increment($subject, 'reports_count');
            }

            return [$report, true, $this->applyAutoHide($subject)];
        });

        [$report, $created, $hidden] = $result;

        if ($created) {
            $this->dispatch($report, $reporter);
        }

        return new ReportOutcome($report, created: $created, hidden: $hidden);
    }

    /**
     * Signalement ouvert par le filtre de mots (`bad_words` « signaler ») : le contenu reste publié, l'équipe le relit.
     * Les termes trouvés ne sont lisibles que dans le détail du signalement (jamais renvoyés à l'auteur·rice).
     */
    public function automatic(Post|Comment $subject, ContentVerdict $verdict): ModerationReport
    {
        $report = $this->createReport($subject, [
            'reporter_user_id' => null,
            'reporter_label' => (string) __('feed.moderation.auto_reporter'),
            'reason' => ContentReportReason::Automatic->label(),
            'details' => Str::limit((string) __('feed.moderation.auto_details', ['terms' => implode(', ', $verdict->flaggingTerms)]), 500, ''),
        ]);

        $this->dispatch($report, null);

        return $report;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createReport(Post|Comment $subject, array $attributes): ModerationReport
    {
        $kind = $subject instanceof Post ? ModerationKind::Post : ModerationKind::Comment;

        return ModerationReport::query()->create($attributes + [
            'kind' => $kind,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'excerpt' => self::excerpt($subject),
            'reported_user_id' => $subject->user_id,
            'status' => ModerationStatus::Pending,
        ]);
    }

    private function applyAutoHide(Post|Comment $subject): bool
    {
        $rule = self::autoHideRule();
        $subject->refresh();

        if (! $rule->isEnabled()) {
            return false;
        }

        $pending = ModerationReport::query()->forSubject($subject)->pending()
            ->whereNotNull('reporter_user_id')->distinct()->count('reporter_user_id');

        if (! $rule->shouldHide($pending, $subject->status === PostStatus::Published)) {
            return false;
        }

        if ($subject instanceof Post) {
            $subject->forceFill([
                'status' => PostStatus::Hidden,
                'hidden_reason' => Str::limit((string) __('feed.moderation.auto_hidden_reason', ['count' => $pending]), 200, ''),
            ])->save();
        } else {
            $subject->forceFill(['status' => PostStatus::Hidden])->save();

            if ($subject->post !== null) {
                $this->counters->recountComments($subject->post);
            }
        }

        Audit::logWithoutRequest('moderation.auto_hidden', $subject, sprintf('signalements=%d ; seuil=%d', $pending, $rule->threshold), AuditLog::ACTOR_SYSTEM);

        return true;
    }

    private function dispatch(ModerationReport $report, ?User $reporter): void
    {
        ContentReported::dispatch(
            (int) $report->getKey(),
            $report->kind->value,
            (string) $report->reason,
            $reporter?->getKey(),
            new DateTimeImmutable,
        );
    }

    private static function excerpt(Post|Comment $subject): ?string
    {
        $text = trim((string) $subject->body);

        if ($text === '' && $subject instanceof Post) {
            $text = trim((string) $subject->poll?->question);
        }

        return $text === '' ? null : Str::limit((string) preg_replace('/\s+/u', ' ', $text), self::EXCERPT_LENGTH);
    }
}
