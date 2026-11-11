<?php

declare(strict_types=1);

namespace App\Application\Admin\Roles;

use App\Application\Auth\OtpService;
use App\Domain\Account\Exceptions\AccountActionRefused;
use App\Domain\Auth\Exceptions\InvitationInvalid;
use App\Models\RoleInvitation;
use App\Models\User;
use App\Models\Zone;
use App\Support\Audit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Invitations à rejoindre l'équipe (décisions client du 2026-10-29, 3b et 3d) : l'administration crée un lien / code
 * **signé à usage unique** ; la personne l'utilise sur la page d'inscription ordinaire (case « 15 ans » comprise),
 * devient d'abord membre, puis reçoit le rôle à la validation de son compte (GrantInvitedRole). Rôle `pair` :
 * `peers.invite` ; `moderateur`, `admin`, `superadmin` : `roles.assign_admin` (super-admin seulement). Le code n'est
 * jamais conservé en clair (SHA-256) ni écrit au journal ; il n'est montré qu'à la création. Journal
 * `invitations.created` / `invitations.revoked` / `invitations.redeemed`.
 */
final class TeamInvitations
{
    /** Alphabet sans caractères ambigus (0/O, 1/I/L) : le code se dicte et se recopie. */
    private const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    private const CODE_LENGTH = 12;

    /** @var list<string> rôles qu'une invitation peut donner */
    public const ROLES = ['pair', 'moderateur', 'admin', 'superadmin'];

    /**
     * @return array{invitation: RoleInvitation, code: string, link: string}
     *
     * @throws AccountActionRefused
     */
    public function create(User $by, string $role, ?string $email = null, ?string $note = null, ?int $zoneId = null, ?int $days = null): array
    {
        if (! in_array($role, self::ROLES, true)) {
            throw new AccountActionRefused('invalid_role');
        }

        if ($zoneId !== null && ! Zone::query()->active()->whereKey($zoneId)->exists()) {
            throw new AccountActionRefused('invalid_zone');
        }

        $days ??= self::defaultDays();

        if ($days < self::minDays() || $days > self::maxDays()) {
            throw new AccountActionRefused('invalid_invitation_days', ['min' => self::minDays(), 'max' => self::maxDays()]);
        }

        Gate::forUser($by)->authorize('invite', [RoleInvitation::class, $role]);

        $email = $email === null || trim($email) === '' ? null : OtpService::normalizeEmail($email);
        $note = $note === null || trim($note) === '' ? null : Str::limit(trim($note), 190, '');
        $code = self::makeCode();
        $expires = now()->addDays($days);

        $invitation = RoleInvitation::query()->create([
            'role' => $role,
            'zone_id' => $zoneId,
            'code_hash' => RoleInvitation::hash($code),
            'code_hint' => substr(RoleInvitation::normalize($code), -4),
            'email' => $email,
            'note' => $note,
            'status' => RoleInvitation::STATUS_PENDING,
            'invited_by' => $by->getKey(),
            'expires_at' => $expires,
        ]);

        Audit::log('invitations.created', $invitation, sprintf('role=%s ; code=…%s ; expire=%s%s', $role, $invitation->code_hint, $expires->toDateString(), $zoneId === null ? '' : ' ; zone='.$zoneId), $by);

        return ['invitation' => $invitation, 'code' => $code, 'link' => self::link($code, $expires)];
    }

    /**
     * @throws AccountActionRefused
     */
    public function revoke(RoleInvitation $invitation, User $by): void
    {
        Gate::forUser($by)->authorize('revoke', $invitation);

        if (! in_array($invitation->status, [RoleInvitation::STATUS_PENDING, RoleInvitation::STATUS_REDEEMED], true)) {
            throw new AccountActionRefused('invitation_closed');
        }

        $invitation->forceFill(['status' => RoleInvitation::STATUS_REVOKED, 'decided_at' => now(), 'decided_by' => $by->getKey()])->save();

        Audit::log('invitations.revoked', $invitation, sprintf('role=%s ; code=…%s', $invitation->role, $invitation->code_hint), $by);
    }

    /**
     * Invitation utilisable pour ce code (et cette adresse si elle est liée) ; message unique sinon.
     *
     * @throws InvitationInvalid
     */
    public function peek(string $code, ?string $email = null): RoleInvitation
    {
        $invitation = RoleInvitation::query()->usable()->where('code_hash', RoleInvitation::hash($code))->first();

        if ($invitation === null) {
            throw new InvitationInvalid;
        }

        if ($invitation->email !== null && ($email === null || OtpService::normalizeEmail($email) !== $invitation->email)) {
            throw new InvitationInvalid;
        }

        return $invitation;
    }

    /**
     * Consomme l'invitation (usage unique, verrou) pour le compte qui vient d'être créé.
     *
     * @throws InvitationInvalid
     */
    public function redeem(string $code, User $user): RoleInvitation
    {
        return DB::transaction(function () use ($code, $user): RoleInvitation {
            $invitation = RoleInvitation::query()->usable()->where('code_hash', RoleInvitation::hash($code))->lockForUpdate()->first();

            if ($invitation === null || ($invitation->email !== null && $invitation->email !== OtpService::normalizeEmail($user->email))) {
                throw new InvitationInvalid;
            }

            $invitation->forceFill([
                'status' => RoleInvitation::STATUS_REDEEMED,
                'user_id' => $user->getKey(),
                'redeemed_at' => now(),
            ])->save();

            Audit::log('invitations.redeemed', $invitation, sprintf('role=%s ; code=…%s', $invitation->role, $invitation->code_hint), $user);

            return $invitation;
        });
    }

    /** Durée de validité par défaut (jours), ramenée dans les bornes. */
    public static function defaultDays(): int
    {
        return max(self::minDays(), min(self::maxDays(), (int) config('xomiton.accounts.team_invitation_days', 7)));
    }

    public static function minDays(): int
    {
        return max(1, (int) config('xomiton.accounts.team_invitation_min_days', 1));
    }

    public static function maxDays(): int
    {
        return max(self::minDays(), (int) config('xomiton.accounts.team_invitation_max_days', 30));
    }

    public static function makeCode(): string
    {
        $chars = '';

        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $chars .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return 'XM-'.implode('-', str_split($chars, 4));
    }

    /** Lien d'inscription signé et daté : ouvre /inscription avec le code déjà saisi. */
    public static function link(string $code, Carbon $expires): string
    {
        return URL::temporarySignedRoute('inscription', $expires, ['invitation' => $code]);
    }
}
