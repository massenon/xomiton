<?php

declare(strict_types=1);

namespace App\Application\Auth\Listeners;

use App\Enums\UserStatus;
use App\Events\Auth\UserRegistered;
use App\Models\User;
use App\Notifications\PeerPending;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/**
 * Un compte créé reste « en attente » (inscription SIGNALÉE, D-MOB-17, ou « tout valider », Q26) : les comptes habilités
 * `users.validate_peers` (admin, superadmin) reçoivent « Inscription à valider » (cloche et push `peer_pending`, P15-19) et
 * ouvrent la file des inscriptions (web ou espace Admin du mobile). Un compte actif (cas courant) ne notifie personne.
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
            Notification::send($validators, new PeerPending((int) $user->getKey(), (string) $user->pseudo));
        }
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
