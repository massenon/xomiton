<?php

declare(strict_types=1);

namespace App\Http\Resources\Feed;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Commentaire d'une publication (P7-02) : identifiant numérique (ADR-27 : les commentaires n'ont pas d'ULID, ils ne
 * s'adressent jamais par URL), parent éventuel (encore publié), corps, auteur·rice (AuthorPayload, RG-02), date et droits utiles.
 *
 * @mixin Comment
 */
final class CommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Comment $comment */
        $comment = $this->resource;
        $viewer = $request->user('sanctum') ?? $request->user();
        $viewer = $viewer instanceof User ? $viewer : null;
        $isMine = $viewer !== null && $comment->user_id !== null && $comment->user_id === $viewer->getKey();
        // Liste (FeedQuery::comments) : parent chargé seulement s'il est encore publié ; sinon la réponse remonte au
        // premier niveau plutôt que de pointer vers un commentaire supprimé ou masqué.
        $parentId = $comment->relationLoaded('parent') ? $comment->parent?->getKey() : $comment->parent_id;

        return [
            'id' => (int) $comment->getKey(),
            'parent_id' => $parentId !== null ? (int) $parentId : null,
            'body' => $comment->body,
            'author' => AuthorPayload::for($comment->user, (bool) $comment->is_anonymous),
            'is_mine' => $isMine,
            'is_anonymous' => (bool) $comment->is_anonymous,
            'created_at' => $comment->created_at?->toIso8601String(),
            'can' => [
                'delete' => $isMine || ($viewer?->can('posts.moderate') ?? false),
                'report' => $viewer !== null && ! $isMine,
            ],
        ];
    }
}
