<?php

declare(strict_types=1);

namespace App\Application\Moderation;

use App\Enums\ModerationKind;
use App\Enums\ModerationStatus;
use App\Models\Comment;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\ModerationReport;
use App\Models\Post;
use App\Models\User;
use App\Models\UserWarning;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Contexte d'un signalement pour la décision (P18-05) :
 *  - **fil** : la publication d'un commentaire (extrait de 200 caractères), le sujet et le forum d'une réponse, le forum
 *    d'un sujet, le groupe d'une publication. Un message privé n'a **aucun** contexte ici : seul le message signalé se lit,
 *    par l'action journalisée « Lire le message » (Q74) ; une conversation n'est jamais ouverte ;
 *  - **historique de modération de l'auteur·rice** : avertissements, sanctions par nature (contenus distincts),
 *    signalements reçus, statut du compte (suspendu, banni) et les 5 dernières décisions sur d'autres contenus (date,
 *    nature, décision) — jamais l'extrait de ces autres contenus.
 * Lecture seule ; l'appelant a déjà vérifié `moderation.manage` (ModerationReportPolicy).
 */
final class ReportContext
{
    public const PARENT_EXCERPT = 200;

    public const HISTORY_LIMIT = 5;

    /**
     * @return array{parent: array{type: string, title: string|null, excerpt: string|null}|null, history: array{warnings: int, reports: int, sanctions: array<string, int>, suspended: bool, banned: bool, recent: list<array{at: Carbon|string|null, kind: string, action: string|null}>}|null}
     */
    public function for(ModerationReport $report): array
    {
        return [
            'parent' => $this->parent($report),
            'history' => $report->reportedUser instanceof User ? $this->history($report, $report->reportedUser) : null,
        ];
    }

    /**
     * @return array{type: string, title: string|null, excerpt: string|null}|null
     */
    private function parent(ModerationReport $report): ?array
    {
        if ($report->getRawOriginal('kind') === ModerationKind::Message->value) {
            return null;
        }

        $subject = $report->subject;

        return match (true) {
            $subject instanceof Comment => $this->post($subject->post()->withTrashed()->first()),
            $subject instanceof ForumReply => $this->topic($subject->topic()->withTrashed()->first()),
            $subject instanceof ForumTopic => ['type' => 'forum', 'title' => $subject->forum?->name, 'excerpt' => null],
            $subject instanceof Post && $subject->group_id !== null => ['type' => 'group', 'title' => $subject->group?->name, 'excerpt' => null],
            default => null,
        };
    }

    /**
     * @return array{type: string, title: string|null, excerpt: string|null}|null
     */
    private function post(?Post $post): ?array
    {
        if ($post === null) {
            return null;
        }

        $text = trim((string) preg_replace('/\s+/u', ' ', (string) $post->body));

        return ['type' => 'post', 'title' => $post->user?->pseudo, 'excerpt' => $text === '' ? null : Str::limit($text, self::PARENT_EXCERPT)];
    }

    /**
     * @return array{type: string, title: string|null, excerpt: string|null}|null
     */
    private function topic(?ForumTopic $topic): ?array
    {
        return $topic === null ? null : ['type' => 'topic', 'title' => (string) $topic->title, 'excerpt' => $topic->forum?->name];
    }

    /**
     * @return array{warnings: int, reports: int, sanctions: array<string, int>, suspended: bool, banned: bool, recent: list<array{at: Carbon|string|null, kind: string, action: string|null}>}
     */
    private function history(ModerationReport $report, User $author): array
    {
        $sanctions = [];
        $rows = ModerationReport::query()->toBase()->where('reported_user_id', $author->getKey())
            ->whereIn('action', ReportPrioritizer::SANCTIONS)->distinct()->get(['action', 'subject_type', 'subject_id']);

        foreach ($rows as $row) {
            $sanctions[(string) $row->action] = ($sanctions[(string) $row->action] ?? 0) + 1;
        }

        $recent = ModerationReport::query()->where('reported_user_id', $author->getKey())
            ->where('status', '!=', ModerationStatus::Pending->value)
            ->where(fn ($query) => $query->where('subject_type', '!=', $report->subject_type)->orWhere('subject_id', '!=', $report->subject_id))
            ->orderByDesc('handled_at')->orderByDesc('id')->limit(self::HISTORY_LIMIT)
            ->get(['id', 'kind', 'action', 'handled_at'])
            ->map(static fn (ModerationReport $past): array => [
                'at' => $past->handled_at,
                'kind' => (string) $past->getRawOriginal('kind'),
                'action' => $past->action,
            ])->values()->all();
        $recent = array_values($recent);

        return [
            'warnings' => UserWarning::query()->forUser($author)->count(),
            'reports' => ModerationReport::query()->againstUser($author)->count(),
            'sanctions' => $sanctions,
            'suspended' => $author->isSuspended(),
            'banned' => $author->isBanned(),
            'recent' => $recent,
        ];
    }
}
