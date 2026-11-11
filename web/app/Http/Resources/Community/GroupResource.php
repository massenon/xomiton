<?php

declare(strict_types=1);

namespace App\Http\Resources\Community;

use App\Application\Community\Groups\GroupLinks;
use App\Enums\GroupRole;
use App\Enums\MembershipStatus;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Groupe tel qu'il sort de l'API (P8-02, docs/API.md §13) : identifiant public ULID (ADR-27), nom, confidentialité,
 * département (null = national), nombre de membres, couverture, adhésion de la personne qui lit (`membership` :
 * statut et rôle) et gestes permis (`can`). **Fiche réduite** (`restricted: true`) pour un groupe privé dont la
 * personne n'est pas membre (FR-SOC-05, Q75) : ni description, ni propriétaire, ni demandes — de quoi demander à
 * rejoindre, rien de plus. L'adhésion est fournie par l'appelant (`withMembership`), chargée en une requête par page.
 *
 * @mixin Group
 */
final class GroupResource extends JsonResource
{
    private ?GroupMember $membership = null;

    private bool $membershipKnown = false;

    private ?int $pendingRequests = null;

    public function withMembership(?GroupMember $membership): self
    {
        $this->membership = $membership;
        $this->membershipKnown = true;

        return $this;
    }

    /** Nombre de demandes en attente (fiche vue par l'équipe du groupe). */
    public function withPendingRequests(?int $count): self
    {
        $this->pendingRequests = $count;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Group $group */
        $group = $this->resource;
        $viewer = $request->user('sanctum') ?? $request->user();
        $viewer = $viewer instanceof User ? $viewer : null;
        $membership = $this->membershipKnown || $viewer === null
            ? $this->membership
            : GroupMember::query()->where('group_id', $group->getKey())->where('user_id', $viewer->getKey())->first();

        $isOwner = $viewer !== null && $group->owner_id !== null && (int) $group->owner_id === (int) $viewer->getKey();
        $active = $membership?->status === MembershipStatus::Active;
        $requested = $membership?->status === MembershipStatus::Requested;
        $global = $viewer?->can('moderation.manage') ?? false;
        $social = $viewer?->can('social.access') ?? false;
        $restricted = $group->isPrivate() && ! $active && ! $isOwner && ! $global;
        $role = $active ? $membership->role : null;
        $team = $isOwner || ($role !== null && $role->canModerate()) || $global;
        $zone = $group->zone_id !== null ? $group->zone : null;

        return [
            'id' => $group->ulid,
            'name' => (string) $group->name,
            'description' => $restricted ? null : $group->description,
            'privacy' => $group->privacy->value,
            'privacy_label' => $group->privacy->label(),
            'zone' => $zone !== null ? ['id' => (int) $zone->getKey(), 'name' => (string) $zone->name] : null,
            'zone_label' => $zone !== null ? (string) $zone->name : (string) __('community.groups.create.zone_national'),
            'members_count' => (int) $group->members_count,
            'cover_url' => MediaUrl::resolve($group->cover_path ?? $group->image_path, Group::DEFAULT_COVER),
            'url' => GroupLinks::show($group),
            'restricted' => $restricted,
            'is_owner' => $isOwner,
            'membership' => $membership === null || $membership->status === MembershipStatus::Banned ? null : [
                'status' => $membership->status->value,
                'status_label' => $membership->status->label(),
                'role' => $active ? $membership->role->value : null,
                'role_label' => $active ? $membership->role->label() : null,
            ],
            'pending_requests' => $team ? $this->pendingRequests : null,
            'created_at' => $group->created_at?->toIso8601String(),
            'can' => [
                'join' => $social && $group->is_active && ! $active && ! $requested && $membership?->status !== MembershipStatus::Banned,
                'cancel_request' => $requested,
                'leave' => $active,
                'post' => $group->is_active && ($active || $isOwner) && ($viewer?->can('posts.create') ?? false),
                'discuss' => $group->is_active && $active,
                'moderate' => $team,
                'change_role' => $isOwner || $role === GroupRole::Admin || $global,
            ],
        ];
    }
}
