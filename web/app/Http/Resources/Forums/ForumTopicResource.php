<?php

declare(strict_types=1);

namespace App\Http\Resources\Forums;

use App\Application\Community\Forums\ForumLinks;
use App\Enums\PostStatus;
use App\Models\ForumTopic;
use App\Models\User;
use App\Policies\ForumTopicPolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sujet de forum tel qu'il sort de l'API (P8-05, docs/API.md §16) : identifiant public ULID (ADR-27), forum, titre,
 * auteur·rice (ForumAuthor : anonyme → « Membre anonyme », **aucun identifiant**, RG-02), `is_mine` pour la personne qui
 * lit seulement, compteurs (réponses, vues), dates, épinglé / fermé, mention `moderated` (son sujet masqué par la
 * modération, à elle seule) et gestes permis (`can`). Le message (`body`) n'est servi que par la fiche (`detailed()`) :
 * la liste du prototype n'affiche que le titre.
 *
 * @mixin ForumTopic
 */
final class ForumTopicResource extends JsonResource
{
    private bool $detailed = false;

    public function detailed(bool $detailed = true): self
    {
        $this->detailed = $detailed;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ForumTopic $topic */
        $topic = $this->resource;
        $viewer = $request->user('sanctum') ?? $request->user();
        $viewer = $viewer instanceof User ? $viewer : null;
        $isMine = $viewer !== null && $topic->user_id !== null && $topic->user_id === $viewer->getKey();
        $forum = $topic->forum;

        return [
            'id' => (string) $topic->ulid,
            'forum' => $forum !== null ? ['key' => (string) $forum->key, 'name' => (string) $forum->name, 'url' => ForumLinks::index($forum)] : null,
            'title' => (string) $topic->title,
            'body' => $this->when($this->detailed, (string) $topic->body),
            'author' => ForumAuthor::for($topic->user, (bool) $topic->is_anonymous),
            'is_mine' => $isMine,
            'is_anonymous' => (bool) $topic->is_anonymous,
            'is_pinned' => (bool) $topic->is_pinned,
            'is_locked' => (bool) $topic->is_locked,
            'moderated' => $isMine && $topic->status === PostStatus::Hidden,
            'replies_count' => (int) $topic->replies_count,
            'views_count' => (int) $topic->views_count,
            'created_at' => $topic->created_at?->toIso8601String(),
            'last_reply_at' => $topic->last_reply_at?->toIso8601String(),
            'url' => ForumLinks::show($topic),
            'can' => [
                // Le sujet servi est déjà visible (liste filtrée, fiche contrôlée) : aucune requête de blocage par sujet.
                'reply' => $viewer !== null && ! $topic->is_locked && ForumTopicPolicy::acceptsReplies($viewer, $topic),
                'delete' => $viewer !== null && $viewer->can('delete', $topic),
                'report' => $viewer !== null && ! $isMine,
            ],
        ];
    }
}
