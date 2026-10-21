<?php

declare(strict_types=1);

namespace App\Application\Admin\Users;

use App\Application\Auth\OtpService;
use App\Application\Auth\RequestPasswordReset;
use App\Domain\Account\Exceptions\AccountActionRefused;
use App\Enums\MemberType;
use App\Enums\UserStatus;
use App\Models\Profile;
use App\Models\User;
use App\Models\UserSetting;
use App\Rules\PhoneBj;
use App\Support\Audit;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Création d'un compte par l'équipe (P12-01) : `users.manage` (UserPolicy `create`) ; le rôle doit faire partie de ceux
 * que le compte peut attribuer (AdminRoles::assignable, RG-15 : modérateur·rice, admin et super-admin demandent
 * `roles.assign_admin`). Compte actif, mot de passe aléatoire que personne ne voit : la personne choisit le sien par
 * l'e-mail `reinitialisation-mot-de-passe` (RequestPasswordReset, jamais de mot de passe temporaire affiché,
 * contrairement à `admin/utilisateurs.php` l.41). Profil et réglages comme à l'inscription ; journal `users.created`.
 * Les consentements (CGU, confidentialité) ne sont pas recueillis ici : ils le sont à la première connexion (plan Q91).
 */
final class CreateUserAccount
{
    public function __construct(private readonly RequestPasswordReset $reset) {}

    /**
     * @throws AccountActionRefused
     */
    public function __invoke(NewAccountData $data, User $by): User
    {
        Gate::forUser($by)->authorize('create', User::class);

        if (! in_array($data->role, AdminRoles::assignable($by), true)) {
            throw new AccountActionRefused(AdminRoles::isAdministration($data->role) ? 'superadmin_only_role' : 'invalid_role');
        }

        $email = OtpService::normalizeEmail($data->email);

        $user = DB::transaction(function () use ($data, $email): User {
            $user = User::query()->create([
                'pseudo' => trim($data->pseudo),
                'name' => trim($data->name),
                'email' => $email,
                'phone' => $data->phone === null || trim($data->phone) === '' ? null : PhoneBj::normalize($data->phone),
                'password' => Str::password(40),
                'status' => UserStatus::Active,
                'zone_id' => $data->zoneId,
                'email_verified_at' => null,
            ]);

            $user->assignRole(Role::findOrCreate($data->role, RolesAndPermissionsSeeder::GUARD));

            Profile::query()->create([
                'user_id' => $user->getKey(),
                'member_type' => $data->role === 'pair' ? MemberType::Peer : MemberType::Member,
                'completion' => 0,
            ]);

            UserSetting::query()->create([
                'user_id' => $user->getKey(),
                'theme' => 'auto',
                'language' => (string) config('app.locale', 'fr'),
                'notification_prefs' => UserSetting::defaultNotificationPrefs(),
                'notification_digest' => UserSetting::DIGEST_NEVER,
            ]);

            return $user;
        });

        Audit::log('users.created', $user, sprintf('pseudo=%s ; role=%s ; invitation=%s', $user->pseudo, $data->role, $data->sendInvitation ? 'oui' : 'non'), $by);

        if ($data->sendInvitation) {
            ($this->reset)($email);
        }

        return $user;
    }
}
