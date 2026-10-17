<?php

declare(strict_types=1);

namespace App\Application\Import;

use App\Enums\MemberType;
use App\Enums\ProfileVisibility;
use App\Enums\UserStatus;
use App\Models\Profile;
use App\Models\User;
use App\Models\UserSetting;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * users.json → users (hash → password, joined → created_at, zone / commune → FK, role → rôle spatie,
 * status), profiles (avatar index → fichier copié sur le disque public, gender → gender_identity chiffré,
 * orientation chiffré, profile → member_type) et user_settings. Clé naturelle : l'e-mail.
 * Un compte sans empreinte de mot de passe reçoit un mot de passe aléatoire, hors production seulement.
 */
final class ImportUsers extends AbstractImporter
{
    /** Fichiers d'avatar du prototype par index (`x_avatar()` : 0–15 → 6 fichiers). */
    private const AVATARS = ['avatar1.png', 'avatar2.png', 'avatar3.png', 'avatar4.png', 'personnage-1.png', 'personnage-2.png'];

    private const AVATAR_DIR = 'avatars';

    public static function collection(): string
    {
        return 'users';
    }

    protected function importRow(array $row, int $index): void
    {
        $email = mb_strtolower($this->required($row['email'] ?? null, 'email', 190));

        if (! str_contains($email, '@')) {
            $this->reject(sprintf('e-mail invalide « %s »', $email));
        }

        $pseudo = $this->required($row['pseudo'] ?? null, 'pseudo', 40);
        $hash = $this->text($row['hash'] ?? null);

        if ($hash !== null && ! Hash::isHashed($hash)) {
            $this->reject('empreinte de mot de passe non reconnue');
        }

        if ($hash === null && ! $this->ctx->demoAllowed()) {
            $this->reject('mot de passe absent : compte non importé en production sans --with-demo');
        }

        $status = UserStatus::tryFrom((string) ($row['status'] ?? ''))
            ?? $this->reject(sprintf('statut inconnu « %s »', (string) ($row['status'] ?? '')));

        $role = $this->text($row['role'] ?? null) ?? 'membre';

        if (! in_array($role, RolesAndPermissionsSeeder::ROLES, true)) {
            $this->reject(sprintf('rôle inconnu « %s »', $role));
        }

        if (User::withTrashed()->where('pseudo', $pseudo)->where('email', '!=', $email)->exists()) {
            $this->reject(sprintf('pseudonyme « %s » déjà utilisé par un autre compte', $pseudo));
        }

        $zoneId = $this->ctx->zoneId($row['zone'] ?? null);
        $communeId = $this->ctx->communeId($row['commune'] ?? null, $zoneId);
        $joined = $this->ctx->date($row['joined'] ?? null);

        $user = User::withTrashed()->firstOrNew(['email' => $email]);
        $isNew = ! $user->exists;

        $user->fill([
            'pseudo' => $pseudo,
            'name' => $this->text($row['name'] ?? null, 120),
            'phone' => $this->text($row['phone'] ?? null, 30),
            'status' => $status,
            'zone_id' => $zoneId,
            'commune_id' => $communeId,
        ]);

        if ($isNew) {
            // Remplacé ci-dessous par l'empreinte du prototype quand elle existe (sans repasser par le cast).
            $user->password = Str::password(24);
        }

        if ($joined !== null) {
            $user->created_at = $joined;
        }

        if ($status === UserStatus::Active && $user->email_verified_at === null) {
            $user->email_verified_at = $joined ?? now();
        }

        $user->deleted_at = null;
        $user->save();

        if ($hash !== null) {
            // L'empreinte bcrypt du prototype est reprise telle quelle : le cast `hashed` refuserait un coût
            // supérieur à BCRYPT_ROUNDS alors que la vérification du mot de passe reste valide.
            DB::table('users')->where('id', $user->getKey())->update(['password' => $hash]);
        } elseif ($isNew) {
            $this->ctx->note(sprintf('compte %s créé avec un mot de passe aléatoire (empreinte absente du prototype)', $email));
        }

        $this->syncProfile($user, $row, $role);
        $this->syncSettings($user);
        $this->syncRole($user, $role);

        $this->ctx->rememberUser($user, $row['id'] ?? null);
        $this->track($user);
    }

    /** @param  array<string, mixed>  $row */
    private function syncProfile(User $user, array $row, string $role): void
    {
        $profile = Profile::query()->firstOrNew(['user_id' => $user->getKey()]);
        $avatar = $this->copyAvatar($row['avatar'] ?? null);

        $profile->fill([
            'member_type' => $this->memberType($row['profile'] ?? null, $role),
            'avatar_path' => $avatar ?? $profile->avatar_path,
        ]);

        if (($gender = $this->text($row['gender'] ?? null, 120)) !== null) {
            $profile->gender_identity = $gender;
        }

        if (($orientation = $this->text($row['orientation'] ?? null, 120)) !== null) {
            $profile->orientation = $orientation;
        }

        if (! $profile->exists) {
            $profile->fill([
                'visibility' => ProfileVisibility::Community,
                'show_zone' => true,
                'searchable' => true,
            ]);
        }

        $filled = array_filter([$user->name, $user->zone_id, $user->commune_id, $user->phone, $profile->avatar_path]);
        $profile->completion = min(100, 20 * count($filled));
        $profile->save();
    }

    private function syncSettings(User $user): void
    {
        UserSetting::query()->firstOrCreate(['user_id' => $user->getKey()], [
            'theme' => 'auto',
            'language' => 'fr',
            'notification_prefs' => UserSetting::defaultNotificationPrefs(),
            'notification_digest' => UserSetting::DIGEST_WEEKLY,
            'sos_share_gps' => true,
        ]);
    }

    private function syncRole(User $user, string $role): void
    {
        if (Role::query()->where('name', $role)->where('guard_name', RolesAndPermissionsSeeder::GUARD)->exists()) {
            $user->syncRoles([$role]);

            return;
        }

        $this->ctx->note(sprintf('rôle « %s » absent (seeder des rôles non exécuté) : compte %s importé sans rôle', $role, $user->email));
    }

    /** `profile` de l'inscription du prototype (`pair_animateur`, `membre`, `allie`, `ressource`), sinon déduit du rôle. */
    private function memberType(mixed $profile, string $role): MemberType
    {
        $value = mb_strtolower(trim((string) $profile));

        if ($value === 'pair_animateur') {
            return MemberType::Peer;
        }

        return MemberType::tryFrom($value) ?? ($role === 'pair' ? MemberType::Peer : MemberType::Member);
    }

    /**
     * Copie l'avatar de démonstration désigné par son index sur le disque public (`avatars/<fichier>`), comme
     * le prévoit le dictionnaire ; renvoie le chemin stocké, ou null si le fichier source manque.
     */
    private function copyAvatar(mixed $index): ?string
    {
        if (! is_numeric($index)) {
            return null;
        }

        $file = self::AVATARS[((int) $index) % count(self::AVATARS)];
        $target = self::AVATAR_DIR.'/'.$file;
        $disk = Storage::disk('public');

        if ($disk->exists($target)) {
            return $target;
        }

        $source = $this->ctx->data->assetPath('images/xomiton/'.$file);

        if ($source === null) {
            $this->ctx->note(sprintf('avatar %s introuvable : profil importé sans avatar', $file));

            return null;
        }

        if (! $this->ctx->dryRun) {
            $disk->put($target, (string) file_get_contents($source));
        }

        return $target;
    }
}
