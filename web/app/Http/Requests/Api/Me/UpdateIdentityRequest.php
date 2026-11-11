<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Me;

use App\Application\Account\Settings\PseudonymChanges;
use App\Models\User;
use App\Rules\CommuneInZone;
use App\Rules\PhoneBj;
use App\Rules\Pseudo;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * PATCH /api/v1/me/identity (P8-09, section « Profil » de /parametres) : nom complet **facultatif** (RG-02 : l'inscription
 * ne le demande pas, vide = effacé, Q81), pseudonyme (unique sans casse, le sien excepté, anciens pseudonymes réservés et
 * délai entre deux changements : PseudonymChanges), téléphone béninois, département et commune (liste en cascade ; une
 * commune seule est vérifiée contre le département actuel du compte), présentation (200 caractères, comme le
 * prototype). Chaque champ absent est laissé tel quel ; l'e-mail ne se change pas ici.
 */
final class UpdateIdentityRequest extends FormRequest
{
    public const BIO_MAX = 200;

    public const NAME_MIN = 2;

    public const NAME_MAX = 120;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->user();

        return [
            'name' => ['sometimes', 'nullable', 'string', 'min:'.self::NAME_MIN, 'max:'.self::NAME_MAX],
            'pseudo' => ['sometimes', 'required', 'string', new Pseudo((int) $user->getKey())],
            'phone' => ['sometimes', 'nullable', 'string', new PhoneBj],
            'bio' => ['sometimes', 'nullable', 'string', 'max:'.self::BIO_MAX],
            'zone_id' => ['sometimes', 'nullable', 'integer', Rule::exists('zones', 'id')->where('is_active', true), 'required_with:commune_id'],
            'commune_id' => ['sometimes', 'nullable', 'integer', $this->has('zone_id')
                ? new CommuneInZone('zone_id')
                : static function (string $attribute, mixed $value, Closure $fail) use ($user): void {
                    (new CommuneInZone('zone_id'))->setData(['zone_id' => $user->zone_id])->validate($attribute, $value, $fail);
                }],
        ];
    }

    /**
     * Délai entre deux changements de pseudonyme (Q81) : un nouveau pseudonyme (autre que le sien, casse comprise) est
     * refusé tant que le délai ouvert par le précédent changement court.
     *
     * @return array<int, Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var User|null $user */
                $user = $this->user();
                $pseudo = $this->input('pseudo');

                if (! $user instanceof User || ! is_string($pseudo) || $validator->errors()->has('pseudo')
                    || ! PseudonymChanges::differs((string) $user->pseudo, $pseudo)) {
                    return;
                }

                $remaining = app(PseudonymChanges::class)->cooldownRemaining($user);

                if ($remaining > 0) {
                    $validator->errors()->add('pseudo', __('profile.settings.errors.pseudo_cooldown', ['hours' => (int) ceil($remaining / 3600)]));
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.min' => __('profile.settings.errors.name_min', ['min' => self::NAME_MIN]),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('profile.settings.profile.name'),
            'pseudo' => __('profile.settings.profile.pseudo'),
            'phone' => __('profile.settings.profile.phone'),
            'bio' => __('profile.about.bio'),
            'zone_id' => __('profile.settings.profile.zone'),
            'commune_id' => __('profile.settings.profile.commune'),
        ];
    }

    /**
     * @return array{name?: string|null, pseudo?: string, phone?: string|null, bio?: string|null, zone_id?: int|null, commune_id?: int|null}
     */
    public function toIdentity(): array
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();
        $data = [];

        if (array_key_exists('pseudo', $validated)) {
            $data['pseudo'] = (string) $validated['pseudo'];
        }

        foreach (['name', 'phone', 'bio'] as $field) {
            if (array_key_exists($field, $validated)) {
                $data[$field] = $validated[$field] === null ? null : (string) $validated[$field];
            }
        }

        foreach (['zone_id', 'commune_id'] as $field) {
            if (array_key_exists($field, $validated)) {
                $data[$field] = $validated[$field] === null ? null : (int) $validated[$field];
            }
        }

        return $data;
    }
}
