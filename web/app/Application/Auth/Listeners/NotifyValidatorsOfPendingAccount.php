<?php

declare(strict_types=1);

namespace App\Application\Auth\Listeners;

use App\Enums\AllyRole;
use App\Enums\MemberType;
use App\Enums\UserStatus;
use App\Events\Auth\UserRegistered;
use App\Models\User;
use App\Notifications\PeerPending;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/**
 * Un compte créé reste « en attente » (« tout valider », Q26, par défaut depuis le 11/11/2026 ; ou inscription SIGNALÉE, D-MOB-17) : les comptes habilités
 * `users.validate_peers` (admin, superadmin) reçoivent « Inscription à valider » (cloche et push `peer_pending`, P15-19) et
 * ouvrent la file des inscriptions (web ou espace Admin du mobile). Le message porte le pseudonyme et le type (membre, ou allié·e et son statut) et le lien ouvre l'écran Inscriptions. Sont servies toutes les inscriptions, web et API (le cas d'usage RegisterUser émet UserRegistered) ; un compte actif d'emblée (mode `peers`, non signalé) ne notifie personne.
 * Enregistré par AppServiceProvider::DOMAIN_LISTENERS.
 */
final class NotifyValidatorsOfPendingAccount
{
    public const PERMISSION = 'users.validate_peers';

    public function handle(UserRegistered $event): void
    {
        $user = $event->user;

        if ($user->status !== UserStatus::Pending) {
            return;
        }

        $validators = self::validators()->reject(static fn (User $validator): bool => $validator->is($user));

        if ($validators->isNotEmpty()) {
            Notification::send($validators, new PeerPending((int) $user->getKey(), (string) $user->pseudo, self::kindOf($user)));
        }
    }

    /**
     * Type de la personne pour le texte de la notification : « membre », ou « allié·e, avocat·e » (statut choisi à
     * l'inscription). Jamais l'orientation, l'identité de genre ni les précisions en texte libre.
     */
    public static function kindOf(User $user): string
    {
        $profile = $user->loadMissing('profile')->profile;

        if ($profile === null || $profile->member_type === MemberType::Member) {
            return (string) __('notifications.peer_pending.kind_member');
        }

        if ($profile->member_type !== MemberType::Ally) {
            return (string) $profile->member_type->label();
        }

        $role = $profile->ally_role === null ? null : AllyRole::tryFrom($profile->ally_role);

        return $role === null
            ? (string) __('notifications.peer_pending.kind_ally')
            : (string) __('notifications.peer_pending.kind_ally_role', ['role' => mb_strtolower($role->label())]);
    }

    /**
     * @return Collection<int, User>
     */
    public static function validators(): Collection
    {
        try {
            return User::query()->active()->permission(self::PERMISSION)->orderBy('id')->get();
        } catch (PermissionDoesNotExist $exception) {
            Log::warning('Inscriptions : permission absente, aucun destinataire.', ['error' => $exception->getMessage()]);

            return new Collection;
        }
    }
}
