<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\MemberType;
use App\Enums\UserStatus;
use App\Models\Profile;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

/**
 * Comptes de démonstration du prototype (prototype_social/data/users.json, sign-in.php).
 * Jamais exécuté en production ; dépend de RolesAndPermissionsSeeder pour les rôles.
 */
class DemoAccountsSeeder extends Seeder
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function accounts(): array
    {
        return [
            [
                'email' => 'admin@xomiton.bj',
                'password' => 'Admin123!',
                'role' => 'superadmin',
                'pseudo' => 'admin',
                'name' => 'Administrateur Xomiton',
                'phone' => '+229 01 97 00 00 01',
                'zone' => 'Littoral',
                'commune' => 'Cotonou',
                'avatar' => 'avatars/avatar1.png',
                'member_type' => MemberType::Member,
                'bio' => 'Compte de coordination de la plateforme Xomiton.',
            ],
            [
                'email' => 'moderation@xomiton.bj',
                'password' => 'Admin123!',
                'role' => 'admin',
                'pseudo' => 'moderatrice',
                'name' => 'Gestionnaire de contenu',
                'phone' => '+229 01 97 00 00 02',
                'zone' => 'Atlantique',
                'commune' => 'Abomey-Calavi',
                'avatar' => 'avatars/avatar2.png',
                'member_type' => MemberType::Member,
                'bio' => 'Gestion des contenus et de la modération.',
            ],
            [
                'email' => 'membre@xomiton.bj',
                'password' => 'Membre123!',
                'role' => 'membre',
                'pseudo' => 'awa_s',
                'name' => 'Awa Sossou',
                'phone' => '+229 01 55 12 34 56',
                'zone' => 'Littoral',
                'commune' => 'Cotonou',
                'avatar' => 'avatars/avatar3.png',
                'member_type' => MemberType::Member,
                'bio' => 'Ici, on apprend, on s’entraide et on avance ensemble.',
            ],
            [
                'email' => 'pair@xomiton.bj',
                'password' => 'Pair123!',
                'role' => 'pair',
                'pseudo' => 'pair_chantal',
                'name' => 'Chantal Hounkpatin',
                'phone' => '+229 01 66 22 33 44',
                'zone' => 'Ouémé',
                'commune' => 'Porto-Novo',
                'avatar' => 'avatars/avatar4.png',
                'member_type' => MemberType::Peer,
                'bio' => 'Pair éducatrice à Porto-Novo : écoute, orientation et animation d’ateliers.',
            ],
        ];
    }

    public function run(): void
    {
        if (! app()->environment('local', 'testing', 'staging')) {
            return;
        }

        foreach (self::accounts() as $account) {
            $this->seedAccount($account);
        }
    }

    /**
     * @param  array<string, mixed>  $account
     */
    private function seedAccount(array $account): void
    {
        $zoneId = $this->lookupId('zones', $account['zone']);
        $communeId = $this->lookupId('communes', $account['commune'], $zoneId);

        $user = User::withTrashed()->updateOrCreate(
            ['email' => $account['email']],
            [
                'pseudo' => $account['pseudo'],
                'name' => $account['name'],
                'phone' => $account['phone'],
                'password' => $account['password'],
                'status' => UserStatus::Active,
                'zone_id' => $zoneId,
                'commune_id' => $communeId,
                'email_verified_at' => now(),
                'deleted_at' => null,
            ],
        );

        Profile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'member_type' => $account['member_type'],
                'bio' => $account['bio'],
                'avatar_path' => $account['avatar'],
                'completion' => 80,
                'pronouns' => 'elle',
                'age_range' => '25-34',
            ],
        );

        UserSetting::firstOrCreate(
            ['user_id' => $user->id],
            [
                'theme' => 'auto',
                'language' => 'fr',
                'notification_prefs' => UserSetting::defaultNotificationPrefs(),
            ],
        );

        if (Role::where('name', $account['role'])->where('guard_name', RolesAndPermissionsSeeder::GUARD)->exists()) {
            $user->syncRoles([$account['role']]);
        }
    }

    /**
     * Les référentiels (lot 1) peuvent ne pas être encore migrés ou seedés : la FK reste alors nulle.
     */
    private function lookupId(string $table, string $name, ?int $zoneId = null): ?int
    {
        if ($name === '' || ! Schema::hasTable($table)) {
            return null;
        }

        $query = DB::table($table)->where('name', $name);

        if ($table === 'communes' && $zoneId !== null) {
            $query->where('zone_id', $zoneId);
        }

        $id = $query->value('id');

        return $id === null ? null : (int) $id;
    }
}
