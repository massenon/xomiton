<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\MemberType;
use App\Enums\ProfileVisibility;
use App\Enums\UserStatus;
use App\Models\Profile;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

/**
 * Comptes de démonstration du prototype (prototype_social/data/users.json, sign-in.php).
 * Jamais exécuté en production ; dépend de RolesAndPermissionsSeeder pour les rôles.
 *
 * Un seul compte, des rôles cumulables (décisions client du 2026-10-29) : `role` est le rôle principal, `roles` la liste
 * complète (toujours avec `membre`). `pair@` = pair + membre, `moderation@` = admin + membre, `equipe@` = admin + pair +
 * membre (les trois espaces, pour le sélecteur d'espace du mobile), `pair.attente@` = membre dont le rôle pair est en
 * attente de validation.
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
                'roles' => ['membre', 'superadmin'],
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
                'roles' => ['membre', 'admin'],
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
                'roles' => ['membre', 'pair'],
                'pseudo' => 'pair_chantal',
                'name' => 'Chantal Hounkpatin',
                'phone' => '+229 01 66 22 33 44',
                'zone' => 'Ouémé',
                'commune' => 'Porto-Novo',
                'avatar' => 'avatars/avatar4.png',
                'member_type' => MemberType::Peer,
                'bio' => 'Pair éducatrice à Porto-Novo : écoute, orientation et animation d’ateliers.',
            ],
            [
                'email' => 'moderateur@xomiton.bj',
                'password' => 'Modo123!',
                'role' => 'moderateur',
                'roles' => ['membre', 'moderateur'],
                'pseudo' => 'modo_rose',
                'name' => 'Rose Adjovi',
                'phone' => '+229 01 97 00 00 03',
                'zone' => 'Littoral',
                'commune' => 'Cotonou',
                'avatar' => 'avatars/avatar2.png',
                'member_type' => MemberType::Member,
                'bio' => 'Modération du fil, des forums et des signalements de contenu.',
            ],
            [
                'email' => 'membre2@xomiton.bj',
                'password' => 'Membre123!',
                'role' => 'membre',
                'pseudo' => 'fatou_k',
                'name' => 'Fatou Kpadonou',
                'phone' => '+229 01 55 98 76 54',
                'zone' => 'Atlantique',
                'commune' => 'Abomey-Calavi',
                'avatar' => 'avatars/avatar1.png',
                'member_type' => MemberType::Member,
                'bio' => 'Deuxième compte membre : conversations, groupes et événements à deux.',
            ],
            [
                'email' => 'pair.attente@xomiton.bj',
                'password' => 'Pair123!',
                'role' => 'membre',
                'roles' => ['membre'],
                'pseudo' => 'pair_en_attente',
                'name' => 'Pair en attente de validation',
                'phone' => '+229 01 66 22 33 55',
                'zone' => 'Borgou',
                'commune' => 'Parakou',
                'avatar' => 'avatars/avatar4.png',
                'member_type' => MemberType::Peer,
                'bio' => 'Demande de validation à traiter dans Administration › Utilisateurs (RG-03).',
                'status' => UserStatus::Pending,
            ],
            [
                'email' => 'equipe@xomiton.bj',
                'password' => 'Equipe123!',
                'role' => 'admin',
                'roles' => ['membre', 'pair', 'admin'],
                'pseudo' => 'equipe_xom',
                'name' => 'Équipe tous espaces',
                'phone' => '+229 01 97 00 00 04',
                'zone' => 'Littoral',
                'commune' => 'Cotonou',
                'avatar' => 'avatars/avatar3.png',
                'member_type' => MemberType::Peer,
                'bio' => 'Compte qui cumule membre, pair éducateur·rice et admin : sélecteur d’espace du mobile.',
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
                'status' => $account['status'] ?? UserStatus::Active,
                'zone_id' => $zoneId,
                'commune_id' => $communeId,
                'email_verified_at' => now(),
                'deleted_at' => null,
            ],
        );

        $this->ensureDemoAvatar($account['avatar']);

        Profile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'member_type' => $account['member_type'],
                'bio' => $account['bio'],
                'avatar_path' => $account['avatar'],
                'completion' => 80,
                'pronouns' => 'elle',
                'age_range' => '25-34',
                // Réglages du prototype (settings.php) : profil visible de la communauté, zone affichée, trouvable.
                'visibility' => ProfileVisibility::Community,
                'show_zone' => true,
                'searchable' => true,
            ],
        );

        UserSetting::firstOrCreate(
            ['user_id' => $user->id],
            [
                'theme' => 'auto',
                'language' => 'fr',
                'notification_prefs' => UserSetting::defaultNotificationPrefs(),
                'notification_digest' => UserSetting::DIGEST_WEEKLY,
                'sos_share_gps' => true,
            ],
        );

        $roles = array_values(array_unique(array_merge(['membre'], $account['roles'] ?? [$account['role']])));
        $existing = Role::query()->where('guard_name', RolesAndPermissionsSeeder::GUARD)->whereIn('name', $roles)->pluck('name')->all();

        if ($existing !== []) {
            $user->syncRoles($existing);
        }
    }

    /**
     * Copie l'avatar de démonstration (public/assets/images/xomiton) sur le disque public, comme le fera
     * l'import du prototype (dictionnaire : `storage/app/public/avatars`), pour que `avatar_url` serve un fichier.
     */
    private function ensureDemoAvatar(string $path): void
    {
        $disk = Storage::disk('public');

        if ($disk->exists($path)) {
            return;
        }

        $source = public_path('assets/images/xomiton/'.basename($path));

        if (is_file($source)) {
            $disk->put($path, (string) file_get_contents($source));
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
