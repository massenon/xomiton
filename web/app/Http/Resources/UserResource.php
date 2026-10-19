<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Application\Account\Profile\ProgressiveProfileState;
use App\Domain\Account\SensitiveProfile;
use App\Enums\AllyStatus;
use App\Enums\UserStatus;
use App\Models\Profile;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Compte tel qu'il sort de l'API (RG-02) : le pseudonyme est la seule identité publique. Le nom réel n'apparaît jamais ;
 * l'e-mail, les permissions, les réglages, le contact de confiance et le mode discret ne sont renvoyés qu'à la personne
 * elle-même (`GET me`). Identité de genre, orientation, champs montrés et état du profil progressif (P8-08) : vue
 * « moi » **et** withSensitiveProfile() seulement (`GET me`, `GET · PATCH me/profile`, réponses no-store), jamais dans
 * les réponses de connexion, d'inscription, d'OTP ou de réglages ; un autre compte ne les lit que par
 * App\Http\Resources\Member\MemberProfileResource, et seulement si la personne a choisi de les montrer.
 *
 * @mixin User
 */
final class UserResource extends JsonResource
{
    private bool $asSelf = false;

    private bool $withSensitive = false;

    /**
     * Vue « moi » forcée : réponse d'inscription ou de connexion, quand la requête ne porte pas encore de jeton.
     */
    public function asSelf(): static
    {
        $this->asSelf = true;

        return $this;
    }

    /**
     * Ajoute à la vue « moi » les données sensibles déchiffrées et l'état du profil progressif (P8-08) : réservé aux
     * réponses no-store qui les relisent (`GET me`, `GET · PATCH me/profile`, bandeau de /profil).
     */
    public function withSensitiveProfile(): static
    {
        $this->withSensitive = true;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;
        $self = $this->isSelf($request);
        $profile = $user->profile;

        $data = [
            'id' => $user->getKey(),
            'pseudo' => $user->pseudo,
            'avatar_url' => $user->avatar_url,
            'status' => $user->status instanceof UserStatus ? $user->status->value : (string) $user->status,
            'pending_validation' => $user->status === UserStatus::Pending,
            'role' => $user->getRoleNames()->first(),
            'roles' => $user->getRoleNames()->values()->all(),
            'profile' => $this->profile($profile, $self, $user),
            'created_at' => $user->created_at?->toIso8601String(),
        ];

        if ($self) {
            $data += [
                'email' => $user->email,
                'email_verified' => $user->email_verified_at !== null,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'permissions' => $user->getAllPermissions()->pluck('name')->sort()->values()->all(),
                'settings' => $this->settings($user->settings),
                'last_login_at' => $user->last_login_at?->toIso8601String(),
            ];
        }

        return $data;
    }

    private function isSelf(Request $request): bool
    {
        if ($this->asSelf) {
            return true;
        }

        $viewer = $request->user('sanctum') ?? $request->user();

        return $viewer instanceof User && $viewer->is($this->resource);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function profile(?Profile $profile, bool $self, User $user): ?array
    {
        if ($profile === null) {
            return null;
        }

        $showZone = $self || $profile->show_zone;

        $data = [
            'pronouns' => $profile->pronouns,
            'member_type' => $profile->member_type?->value,
            'bio' => $profile->bio,
            'completion' => (int) $profile->completion,
            'visibility' => $profile->visibility?->value,
            'zone' => $showZone && $user->zone !== null ? ['id' => $user->zone->getKey(), 'name' => $user->zone->name] : null,
            'commune' => $showZone && $user->commune !== null ? ['id' => $user->commune->getKey(), 'name' => $user->commune->name] : null,
        ];

        if ($self) {
            $data += [
                'age_range' => $profile->age_range?->value,
                'discreet_mode' => (bool) $profile->discreet_mode,
                'show_zone' => (bool) $profile->show_zone,
                'searchable' => (bool) $profile->searchable,
                'trusted_contact_name' => $profile->trusted_contact_name,
                'trusted_contact_phone' => $profile->trusted_contact_phone,
                // P6-06 : profil progressif (Q10 c), vue « moi » seulement.
                'ally_status' => AllyStatus::tryFrom((string) $profile->ally_status)?->value,
                'interests' => array_values(array_filter((array) ($profile->interests ?? []), 'is_string')),
                'cover_url' => $profile->coverUrl(),
            ];
        }

        if ($self && $this->withSensitive) {
            $data += [
                // P8-08 : données sensibles (chiffrées) relues par la personne seule, champs qu'elle a choisi de montrer,
                // état du bandeau « Complète ton profil » (consentement `sensitive_data` compris).
                'gender_identity' => self::closedValue($profile->gender_identity, SensitiveProfile::GENDER_IDENTITIES),
                'orientation' => self::closedValue($profile->orientation, SensitiveProfile::ORIENTATIONS),
                'shared_fields' => SensitiveProfile::normalizeShared($profile->shared_fields),
                'progressive' => app(ProgressiveProfileState::class)->for($user),
            ];
        }

        return $data;
    }

    /**
     * Valeur d'une liste fermée (identité de genre, orientation), null si inconnue.
     *
     * @param  list<string>  $allowed
     */
    private static function closedValue(mixed $value, array $allowed): ?string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function settings(?UserSetting $settings): ?array
    {
        if ($settings === null) {
            return null;
        }

        return [
            'theme' => $settings->theme,
            'language' => $settings->language,
            'notification_prefs' => $settings->notificationPrefs(),
            'notification_digest' => $settings->notification_digest,
            'mask_sensitive_notifications' => (bool) $settings->mask_sensitive_notifications,
            'sos_share_gps' => (bool) $settings->sos_share_gps,
            'pin_enabled' => (bool) $settings->pin_enabled,
            'neutral_tab_title' => (bool) $settings->neutral_tab_title,
            'sounds_enabled' => (bool) ($settings->sounds_enabled ?? true),
        ];
    }
}
