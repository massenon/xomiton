<?php

declare(strict_types=1);

namespace App\Application\Account;

use App\Application\Mail\EmailTemplateService;
use App\Models\AccountDeletionRequest;
use App\Models\User;
use App\Support\Audit;
use App\Support\KeyedHash;
use Illuminate\Support\Str;
use Illuminate\Support\Timebox;

/**
 * Suppression de compte demandée depuis le web, SANS connexion (page publique /supprimer-mon-compte, exigence Google
 * Play). Vérification par e-mail : la personne saisit son adresse, reçoit un lien à usage unique (24 h), puis confirme
 * par un bouton (POST) ; la suppression elle-même est celle de l'application (RequestAccountDeletion : accès coupés,
 * effacement après `deletion_grace_days`, anonymisation).
 *
 * Anti-énumération : `request()` renvoie toujours la même chose après la même durée plancher (Timebox), que le compte
 * existe ou non ; seule une adresse de compte actif reçoit un e-mail. Un compte de l'équipe d'administration ne se
 * supprime pas ainsi (comme dans l'application) : aucun e-mail, trace `blocked`. Aucune adresse n'est stockée : empreinte
 * HMAC pour compter les demandes, SHA-256 du jeton.
 */
final readonly class PublicAccountDeletion
{
    public const TEMPLATE = 'suppression-compte-verification';

    public function __construct(private Timebox $timebox, private RequestAccountDeletion $delete) {}

    public function request(string $email): void
    {
        $this->timebox->call(function () use ($email): void {
            $email = mb_strtolower(trim($email));
            $user = User::query()->whereRaw('lower(email) = ?', [$email])->first();
            $hash = KeyedHash::make($email);

            if ($user === null || $user->isAnonymized()) {
                AccountDeletionRequest::query()->create(['email_hash' => $hash, 'outcome' => AccountDeletionRequest::NO_ACCOUNT]);

                return;
            }

            if (! $user->can('deleteOwnAccount', $user)) {
                AccountDeletionRequest::query()->create(['email_hash' => $hash, 'outcome' => AccountDeletionRequest::BLOCKED, 'user_id' => $user->getKey()]);

                return;
            }

            $token = Str::random(48);
            $hours = max(1, (int) config('xomiton.accounts.deletion_request_hours', 24));

            AccountDeletionRequest::query()->create([
                'email_hash' => $hash,
                'token_hash' => hash('sha256', $token),
                'outcome' => AccountDeletionRequest::SENT,
                'user_id' => $user->getKey(),
                'expires_at' => now()->addHours($hours),
            ]);

            EmailTemplateService::enqueue(self::TEMPLATE, (string) $user->email, [
                'user_first_name' => trim(Str::before(trim((string) $user->name), ' ')) ?: (string) $user->pseudo,
                'confirm_link' => route('account-deletion.confirm', ['token' => $token]),
                'link_hours' => (string) $hours,
                'deletion_days' => (string) max(1, (int) config('xomiton.accounts.deletion_grace_days', 30)),
            ], 'compte');
        }, max(0, (int) config('xomiton.newsletter.response_floor_ms', 600)) * 1000);
    }

    /** Demande en attente (lien non expiré, non utilisé) pour ce jeton, sinon null. */
    public function pending(string $token): ?AccountDeletionRequest
    {
        $row = AccountDeletionRequest::query()->where('token_hash', hash('sha256', $token))->first();

        if ($row === null || $row->outcome !== AccountDeletionRequest::SENT || $row->confirmed_at !== null) {
            return null;
        }

        return $row->expires_at !== null && $row->expires_at->isFuture() ? $row : null;
    }

    /** Lance la suppression (même flux que l'application). Faux si le lien n'est plus valable. */
    public function confirm(string $token): bool
    {
        $row = $this->pending($token);
        $user = $row?->user_id !== null ? User::query()->find($row->user_id) : null;

        if ($row === null || $user === null || ! $user->can('deleteOwnAccount', $user)) {
            return false;
        }

        ($this->delete)($user);
        $row->forceFill(['outcome' => AccountDeletionRequest::DONE, 'confirmed_at' => now(), 'token_hash' => null])->save();
        Audit::log('account.deletion_confirmed_by_email', $user, 'Suppression confirmée depuis le lien web public.', $user);

        return true;
    }

    /** Purge des traces au-delà de la durée de conservation. */
    public static function purge(): int
    {
        $days = max(1, (int) config('xomiton.accounts.deletion_request_retention_days', 90));

        return AccountDeletionRequest::query()->where('created_at', '<', now()->subDays($days))->delete();
    }
}
