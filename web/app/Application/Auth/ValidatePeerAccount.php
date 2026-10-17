<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Application\Auth\Notifications\PeerAccountValidated;
use App\Application\Mail\EmailTemplateService;
use App\Enums\MemberType;
use App\Enums\UserStatus;
use App\Events\Auth\PeerValidated;
use App\Models\User;
use App\Support\Audit;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Validation d'un compte pair éducateur·rice par l'administration (P2-10, RG-03, FR-AUTH-05) : statut actif,
 * rôle `pair` garanti, e-mail `compte-valide`, notification cloche et journal d'audit au nom de l'admin.
 * L'autorisation (`users.validate_peers`) est vérifiée par l'appelant (policy).
 */
final class ValidatePeerAccount
{
    public function __invoke(User $peer, User $by): void
    {
        DB::transaction(function () use ($peer): void {
            $peer->forceFill(['status' => UserStatus::Active])->save();

            if ($peer->profile !== null && $peer->profile->member_type !== MemberType::Peer) {
                $peer->profile->forceFill(['member_type' => MemberType::Peer])->save();
            }

            if (! $peer->hasRole(CompleteRegistrationProfile::PEER_ROLE)) {
                $peer->assignRole(Role::findOrCreate(CompleteRegistrationProfile::PEER_ROLE, RolesAndPermissionsSeeder::GUARD));
            }
        });

        EmailTemplateService::send('compte-valide', $peer->email, [
            'user_first_name' => $this->firstName($peer),
            'user_pseudo' => $peer->pseudo,
        ], 'validation_pair');

        $notification = new PeerAccountValidated($by);
        $peer->notify($notification);
        $this->categorize($peer, $notification);

        Audit::log('users.validate_peer', $peer, sprintf('pseudo=%s', $peer->pseudo), $by);
        PeerValidated::dispatch($peer, $by);
    }

    /**
     * Renseigne `notifications.category` / `is_sensitive` (DATABASE_DICTIONARY §9) : le canal `database` de Laravel ne
     * remplit que `data`. À remplacer par le canal Xomiton quand le lot notifications (P3) le livrera.
     */
    private function categorize(User $peer, PeerAccountValidated $notification): void
    {
        $peer->notifications()
            ->where('type', $notification::class)
            ->whereNull('category')
            ->update(['category' => $notification->category(), 'is_sensitive' => $notification->isSensitive()]);
    }

    private function firstName(User $user): string
    {
        $firstName = trim(Str::before(trim((string) $user->name), ' '));

        return $firstName !== '' ? $firstName : (string) $user->pseudo;
    }
}
