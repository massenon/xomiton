<?php

declare(strict_types=1);

namespace App\Http\Resources\Community;

use App\Models\Page;
use App\Models\PageFollower;
use App\Models\User;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Route;

/**
 * Page d'organisation telle qu'elle sort de l'API (P8-03, docs/API.md §14) : identifiant public ULID (ADR-27), nom,
 * catégorie, description, site, couverture, abonné·es, badges « Page vérifiée » et « En avant », statut d'approbation
 * (`status` : `approved` | `pending` — une page en attente n'est servie qu'à sa propriétaire et à la modération,
 * P12-05), abonnement de la personne qui lit (`following`, fourni par l'appelant pour une page de résultats).
 *
 * @mixin Page
 */
final class PageResource extends JsonResource
{
    private ?bool $following = null;

    public function withFollowing(bool $following): self
    {
        $this->following = $following;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Page $page */
        $page = $this->resource;
        $viewer = $request->user('sanctum') ?? $request->user();
        $viewer = $viewer instanceof User ? $viewer : null;
        $following = $this->following ?? ($viewer !== null
            && PageFollower::query()->where('page_id', $page->getKey())->where('user_id', $viewer->getKey())->exists());
        $isMine = $viewer !== null && $page->isOwnedBy($viewer);

        return [
            'id' => $page->ulid,
            'name' => (string) $page->name,
            'category' => (string) $page->category,
            'description' => $page->description,
            'website' => $page->website,
            'cover_url' => MediaUrl::resolve($page->cover_path ?? $page->image_path, Page::DEFAULT_COVER),
            'followers_count' => (int) $page->followers_count,
            'is_verified' => (bool) $page->is_verified,
            'is_featured' => (bool) $page->is_featured,
            'status' => $page->is_approved ? 'approved' : 'pending',
            'status_label' => (string) __($page->is_approved ? 'community.pages.status.approved' : 'community.pages.status.pending'),
            'is_mine' => $isMine,
            'following' => $following,
            'url' => Route::has('pages.show') ? route('pages.show', ['page' => $page->ulid]) : url('/pages/'.$page->ulid),
            'created_at' => $page->created_at?->toIso8601String(),
            'can' => [
                'follow' => $viewer !== null && $page->isPubliclyVisible() && $viewer->can('social.access'),
                'update' => $viewer !== null && ($isMine || $viewer->can('moderation.manage')),
            ],
        ];
    }
}
