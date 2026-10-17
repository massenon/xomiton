<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Auth;

use App\Application\Auth\ProfileData;
use App\Enums\MemberType;
use App\Rules\CommuneInZone;
use App\Rules\PhoneBj;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PATCH /api/v1/me/profile : phases 2 et 3 de l'inscription (facultatives) ou profil progressif ; chaque champ absent
 * est laissé tel quel. Choisir « pair » place le compte en attente de validation (RG-03).
 */
final class UpdateProfileRequest extends FormRequest
{
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
        );
    }
}
