<?php

declare(strict_types=1);

namespace App\Application\Moderation;

use App\Application\Admin\Users\AccountSuspension;
use App\Domain\Moderation\Exceptions\ModerationRefused;
use App\Enums\ModerationStatus;
use App\Models\ModerationReport;
use App\Models\User;
use App\Models\UserWarning;
use App\Notifications\ModerationNotice;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Décision de modération sur un signalement (P12-06, FR-SOC-06, RG-16) : `moderation.manage` (ModerationReportPolicy
 * `handle`). Une décision vaut pour **tous les signalements en attente du même contenu** (le contenu masqué après trois
 * signalements, RG-16, se règle une fois) :
 *  - ignorer : signalements rejetés (action `aucune`) ; un contenu masqué automatiquement est rendu visible, sauf s'il a
 *    été masqué par une décision de la modération (il faut alors N nouveaux signalements pour le masquer de nouveau) ;
 *  - masquer : contenu retiré (ContentVisibility), auteur·rice prévenu·e ; non proposé pour un compte signalé ;
 *  - avertir : une ligne `user_warnings` (compteur de l'auteur·rice) et une notification, motif obligatoire (≤ 200) ;
 *  - suspendre : compte suspendu (AccountSuspension), **jamais un compte d'administration** (`admin.access`), motif
 *    obligatoire ; sessions et jetons fermés.
 * Chaque décision est journalisée (`moderation.dismissed|hidden|warned|suspended`, sans l'extrait du contenu) ; rouvrir
 * (`moderation.reopened`) remet le signalement en attente sans défaire l'avertissement ni la suspension ; « rendre
 * visible » (`moderation.unhidden`) est une action distincte. Toute la décision tient dans une transaction.
 */
final class DecideReport
{
    public const DISMISS = 'dismiss';

    public const HIDE = 'hide';

    public const WARN = 'warn';

    public const SUSPEND = 'suspend';

    /** Décision => valeur de `moderation_reports.action`. */
    public const ACTIONS = [
        self::DISMISS => ModerationReport::ACTION_NONE,
        self::HIDE => ModerationReport::ACTION_HIDE,
        self::WARN => ModerationReport::ACTION_WARN,
        self::SUSPEND => ModerationReport::ACTION_SUSPEND,
    ];

    public const REASON_MAX = 200;

    public function __construct(
        private readonly ContentVisibility $visibility,
        private readonly AccountSuspension $suspension,
    ) {}

    /**
     * @return int nombre de signalements clos par la décision
     *
     * @throws ModerationRefused
     */
    public function __invoke(ModerationReport $report, string $decision, User $by, ?string $reason = null): int
    {
        Gate::forUser($by)->authorize('handle', $report);

        if (! isset(self::ACTIONS[$decision])) {
            throw new ModerationRefused('invalid_decision');
        }

        $report->refresh();

        if (! $report->isPending()) {
            throw new ModerationRefused('already_handled');
        }

        $reason = $reason === null ? '' : Str::limit(trim($reason), self::REASON_MAX, '');
        $author = $report->reportedUser;
        $subject = $report->subject;

        if (in_array($decision, [self::WARN, self::SUSPEND], true)) {
            if ($author === null) {
                throw new ModerationRefused('no_author');
            }

            if ($reason === '') {
                throw new ModerationRefused('reason_required');
            }
        }

        if ($decision === self::SUSPEND && $author?->can('admin.access')) {
            throw new ModerationRefused('admin_target');
        }

        if ($decision === self::HIDE && ! ContentVisibility::hideable($subject)) {
            throw new ModerationRefused('not_hideable');
        }

        $closed = DB::transaction(function () use ($report, $decision, $by, $reason, $author, $subject): int {
            $notice = null;

            switch ($decision) {
                case self::DISMISS:
                    if ($subject !== null && ContentVisibility::isHidden($subject) && ! $this->hiddenByDecision($report)) {
                        $this->visibility->show($subject);
                    }

                    break;
                case self::HIDE:
                    $this->visibility->hide($subject, $reason !== '' ? $reason : (string) __('admin.moderation.hidden_reason'));
                    $notice = $author === null ? null : new ModerationNotice(ModerationNotice::CONTENT_HIDDEN, null, (int) $report->getKey());

                    break;
                case self::WARN:
                    UserWarning::query()->create([
                        'user_id' => $author->getKey(),
                        'issued_by' => $by->getKey(),
                        'reason' => $reason,
                        'moderation_report_id' => $report->getKey(),
                    ]);
                    $notice = new ModerationNotice(ModerationNotice::WARNING, $reason, (int) $report->getKey(), UserWarning::query()->forUser($author)->count());

                    break;
                case self::SUSPEND:
                    // La suspension notifie elle-même la personne.
                    $this->suspension->suspend($author, $by, $reason);

                    break;
            }

            $closed = ModerationReport::query()
                ->where('subject_type', $report->subject_type)
                ->where('subject_id', $report->subject_id)
                ->pending()
                ->update([
                    'status' => $decision === self::DISMISS ? ModerationStatus::Rejected : ModerationStatus::Handled,
                    'action' => self::ACTIONS[$decision],
                    'handled_by' => $by->getKey(),
                    'handled_at' => now(),
                    'updated_at' => now(),
                ]);

            if ($notice !== null && $author !== null) {
                $author->notify($notice);
            }

            return $closed;
        });

        Audit::log('moderation.'.self::AUDIT[$decision], $report, sprintf(
            'rapport=%d ; nature=%s ; signalements=%d%s',
            $report->getKey(),
            $report->kind->value,
            $closed,
            $reason === '' ? '' : ' ; motif='.$reason,
        ), $by);

        return $closed;
    }

    private const AUDIT = [
        self::DISMISS => 'dismissed',
        self::HIDE => 'hidden',
        self::WARN => 'warned',
        self::SUSPEND => 'suspended',
    ];

    /**
     * Rouvre un signalement traité ou rejeté : de nouveau en attente, sans défaire l'avertissement ni la suspension.
     *
     * @throws ModerationRefused
     */
    public function reopen(ModerationReport $report, User $by): void
    {
        Gate::forUser($by)->authorize('handle', $report);
        $report->refresh();

        if ($report->isPending()) {
            throw new ModerationRefused('not_handled');
        }

        $report->forceFill(['status' => ModerationStatus::Pending, 'action' => null, 'handled_by' => null, 'handled_at' => null])->save();

        Audit::log('moderation.reopened', $report, sprintf('rapport=%d ; nature=%s', $report->getKey(), $report->kind->value), $by);
    }

    /**
     * Rend visible le contenu d'un signalement (masqué par RG-16 ou par une décision) sans toucher au signalement.
     *
     * @throws ModerationRefused
     */
    public function unhide(ModerationReport $report, User $by): void
    {
        Gate::forUser($by)->authorize('handle', $report);

        if ($report->subject === null || ! $this->visibility->show($report->subject)) {
            throw new ModerationRefused('not_hidden');
        }

        Audit::log('moderation.unhidden', $report, sprintf('rapport=%d ; nature=%s', $report->getKey(), $report->kind->value), $by);
    }

    /** Une décision « masquer » de la modération existe-t-elle déjà pour ce contenu ? */
    private function hiddenByDecision(ModerationReport $report): bool
    {
        return ModerationReport::query()
            ->where('subject_type', $report->subject_type)
            ->where('subject_id', $report->subject_id)
            ->where('action', ModerationReport::ACTION_HIDE)
            ->exists();
    }
}
