<?php

declare(strict_types=1);

namespace App\Application\Account\RoleRequests;

use App\Domain\Account\Exceptions\RoleRequestRefused;
use App\Enums\UserStatus;
use App\Models\RoleRequest;
use App\Models\User;
use App\Notifications\RoleRequested;
use App\Support\Audit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Un membre DEMANDE le rôle pair depuis son profil (D-MOB-15, tranché 2026-10-29). Jamais d'auto-attribution : la demande
 * crée seulement une ligne `pending` ; le rôle ne vient qu'avec la décision d'un compte habilité (ReviewRoleRequest).
 * Conditions : compte actif, e-mail confirmé, pas déjà pair, aucune autre demande en cours (une à la fois, garantie par
 * l'index unique `open_user_id` même en cas de requêtes simultanées). Motif facultatif, court (≤ 200 caractères).
 * Journal `roles.request_created`, cloche des comptes habilités (`peers.assign`).
 */
final class RequestPeerRole
{
    /**
     * @throws RoleRequestRefused
     */
    public function __invoke(User $user, ?string $reason = null): RoleRequest
    {
        if ($user->status !== UserStatus::Active) {
            throw new RoleRequestRefused('not_active');
        }

        if ($user->email_verified_at === null) {
            throw new RoleRequestRefused('email_unverified');
        }

        if ($user->hasRole('pair')) {
            throw new RoleRequestRefused('already_peer');
        }

        $reason = $reason === null || trim($reason) === '' ? null : Str::limit(trim($reason), RoleRequest::REASON_MAX, '');

        try {
            $request = DB::transaction(function () use ($user, $reason): RoleRequest {
                if (RoleRequest::query()->where('user_id', $user->getKey())->pending()->exists()) {
                    throw new RoleRequestRefused('request_open');
                }

                return RoleRequest::query()->create([
                    'user_id' => $user->getKey(),
                    'open_user_id' => $user->getKey(),
                    'role' => RoleRequest::ROLE_PEER,
                    'status' => RoleRequest::STATUS_PENDING,
                    'reason' => $reason,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            throw new RoleRequestRefused('request_open');
        }

        Audit::log('roles.request_created', $user, sprintf('pseudo=%s ; role=%s', $user->pseudo, RoleRequest::ROLE_PEER), $user);

        Notification::send(
            User::query()->active()->permission('peers.assign')->where('id', '!=', $user->getKey())->get(),
            new RoleRequested((int) $request->getKey(), (string) $user->pseudo),
        );

        return $request;
    }
}
