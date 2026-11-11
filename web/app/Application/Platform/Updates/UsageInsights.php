<?php

declare(strict_types=1);

namespace App\Application\Platform\Updates;

use App\Models\AssistantConversation;
use App\Models\EventRsvp;
use App\Models\Message;
use App\Models\Post;
use App\Models\PostLike;
use App\Models\Resource;
use App\Models\SyncDevice;
use App\Models\Thematique;
use App\Models\User;
use App\Support\SmallCellMask;
use Illuminate\Database\Eloquent\Builder;

/**
 * Usage de la plateforme pour l'écran Mises à jour (P13-02) : contenus publics les plus consultés (compteurs
 * `views_count` des thématiques et des ressources publiées) et adoption des fonctionnalités par les membres actifs sur
 * 30 jours. Les comptes se lisent en agrégats : un effectif de moins de 5 est masqué (SmallCellMask, comme les indicateurs).
 */
final class UsageInsights
{
    public const WINDOW_DAYS = 30;

    /** Fonctionnalités mesurées, dans l'ordre d'affichage. */
    public const FEATURES = ['feed', 'messages', 'events', 'assistant', 'offline'];

    /**
     * @return list<array{label: string, kind: string, views: int}>
     */
    public function topContent(int $limit = 6): array
    {
        $items = [];

        foreach (Thematique::query()->published()->where('views_count', '>', 0)->orderByDesc('views_count')->limit($limit)->get(['title', 'views_count']) as $row) {
            $items[] = ['label' => (string) $row->title, 'kind' => 'thematique', 'views' => (int) $row->views_count];
        }

        foreach (Resource::query()->published()->where('views_count', '>', 0)->orderByDesc('views_count')->limit($limit)->get(['title', 'views_count']) as $row) {
            $items[] = ['label' => (string) $row->title, 'kind' => 'resource', 'views' => (int) $row->views_count];
        }

        usort($items, static fn (array $a, array $b): int => $b['views'] <=> $a['views']);

        return array_slice($items, 0, $limit);
    }

    /**
     * @return array{members: int, peers: int, items: list<array{key: string, users: int, base: int, percent: int|null}>}
     */
    public function adoption(): array
    {
        $since = now()->subDays(self::WINDOW_DAYS);
        $members = User::query()->active()->where('last_seen_at', '>=', $since)->count();
        $peers = User::query()->role('pair')->active()->count();

        $counts = [
            'feed' => User::query()->active()->where(fn (Builder $q) => $q
                ->whereIn('id', Post::query()->select('user_id')->whereNotNull('user_id')->where('created_at', '>=', $since))
                ->orWhereIn('id', PostLike::query()->select('user_id')->where('created_at', '>=', $since)))->count(),
            'messages' => User::query()->active()->whereIn('id', Message::query()->select('user_id')->whereNotNull('user_id')->where('created_at', '>=', $since))->count(),
            'events' => User::query()->active()->whereIn('id', EventRsvp::query()->select('user_id')->where('created_at', '>=', $since))->count(),
            'assistant' => User::query()->active()->whereIn('id', AssistantConversation::query()->select('user_id')->whereNotNull('user_id')->where('created_at', '>=', $since))->count(),
            'offline' => (int) SyncDevice::query()->active()->where('last_sync_at', '>=', $since)->distinct()->count('user_id'),
        ];

        $items = [];

        foreach (self::FEATURES as $key) {
            $base = $key === 'offline' ? $peers : $members;
            $users = $counts[$key];
            $items[] = [
                'key' => $key,
                'users' => $users,
                'base' => $base,
                'percent' => $base === 0 || SmallCellMask::isMasked($users) ? null : (int) min(100, round($users / $base * 100)),
            ];
        }

        return ['members' => $members, 'peers' => $peers, 'items' => $items];
    }

    /** Pair·es dont aucun appareil actif n'a synchronisé depuis `$days` jours (rappel conseillé). */
    public function peersWithoutSync(int $days = 7): int
    {
        $since = now()->subDays($days);

        return User::query()->role('pair')->active()
            ->whereDoesntHave('syncDevices', fn (Builder $q) => $q->where('is_revoked', false)->where('last_sync_at', '>=', $since))
            ->count();
    }
}
