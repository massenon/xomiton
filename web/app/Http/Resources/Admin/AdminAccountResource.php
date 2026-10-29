<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Application\Admin\Users\AdminRoles;
use App\Application\Admin\Users\ReviewRegistration;
use App\Application\Auth\Support\RegistrationFlags;
use App\Application\Member\AccountSpaces;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Compte vu par l'admin mobile (file des inscriptions en attente, recherche d'un membre pour lui attribuer un rôle ;
 * P15-16) : pseudonyme, statut, rôles cumulés, espaces disponibles, département et ancienneté. L'e-mail et le téléphone ne
 * sortent que pour `users.manage` (comme l'écran Utilisateurs) ; jamais le nom réel, le contact de confiance ni un réglage.
 * `requested_role` : `pair` quand la demande vise le rôle pair (profil « pair » ou invitation à l'équipe utilisée).
 *
 * @property User $resource
 */
final class AdminAccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $account = $this->resource;
        $viewer = $request->user();
        $contacts = $viewer instanceof User && $viewer->can('users.manage');
        $pending = $account->status === UserStatus::Pending;

        $data = [
            'id' => (int) $account->getKey(),
            'pseudo' => $account->pseudo,
            'avatar_url' => $account->avatar_url,
            'status' => $account->status->value,
            'role' => AdminRoles::of($account),
            'roles' => $account->getRoleNames()->sort()->values()->all(),
            'spaces' => AccountSpaces::for($account, AccountSpaces::SURFACE_MOBILE),
            'is_peer' => $account->hasRole('pair'),
            'zone' => $account->zone === null ? null : ['id' => (int) $account->zone->getKey(), 'name' => $account->zone->name],
            'email_verified' => $account->email_verified_at !== null,
            'member_type' => $account->profile?->member_type?->value,
            'flags' => $pending ? RegistrationFlags::parse($account->registration_flags) : [],
            'requested_role' => $pending && ReviewRegistration::isPeerRequest($account) ? 'pair' : null,
            'created_at' => $account->created_at?->toIso8601String(),
            'last_seen_at' => $account->last_seen_at?->toIso8601String(),
            'can' => [
                'grant_peer' => $viewer instanceof User && ! $account->hasRole('pair') && $account->isActive() && $viewer->can('assignPeerRole', $account),
                'revoke_peer' => $viewer instanceof User && $account->hasRole('pair') && $viewer->can('revokePeerRole', $account),
                // D-MOB-16 : le mobile ne change que membre ↔ pair, jamais un rôle d'administration (web seulement).
                'change_role' => $viewer instanceof User && ! AdminRoles::isAdministration(AdminRoles::of($account)) && $viewer->can('assignRole', $account),
                'validate' => $viewer instanceof User && $pending && $viewer->can('validatePeer', $account),
            ],
        ];

        if ($contacts) {
            $data['email'] = $account->email;
            $data['phone'] = $account->phone;
        }

        return $data;
    }
}
