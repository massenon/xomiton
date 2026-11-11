<?php

declare(strict_types=1);

namespace App\Http\Resources\Forums;

use App\Models\ForumReply;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Réponse à un sujet (P8-05, docs/API.md §16) : identifiant numérique (ADR-27 : comme un commentaire, une réponse ne
 * s'adresse jamais par URL), message, auteur·rice (ForumAuthor, RG-02), `is_mine`, date et gestes permis.
 *
 * @mixin ForumReply
 */
final class ForumReplyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ForumReply $reply */
        $reply = $this->resource;
        $viewer = $request->user('sanctum') ?? $request->user();
        $viewer = $viewer instanceof User ? $viewer : null;
        $isMine = $viewer !== null && $reply->user_id !== null && $reply->user_id === $viewer->getKey();

        return [
            'id' => (int) $reply->getKey(),
            'body' => (string) $reply->body,
            'author' => ForumAuthor::for($reply->user, (bool) $reply->is_anonymous),
            'is_mine' => $isMine,
            'is_anonymous' => (bool) $reply->is_anonymous,
            'created_at' => $reply->created_at?->toIso8601String(),
            'can' => [
                'delete' => $isMine || ($viewer?->can('posts.moderate') ?? false),
                'report' => $viewer !== null && ! $isMine,
            ],
        ];
    }
}
