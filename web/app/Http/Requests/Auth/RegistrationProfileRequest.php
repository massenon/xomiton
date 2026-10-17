<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Application\Auth\ProfileData;
use App\Enums\MemberType;
use App\Models\Zone;
use App\Rules\CommuneInZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Phase 2 (facultative) : pronoms, département → commune, type de membre (membre / pair éducateur·rice, Q10 c).
 */
final class RegistrationProfileRequest extends FormRequest
{
    /** @var list<string> */
    public const PRONOUNS = ['elle', 'il', 'iel', 'autre', 'nr'];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        // Les listes déroulantes renvoient '' pour « Choisir… » : traité comme non renseigné.
        $this->merge(array_map(static fn (mixed $v): mixed => $v === '' ? null : $v, $this->only(['pronouns', 'zone_id', 'commune_id', 'member_type'])));
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'pronouns' => ['nullable', 'string', Rule::in(self::PRONOUNS)],
            'zone_id' => ['nullable', 'integer', Rule::exists(Zone::class, 'id')->where('is_active', true)],
            'commune_id' => ['nullable', 'integer', 'required_with:zone_id', new CommuneInZone('zone_id')],
            'member_type' => ['nullable', 'string', Rule::in([MemberType::Member->value, MemberType::Peer->value])],
        ];
    }

    public function toProfileData(): ProfileData
    {
        $memberType = $this->input('member_type');

        return new ProfileData(
            pronouns: $this->filled('pronouns') ? (string) $this->string('pronouns') : null,
            zoneId: $this->filled('zone_id') ? (int) $this->integer('zone_id') : null,
            communeId: $this->filled('commune_id') ? (int) $this->integer('commune_id') : null,
            memberType: is_string($memberType) && $memberType !== '' ? MemberType::from($memberType) : null,
        );
    }
}
