<?php

declare(strict_types=1);

namespace App\Application\Retention;

use App\Models\OtpCode;
use App\Models\Profile;
use App\Models\PushSubscription;
use App\Models\SyncDevice;
use App\Models\User;
use App\Models\UserSetting;
use App\Support\Audit;
use App\Support\MediaUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Anonymisation des comptes supprimés logiquement depuis plus de `xomiton.accounts.deletion_grace_days`
 * (DATABASE_ENGINEERING.md §6 : « logique 30 j puis anonymisation »). Irréversible et exécutée une seule fois
 * par compte : l'e-mail `supprime-<id>@anonyme.invalid` sert de marqueur (`User::isAnonymized()`).
 *
 * Effacé : pseudo, nom, e-mail, téléphone, mot de passe, jeton « se souvenir de moi », motif de suspension,
 * profil (identité de genre, orientation, bio, pronoms, site, avatar et couverture avec leurs fichiers,
 * contact de confiance), réglages (réinitialisés), jetons API Sanctum, abonnements push, sessions, codes OTP
 * et jetons de réinitialisation de l'ancienne adresse ; les appareils de synchronisation sont révoqués.
 * Conservé : publications, commentaires et autres contenus (affichés sous le pseudo anonymisé), consentements.
 * Chaque anonymisation laisse une entrée `account.anonymize` dans le journal d'audit (acteur « système »).
 */
final class AnonymizeDeletedAccounts
{
    private const CHUNK_SIZE = 100;

    public function graceDays(): int
    {
        return (int) config('xomiton.accounts.deletion_grace_days', 30);
    }

    /**
     * Comptes à anonymiser : supprimés logiquement depuis plus que le délai de grâce et pas encore anonymisés.
     *
     * @return Builder<User>
     */
    public function pending(): Builder
    {
        return User::onlyTrashed()
            ->notAnonymized()
            ->where('deleted_at', '<', now()->subDays($this->graceDays()));
    }

    /**
     * @return int Nombre de comptes anonymisés.
     */
    public function run(): int
    {
        $count = 0;

        $this->pending()->chunkById(self::CHUNK_SIZE, function (Collection $users) use (&$count): void {
            foreach ($users as $user) {
                $this->anonymize($user);
                $count++;
            }
        });

        Log::info('Rétention : comptes supprimés anonymisés.', ['count' => $count, 'grace_days' => $this->graceDays()]);

        return $count;
    }

    public function anonymize(User $user): void
    {
        if ($user->isAnonymized()) {
            return;
        }

        DB::transaction(function () use ($user): void {
            $id = (int) $user->getKey();
            $previousEmail = (string) $user->email;

            $this->revokeAccess($user, $previousEmail);
            $this->eraseProfile($user);
            $this->resetSettings($user);

            $user->forceFill([
                'pseudo' => User::anonymizedPseudo($id),
                'name' => null,
                'email' => User::anonymizedEmail($id),
                'phone' => null,
                'password' => Str::random(40),
                'remember_token' => null,
                'suspension_reason' => null,
            ])->saveQuietly();

            Audit::log('account.anonymize', $user, sprintf(
                'Compte supprimé le %s, anonymisé après le délai de grâce de %d jours.',
                $user->deleted_at?->format('d/m/Y') ?? 'date inconnue',
                $this->graceDays(),
            ));
        });
    }

    /**
     * Jetons API, abonnements push, sessions, codes OTP et jetons de réinitialisation : plus aucun accès possible.
     */
    private function revokeAccess(User $user, string $previousEmail): void
    {
        $user->tokens()->delete();
        PushSubscription::query()->where('user_id', $user->getKey())->delete();
        SyncDevice::query()->where('user_id', $user->getKey())->active()->update(['is_revoked' => true, 'updated_at' => now()]);
        OtpCode::query()->where('email', mb_strtolower($previousEmail))->delete();

        if (Schema::hasTable('password_reset_tokens')) {
            DB::table('password_reset_tokens')->where('email', $previousEmail)->delete();
        }

        $sessionTable = (string) config('session.table', 'sessions');
        $sessionConnection = config('session.connection');
        $sessionConnection = is_string($sessionConnection) && $sessionConnection !== '' ? $sessionConnection : null;

        if (Schema::connection($sessionConnection)->hasTable($sessionTable)) {
            DB::connection($sessionConnection)->table($sessionTable)->where('user_id', $user->getKey())->delete();
        }
    }

    private function eraseProfile(User $user): void
    {
        $profile = Profile::query()->where('user_id', $user->getKey())->first();

        if ($profile === null) {
            return;
        }

        $this->deleteUpload($profile->avatar_path);
        $this->deleteUpload($profile->cover_path);

        $profile->forceFill([
            'pronouns' => null,
            'gender_identity' => null,
            'orientation' => null,
            'bio' => null,
            'completion' => 0,
            'avatar_path' => null,
            'cover_path' => null,
            'trusted_contact_phone' => null,
            'trusted_contact_name' => null,
            'website' => null,
            'visibility' => 'private',
            'show_zone' => false,
            'searchable' => false,
        ])->saveQuietly();
    }

    private function resetSettings(User $user): void
    {
        $settings = UserSetting::query()->where('user_id', $user->getKey())->first();

        if ($settings === null) {
            return;
        }

        $settings->forceFill([
            'theme' => 'auto',
            'language' => 'fr',
            'notification_prefs' => UserSetting::defaultNotificationPrefs(),
            'mask_sensitive_notifications' => false,
            'notification_digest' => UserSetting::DIGEST_NEVER,
            'sos_share_gps' => true,
            'pin_enabled' => false,
            'pin_hash' => null,
            'neutral_tab_title' => false,
        ])->saveQuietly();
    }

    /**
     * Supprime un téléversement du disque public ; les images de démonstration de `public/assets` et les URL
     * absolues ne sont pas des fichiers de la personne.
     */
    private function deleteUpload(?string $path): void
    {
        $path = ltrim(trim((string) $path), '/');

        if ($path === '' || str_starts_with($path, 'assets/') || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return;
        }

        if (MediaUrl::exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
