<?php

declare(strict_types=1);

namespace App\Http\Resources\Member;

use App\Application\Auth\CompleteRegistrationProfile;
use App\Domain\Account\SensitiveProfile;
use App\Enums\AllyStatus;
use App\Enums\MemberType;
use App\Enums\UserStatus;
use App\Models\NewsletterInterest;
use App\Models\Profile;
use App\Models\User;
use App\Support\LocaleFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * En-tête et « À propos » d'un profil (P8-07 : `/profil`, `/membres/{pseudo}`, `GET members/{pseudo}`, docs/API.md §18).
 * RG-02 : le pseudonyme est la seule identité ; ni nom réel, ni e-mail, ni téléphone, ni contact de confiance, ni
 * tranche d'âge (« jamais affiché »). Zone : si la personne l'affiche (`show_zone`), et pour un autre compte le
 * département seulement (jamais la commune, comme ProfileConnectionResource ; Q80). Type de membre et rôle pour un
 * autre compte : « membre » ou « pair », et « pair » seulement une fois validé (statut actif, RG-03) ; allié·e et
 * personne ressource (Q10) ne passent que par `shared_fields` (statut allié·e). Champs sensibles (identité de
 * genre, orientation, statut allié·e) : pour un autre compte, **seulement** ceux que la personne a explicitement
 * choisi de montrer (`shared_fields`) et, pour le genre et l'orientation, tant que son consentement court
 * (SensitiveProfile::disclosed) ; pour elle-même, tous ceux qu'elle a renseignés, avec leur état « partagé ».
 *
 * @mixin User
 */
final class MemberProfileResource extends JsonResource
{
    /** Rôles affichés avec le badge « Rôle vérifié » (prototype `profile-head.php` l.24). */
    private const VERIFIED_ROLES = ['pair', 'moderateur', 'admin', 'superadmin'];

    /** @var array{posts: int, connections: int, followers: int, following: int}|null */
    private ?array $stats = null;

    /** @var array{following: bool, follows_you: bool, connected: bool}|null */
    private ?array $relationship = null;

    private bool $isSelf = false;

    private bool $consent = false;

    private bool $canMessage = false;

    /**
     * @param  array{posts: int, connections: int, followers: int, following: int}  $stats
     * @param  array{following: bool, follows_you: bool, connected: bool}|null  $relationship
     */
    public function withContext(bool $isSelf, array $stats, ?array $relationship, bool $sensitiveConsent, bool $canMessage = false): self
    {
        $this->isSelf = $isSelf;
        $this->stats = $stats;
        $this->relationship = $relationship;
        $this->consent = $sensitiveConsent;
        $this->canMessage = $canMessage;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;
        $profile = $user->profile;
        $role = $user->getRoleNames()->first();
        $fmt = LocaleFormatter::make();
        $showZone = $this->isSelf || (bool) ($profile?->show_zone ?? true);
        $memberType = $this->isSelf ? $profile?->member_type : self::publicMemberType($user, $profile);

        // RG-03 : un compte pair en attente de validation reste « membre » aux yeux des autres.
        if (! $this->isSelf && $role === CompleteRegistrationProfile::PEER_ROLE && $user->status !== UserStatus::Active) {
            $role = 'membre';
        }

        $data = [
            'id' => (int) $user->getKey(),
            'pseudo' => (string) $user->pseudo,
            'url' => $this->isSelf ? route('profil') : route('membres.show', ['pseudo' => $user->pseudo]),
            'is_self' => $this->isSelf,
            'avatar_url' => $user->avatar_url,
            'cover_url' => $profile?->coverUrl() ?? asset(Profile::DEFAULT_COVER),
            'role' => $role,
            'role_label' => (string) __('profile.roles.'.($role ?? 'membre')),
            'verified' => in_array($role, self::VERIFIED_ROLES, true) && $user->status === UserStatus::Active,
            'member_type' => $memberType?->value,
            'member_type_label' => $memberType?->label(),
            'pronouns' => $profile?->pronouns,
            'bio' => $profile?->bio,
            'zone_label' => $showZone ? self::zoneLabel($user, withCommune: $this->isSelf) : null,
            'joined_at' => $user->created_at?->toIso8601String(),
            'joined_label' => $user->created_at !== null ? $fmt->monthYear($user->created_at) : null,
            'interests' => self::interests($profile),
            'sensitive' => $this->sensitive($profile),
            'stats' => $this->stats ?? ['posts' => 0, 'connections' => 0, 'followers' => 0, 'following' => 0],
            'relationship' => $this->isSelf ? null : $this->relationship,
            'message_url' => ! $this->isSelf && $this->canMessage ? route('messages', ['with' => $user->getKey()]) : null,
        ];

        if ($this->isSelf) {
            $data += [
                'completion' => (int) ($profile?->completion ?? 0),
                'visibility' => $profile?->visibility?->value,
                'visibility_label' => $profile?->visibility?->label(),
                'show_zone' => (bool) ($profile?->show_zone ?? true),
                'has_avatar' => filled($profile?->avatar_path),
                'has_cover' => filled($profile?->cover_path),
                // Avatar et couverture se voient de la communauté : e-mail confirmé exigé (route `verified`, P2-03).
                'can_change_images' => $user->hasVerifiedEmail(),
            ];
        }

        return $data;
    }

    /** Type de membre montré à un autre compte : « pair » validé (statut actif), sinon « membre ». */
    private static function publicMemberType(User $user, ?Profile $profile): ?MemberType
    {
        if ($profile?->member_type === null) {
            return null;
        }

        return $profile->member_type === MemberType::Peer && $user->status === UserStatus::Active ? MemberType::Peer : MemberType::Member;
    }

    /** Sa propre fiche : « commune, département » ; celle d'une autre personne : le département seulement. */
    private static function zoneLabel(User $user, bool $withCommune): ?string
    {
        $parts = array_filter([$withCommune ? $user->commune?->name : null, $user->zone?->name], static fn (?string $part): bool => $part !== null && $part !== '');

        return $parts === [] ? null : implode(', ', $parts);
    }

    /**
     * @return list<array{slug: string, label: string}>
     */
    private static function interests(?Profile $profile): array
    {
        $slugs = array_values(array_filter((array) ($profile?->interests ?? []), 'is_string'));

        if ($slugs === []) {
            return [];
        }

        $labels = NewsletterInterest::query()->whereIn('slug', $slugs)->pluck('label', 'slug')->all();

        return array_values(array_map(
            static fn (string $slug): array => ['slug' => $slug, 'label' => (string) $labels[$slug]],
            array_values(array_filter($slugs, static fn (string $slug): bool => isset($labels[$slug]))),
        ));
    }

    /**
     * @return list<array{field: string, label: string, value: string, value_label: string, shared: bool}>
     */
    private function sensitive(?Profile $profile): array
    {
        if ($profile === null) {
            return [];
        }

        $values = [
            SensitiveProfile::GENDER_IDENTITY => self::closed($profile->gender_identity, SensitiveProfile::GENDER_IDENTITIES),
            SensitiveProfile::ORIENTATION => self::closed($profile->orientation, SensitiveProfile::ORIENTATIONS),
            SensitiveProfile::ALLY_STATUS => AllyStatus::tryFrom((string) $profile->ally_status)?->value,
        ];
        $shared = SensitiveProfile::normalizeShared($profile->shared_fields);
        $visible = $this->isSelf
            ? array_filter($values, static fn (?string $value): bool => $value !== null)
            : SensitiveProfile::disclosed($values, $profile->shared_fields, $this->consent);

        $out = [];

        foreach ($visible as $field => $value) {
            $out[] = [
                'field' => $field,
                'label' => (string) __('profile.progressive.groups.'.$field.'.title'),
                'value' => (string) $value,
                'value_label' => self::valueLabel($field, (string) $value),
                'shared' => in_array($field, $shared, true),
            ];
        }

        return $out;
    }

    /**
     * @param  list<string>  $allowed
     */
    private static function closed(mixed $value, array $allowed): ?string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : null;
    }

    private static function valueLabel(string $field, string $value): string
    {
        return $field === SensitiveProfile::ALLY_STATUS
            ? (AllyStatus::tryFrom($value)?->label() ?? $value)
            : (string) __('profile.progressive.groups.'.$field.'.options.'.$value);
    }
}
