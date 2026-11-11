<?php

declare(strict_types=1);

namespace App\Application\Retention;

use App\Application\Shared\MediaUploader;
use App\Enums\SubscriberStatus;
use App\Models\Consent;
use App\Models\NewsletterSubscriber;
use App\Models\OtpCode;
use App\Models\Profile;
use App\Models\PushSubscription;
use App\Models\SyncDevice;
use App\Models\User;
use App\Models\UserSetting;
use App\Support\Audit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Anonymisation des comptes supprimés logiquement depuis plus de `xomiton.accounts.deletion_grace_days`
 * (DATABASE_ENGINEERING.md §6 : « logique 30 j puis anonymisation »). Irréversible et exécutée une seule fois
 * par compte : l'e-mail `supprime-<id>@anonyme.invalid` sert de marqueur (`User::isAnonymized()`).
 *
 * Effacé : pseudo, nom, e-mail, téléphone, mot de passe, jeton « se souvenir de moi », motif de suspension,
 * profil (identité de genre, orientation, bio, pronoms, site, avatar et couverture avec leurs fichiers,
 * contact de confiance, statut allié·e, centres d'intérêt), réglages (réinitialisés), jetons API Sanctum, abonnements push, sessions, codes OTP
 * et jetons de réinitialisation de l'ancienne adresse ; les appareils de synchronisation sont révoqués ; abonnement
 * newsletter (rattaché au compte ou à l'ancienne adresse) anonymisé : adresse, nom, centres d'intérêt et étiquettes
 * effacés, désabonné, `user_id` détaché (la ligne reste pour les statistiques des campagnes déjà envoyées) ; adresse
 * effacée des consentements.
 * Conservé : publications, commentaires et autres contenus (affichés sous le pseudo anonymisé), consentements.
 * Chaque anonymisation laisse une entrée `account.anonymize` dans le journal d'audit (acteur « système »).
 */
final class AnonymizeDeletedAccounts
{
    private const CHUNK_SIZE = 100;

    public function __construct(private readonly MediaUploader $uploader) {}

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

        // Traces des demandes web de suppression (empreintes seulement) : durée de conservation limitée.
        \App\Application\Account\PublicAccountDeletion::purge();

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
            $this->eraseNewsletter($id, $previousEmail);
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

    /**
     * Abonnement newsletter du compte ou de son ancienne adresse : plus aucune adresse ni préférence, jamais réactivable.
     */
    private function eraseNewsletter(int $id, string $previousEmail): void
    {
        $email = mb_strtolower(trim($previousEmail));

        NewsletterSubscriber::query()
            ->where(static fn (Builder $query) => $query->where('user_id', $id)->when($email !== '', static fn (Builder $q) => $q->orWhere('email', $email)))
            ->get()
            ->each(static function (NewsletterSubscriber $subscriber): void {
                $subscriber->syncTags([]);
                $subscriber->syncInterests([]);
                $subscriber->forceFill([
                    'email' => 'newsletter-'.$subscriber->getKey().'@'.User::ANONYMIZED_EMAIL_DOMAIN,
                    'name' => null,
                    'status' => $subscriber->status === SubscriberStatus::Bounced ? SubscriberStatus::Bounced : SubscriberStatus::Unsubscribed,
                    'unsubscribed_at' => $subscriber->unsubscribed_at ?? now(),
                    'tags' => null,
                    'interests' => null,
                    'consent_meta' => null,
                    'token' => NewsletterSubscriber::generateToken(),
                    'user_id' => null,
                    'last_opened_at' => null,
                ])->saveQuietly();
            });

        Consent::query()->where('user_id', $id)->whereNotNull('email')->update(['email' => null]);

        if ($email !== '') {
            Consent::query()->whereNull('user_id')->where('email', $email)->update(['email' => null]);
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
            'ally_status' => null,
            'interests' => null,
            'shared_fields' => null,
            'progressive_answered' => null,
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
            'sounds_enabled' => true,
        ])->saveQuietly();
    }

    /**
     * Supprime un téléversement du disque public avec son repli et ses variantes `thumb` / `medium` (MediaUploader :
     * fichiers frères du même ULID, dont l'adresse se déduit de la principale) ; les images de démonstration de
     * `public/assets` et les URL absolues ne sont pas des fichiers de la personne.
     */
    private function deleteUpload(?string $path): void
    {
        $path = ltrim(trim((string) $path), '/');

        if ($path === '' || str_starts_with($path, 'assets/') || preg_match('#^https?://#', $path) === 1) {
            return;
        }

        $this->uploader->delete($path, MediaUploader::publicDisk());
    }
}
