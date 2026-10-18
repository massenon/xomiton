<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Auth;

use App\Application\Auth\ProfileData;
use App\Enums\AgeRange;
use App\Enums\AllyStatus;
use App\Enums\MemberType;
use App\Rules\CommuneInZone;
use App\Rules\PhoneBj;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PATCH /api/v1/me/profile : phases 2 et 3 de l'inscription (facultatives) ou profil progressif (P6-06 : tranche
 * d'âge, centres d'intérêt, statut allié·e) ; chaque champ absent est laissé tel quel. Choisir « pair » place le compte en attente de validation (RG-03).
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
            'member_type' => ['sometimes', 'nullable', Rule::in([MemberType::Member->value, MemberType::Peer->value])],
            'trusted_contact_phone' => ['sometimes', 'nullable', 'string', new PhoneBj],
            'trusted_contact_name' => ['sometimes', 'nullable', 'string', 'max:80'],
            'discreet_mode' => ['sometimes', 'nullable', 'boolean'],
            // Profil progressif (P6-06) : tranche d'âge, centres d'intérêt (slugs de newsletter_interests, [] = aucun),
            // statut allié·e / personne ressource (Q10 c ; « aucun » l'efface).
            'age_range' => ['sometimes', 'nullable', Rule::enum(AgeRange::class)],
            'interests' => ['sometimes', 'nullable', 'array', 'max:20'],
            'interests.*' => ['string', 'distinct', Rule::exists('newsletter_interests', 'slug')],
            'ally_status' => ['sometimes', 'nullable', Rule::in([...AllyStatus::values(), self::ALLY_STATUS_NONE])],
        ];
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
        ];
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

        return new ProfileData(
            pronouns: isset($v['pronouns']) ? (string) $v['pronouns'] : null,
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
        );
    }
}
