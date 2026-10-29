<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Auth;

use App\Application\Account\Profile\SensitiveDataConsent;
use App\Application\Account\Profile\SensitiveProfileData;
use App\Application\Auth\ProfileData;
use App\Domain\Account\ProgressiveProfile;
use App\Domain\Account\SensitiveProfile;
use App\Enums\AgeRange;
use App\Enums\AllyStatus;
use App\Enums\MemberType;
use App\Models\User;
use App\Rules\CommuneInZone;
use App\Rules\PhoneBj;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * PATCH /api/v1/me/profile : phases 2 et 3 de l'inscription (facultatives) ou profil progressif (P6-06 : tranche
 * d'âge, centres d'intérêt, statut allié·e ; P8-08 : identité de genre et orientation sous consentement, champs
 * partagés, groupes traités) ; chaque champ absent est laissé tel quel. Choisir « pair » place le compte en attente de
 * validation (RG-03).
 */
final class UpdateProfileRequest extends FormRequest
{
    /** Valeur de `ally_status` qui efface le statut (« Aucun des deux »). */
    public const ALLY_STATUS_NONE = 'aucun';

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'pronouns' => ['sometimes', 'nullable', 'string', 'max:30'],
            'zone_id' => ['sometimes', 'nullable', 'integer', Rule::exists('zones', 'id')->where('is_active', true), 'required_with:commune_id'],
            'commune_id' => ['sometimes', 'nullable', 'integer', new CommuneInZone('zone_id')],
            'member_type' => ['sometimes', 'nullable', Rule::in([MemberType::Member->value])],
            'trusted_contact_phone' => ['sometimes', 'nullable', 'string', new PhoneBj],
            'trusted_contact_name' => ['sometimes', 'nullable', 'string', 'max:80'],
            'discreet_mode' => ['sometimes', 'nullable', 'boolean'],
            // Profil progressif (P6-06) : tranche d'âge, centres d'intérêt (slugs de newsletter_interests, [] = aucun),
            // statut allié·e / personne ressource (Q10 c ; « aucun » l'efface).
            'age_range' => ['sometimes', 'nullable', Rule::in([...array_column(AgeRange::cases(), 'value'), SensitiveProfile::PREFER_NOT])],
            'interests' => ['sometimes', 'nullable', 'array', 'max:20'],
            'interests.*' => ['string', 'distinct', Rule::exists('newsletter_interests', 'slug')],
            'ally_status' => ['sometimes', 'nullable', Rule::in([...AllyStatus::values(), self::ALLY_STATUS_NONE])],
            // Profil progressif (P8-08) : données sensibles chiffrées, consentement `sensitive_data` requis (after()) ;
            // `prefer_not` efface ; `shared_fields` = champs sensibles montrés sur le profil (choix explicite, [] = aucun) ;
            // `answered_groups` = groupes traités sans valeur (« Je préfère ne pas le dire »).
            'sensitive_consent' => ['sometimes', 'nullable', 'boolean'],
            'gender_identity' => ['sometimes', 'nullable', Rule::in([...SensitiveProfile::GENDER_IDENTITIES, SensitiveProfile::PREFER_NOT])],
            'orientation' => ['sometimes', 'nullable', Rule::in([...SensitiveProfile::ORIENTATIONS, SensitiveProfile::PREFER_NOT])],
            'shared_fields' => ['sometimes', 'nullable', 'array', 'max:'.count(SensitiveProfile::SHAREABLE)],
            'shared_fields.*' => ['string', 'distinct', Rule::in(SensitiveProfile::SHAREABLE)],
            'answered_groups' => ['sometimes', 'nullable', 'array', 'max:'.count(ProgressiveProfile::GROUPS)],
            'answered_groups.*' => ['string', 'distinct', Rule::in(ProgressiveProfile::GROUPS)],
            // P8-08 : interrupteur « Afficher ma zone sur mon profil » du groupe Ville (département seulement, Q80).
            'show_zone' => ['sometimes', 'nullable', 'boolean'],
        ];
    }

    /**
     * Consentement `sensitive_data` (P8-08) : enregistrer une identité de genre ou une orientation, ou les rendre visibles
     * sur le profil, exige un consentement en cours ou accordé dans la même requête ; un retrait dans la même requête
     * l'emporte (rien n'est enregistré).
     *
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $consent = $this->input('sensitive_consent');
            $fields = array_values(array_filter(
                SensitiveProfile::CONSENT_REQUIRED,
                fn (string $field): bool => is_string($this->input($field)) && $this->input($field) !== SensitiveProfile::PREFER_NOT,
            ));
            $shared = array_intersect(SensitiveProfile::CONSENT_REQUIRED, array_filter((array) $this->input('shared_fields', []), 'is_string'));

            if ($fields === [] && $shared === []) {
                return;
            }

            $user = $this->user();
            $granted = $consent === null
                ? $user instanceof User && app(SensitiveDataConsent::class)->granted($user)
                : filter_var($consent, FILTER_VALIDATE_BOOLEAN);

            if ($granted) {
                return;
            }

            foreach ($fields === [] ? ['shared_fields'] : $fields as $field) {
                $validator->errors()->add($field, (string) __('profile.progressive.sensitive_required'));
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'age_range' => __('profile.progressive.groups.age.title'),
            'interests' => __('profile.progressive.groups.interests.title'),
            'interests.*' => __('profile.progressive.groups.interests.title'),
            'ally_status' => __('profile.progressive.groups.ally_status.title'),
            'gender_identity' => __('profile.progressive.groups.gender_identity.title'),
            'orientation' => __('profile.progressive.groups.orientation.title'),
            'sensitive_consent' => __('profile.progressive.consent_attribute'),
            'shared_fields' => __('profile.progressive.shared_attribute'),
            'shared_fields.*' => __('profile.progressive.shared_attribute'),
            'show_zone' => __('profile.progressive.groups.location.show_zone'),
        ];
    }

    /**
     * Partie sensible et groupes traités (UpdateMemberProfile, P8-08).
     */
    public function toSensitiveData(): SensitiveProfileData
    {
        /** @var array<string, mixed> $v */
        $v = $this->validated();
        $gender = $v['gender_identity'] ?? null;
        $orientation = $v['orientation'] ?? null;

        return new SensitiveProfileData(
            consent: array_key_exists('sensitive_consent', $v) && $v['sensitive_consent'] !== null ? (bool) $v['sensitive_consent'] : null,
            genderIdentity: is_string($gender) && $gender !== SensitiveProfile::PREFER_NOT ? $gender : null,
            clearGenderIdentity: $gender === SensitiveProfile::PREFER_NOT,
            orientation: is_string($orientation) && $orientation !== SensitiveProfile::PREFER_NOT ? $orientation : null,
            clearOrientation: $orientation === SensitiveProfile::PREFER_NOT,
            sharedFields: isset($v['shared_fields']) && is_array($v['shared_fields']) ? array_values(array_map('strval', $v['shared_fields'])) : null,
            answeredGroups: ProgressiveProfile::normalize(isset($v['answered_groups']) && is_array($v['answered_groups']) ? $v['answered_groups'] : []),
            showZone: isset($v['show_zone']) ? (bool) $v['show_zone'] : null,
        );
    }

    public function toProfileData(): ProfileData
    {
        /** @var array<string, mixed> $v */
        $v = $this->validated();

        return self::profileDataFrom($v);
    }

    /**
     * Construit le DTO depuis un tableau validé (réutilisé par l'inscription pour la clé `profile`).
     *
     * @param  array<string, mixed>  $v
     */
    public static function profileDataFrom(array $v): ProfileData
    {
        $memberType = $v['member_type'] ?? null;
        $pronouns = $v['pronouns'] ?? null;

        return new ProfileData(
            pronouns: is_string($pronouns) && $pronouns !== SensitiveProfile::PREFER_NOT ? $pronouns : null,
            zoneId: isset($v['zone_id']) ? (int) $v['zone_id'] : null,
            communeId: isset($v['commune_id']) ? (int) $v['commune_id'] : null,
            memberType: is_string($memberType) && $memberType !== '' ? MemberType::from($memberType) : null,
            trustedContactPhone: isset($v['trusted_contact_phone']) ? (string) $v['trusted_contact_phone'] : null,
            trustedContactName: isset($v['trusted_contact_name']) ? (string) $v['trusted_contact_name'] : null,
            discreetMode: array_key_exists('discreet_mode', $v) && $v['discreet_mode'] !== null ? (bool) $v['discreet_mode'] : null,
            ageRange: isset($v['age_range']) && is_string($v['age_range']) ? AgeRange::tryFrom($v['age_range']) : null,
            interests: isset($v['interests']) && is_array($v['interests']) ? array_values(array_map('strval', $v['interests'])) : null,
            allyStatus: isset($v['ally_status']) && is_string($v['ally_status']) ? AllyStatus::tryFrom($v['ally_status']) : null,
            clearAllyStatus: ($v['ally_status'] ?? null) === self::ALLY_STATUS_NONE,
            clearPronouns: $pronouns === SensitiveProfile::PREFER_NOT,
            clearAgeRange: ($v['age_range'] ?? null) === SensitiveProfile::PREFER_NOT,
        );
    }
}
