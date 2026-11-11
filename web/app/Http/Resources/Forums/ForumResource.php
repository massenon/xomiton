<?php

declare(strict_types=1);

namespace App\Http\Resources\Forums;

use App\Application\Community\Forums\ForumLinks;
use App\Models\Forum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Forum tel qu'il sort de l'API (P8-05, docs/API.md §16) : clé courte du prototype (`f1`… : `?forum=` des listes),
 * slug, nom, description, icône Bootstrap Icons (sans « bi- »), compteurs « N sujets · M messages » et adresse web.
 *
 * @mixin Forum
 */
final class ForumResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Forum $forum */
        $forum = $this->resource;

        return [
            'key' => (string) $forum->key,
            'slug' => (string) $forum->slug,
            'name' => (string) $forum->name,
            'description' => (string) $forum->description,
            'icon' => (string) preg_replace('/^bi-/', '', (string) $forum->icon),
            'topics_count' => (int) $forum->topics_count,
            'posts_count' => (int) $forum->posts_count,
            'url' => ForumLinks::index($forum),
        ];
    }
}
