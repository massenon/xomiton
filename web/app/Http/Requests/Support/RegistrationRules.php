<?php

declare(strict_types=1);

namespace App\Http\Requests\Support;

use App\Application\Auth\OtpService;
use App\Application\Auth\RegisterData;
use App\Application\Auth\Support\RegistrationOptions;
use App\Application\Shared\Countries;
use App\Enums\AllyRole;
use App\Enums\MemberType;
use App\Models\User;
use App\Rules\CommuneInZone;
use App\Rules\EmailAddress;
use App\Rules\Pseudo;
use App\Rules\RegistrationPhone;
use App\Rules\StrongPassword;
use App\Rules\ZoneInCountry;
use Illuminate\Validation\Rule;

/**
 * Règles UNIQUES de l'inscription en 3 étapes (décision client du 11/11/2026), partagées par la requête web
 * (App\Http\Requests\Auth\RegisterRequest) et la requête API (App\Http\Requests\Api\Auth\RegisterRequest) : mêmes champs,
 * mêmes règles conditionnelles, mêmes messages, mêmes clés d'erreur.
 *
 *  - étape 1 : first_name, last_name, pseudo, email, age ;
 *  - étape 2 : pronouns, country_code, city?, phone?, zone_id? puis commune_id? (facultatives, d'une zone du pays choisi), member_type ;
 *  - étape 3 : membre → gender_identity, orientation (+ `*_other` si « autre »), sensitive_data accepté ; allié·e →
 *    ally_role (+ ally_role_other si « autre ») ; puis password + confirmation, cgu, privacy, invitation?.
 *
 * `normalize()` (avant validation) vide les champs qui ne concernent pas le choix fait (une allié·e n'a ni identité de
 * genre ni orientation ; « autre » seul garde sa précision) : ils sont ignorés et
 * jamais enregistrés.
 */
final class RegistrationRules
{
    /** Prénom et nom : lettres (accents compris), espaces, apostrophe, point et trait d'union. */
    public const NAME_PATTERN = "/^[\\p{L}\\p{M}][\\p{L}\\p{M} '’.\\-]*$/u";

    /** @var list<string> Champs texte facultatifs : une saisie vide vaut « non renseigné ». */
    private const NULLABLE_TEXT = ['city', 'phone', 'invitation', 'gender_identity_other', 'orientation_other', 'ally_role_other'];

    /** @var list<string> Champs d'étape 3 propres à un membre. */
    private const MEMBER_ONLY = ['gender_identity', 'orientation', 'gender_identity_other', 'orientation_other', 'sensitive_data'];

    private function __construct() {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        $minAge = RegistrationOptions::minAge();
        $maxAge = max($minAge, (int) config('xomiton.accounts.age_max', 99));

        return [
            // Étape 1.
            'first_name' => ['required', 'string', 'max:60', 'regex:'.self::NAME_PATTERN],
            'last_name' => ['required', 'string', 'max:60', 'regex:'.self::NAME_PATTERN],
            'pseudo' => ['required', 'string', new Pseudo],
            'email' => ['required', 'string', 'max:190', new EmailAddress, Rule::unique(User::class, 'email')],
            'age' => ['required', 'integer', 'min:'.$minAge, 'max:'.$maxAge],

            // Étape 2.
            'pronouns' => ['required', 'string', Rule::in(RegistrationOptions::PRONOUNS)],
            'country_code' => ['required', 'string', 'size:2', Rule::in(Countries::CODES)],
            'city' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30', new RegistrationPhone],
            'zone_id' => ['nullable', 'integer', 'required_with:commune_id', new ZoneInCountry('country_code')],
            'commune_id' => ['nullable', 'integer', new CommuneInZone('zone_id')],
            'member_type' => ['required', 'string', Rule::in(RegistrationOptions::MEMBER_TYPES)],

            // Étape 3 : membre.
            'gender_identity' => ['required_if:member_type,membre', 'nullable', 'string', Rule::in(RegistrationOptions::sensitiveValues('gender_identity'))],
            'orientation' => ['required_if:member_type,membre', 'nullable', 'string', Rule::in(RegistrationOptions::sensitiveValues('orientation'))],
            'gender_identity_other' => ['required_if:gender_identity,autre', 'nullable', 'string', 'max:120'],
            'orientation_other' => ['required_if:orientation,autre', 'nullable', 'string', 'max:120'],
            'sensitive_data' => ['nullable', 'boolean', 'accepted_if:member_type,membre'],

            // Étape 3 : allié·e.
            'ally_role' => ['required_if:member_type,allie', 'nullable', 'string', Rule::in(AllyRole::values())],
            'ally_role_other' => ['required_if:ally_role,autre', 'nullable', 'string', 'max:120'],

            // Étape 3 : sécurité et consentements.
            'password' => ['required', 'string', 'max:255', 'confirmed', new StrongPassword],
            'cgu' => ['accepted'],
            'privacy' => ['accepted'],
            // Lien / code d'invitation à l'équipe : l'inscription reste celle d'un membre, le rôle suit à la validation.
            'invitation' => ['nullable', 'string', 'max:40'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        $min = ['min_age' => RegistrationOptions::minAge()];

        return [
            'first_name.required' => (string) __('auth.register.errors.first_name_required'),
            'first_name.regex' => (string) __('auth.register.errors.name_format'),
            'last_name.required' => (string) __('auth.register.errors.last_name_required'),
            'last_name.regex' => (string) __('auth.register.errors.name_format'),
            'email.unique' => (string) __('auth.api.email_taken'),
            'age.required' => (string) __('auth.register.errors.age_required'),
            'age.integer' => (string) __('auth.register.errors.age_number'),
            'age.min' => (string) __('auth.register.errors.age_min', $min),
            'age.max' => (string) __('auth.register.errors.age_max'),
            'pronouns.required' => (string) __('auth.register.errors.pronouns_required'),
            'pronouns.in' => (string) __('auth.register.errors.pronouns_required'),
            'country_code.required' => (string) __('auth.register.errors.country_required'),
            'country_code.size' => (string) __('auth.register.errors.country_unknown'),
            'country_code.in' => (string) __('auth.register.errors.country_unknown'),
            'member_type.required' => (string) __('auth.register.errors.member_type_required'),
            'member_type.in' => (string) __('auth.register.errors.member_type_required'),
            'gender_identity.required_if' => (string) __('auth.register.errors.gender_identity_required'),
            'orientation.required_if' => (string) __('auth.register.errors.orientation_required'),
            'gender_identity_other.required_if' => (string) __('auth.register.errors.other_required'),
            'orientation_other.required_if' => (string) __('auth.register.errors.other_required'),
            'sensitive_data.accepted_if' => (string) __('auth.register.errors.sensitive_data_required'),
            'ally_role.required_if' => (string) __('auth.register.errors.ally_role_required'),
            'ally_role_other.required_if' => (string) __('auth.register.errors.other_required'),
            'password.confirmed' => (string) __('validation.custom.password.confirmed'),
            'cgu.accepted' => (string) __('validation.custom.cgu.accepted'),
            'privacy.accepted' => (string) __('validation.custom.privacy.accepted'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        return [
            'first_name' => (string) __('validation.attributes.first_name'),
            'last_name' => (string) __('validation.attributes.last_name'),
            'age' => (string) __('validation.attributes.age'),
            'country_code' => (string) __('validation.attributes.country_code'),
            'city' => (string) __('validation.attributes.city'),
            'gender_identity' => (string) __('validation.attributes.gender_identity'),
            'orientation' => (string) __('validation.attributes.orientation'),
            'gender_identity_other' => (string) __('validation.attributes.gender_identity_other'),
            'orientation_other' => (string) __('validation.attributes.orientation_other'),
            'ally_role' => (string) __('validation.attributes.ally_role'),
            'ally_role_other' => (string) __('validation.attributes.ally_role_other'),
        ];
    }

    /**
     * Entrée prête à valider : textes rognés, saisies vides ramenées à null, e-mail normalisé, pays en majuscules, puis
     * champs sans objet vidés (voir la description de la classe). Renvoie les seules clés à fusionner dans la requête.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalize(array $input): array
    {
        $out = [];

        foreach (['first_name', 'last_name', 'pseudo', 'pronouns', 'member_type', 'gender_identity', 'orientation', 'ally_role'] as $key) {
            if (array_key_exists($key, $input) && is_string($input[$key])) {
                $out[$key] = trim($input[$key]);
            }
        }

        foreach (self::NULLABLE_TEXT as $key) {
            if (array_key_exists($key, $input) && (is_string($input[$key]) || $input[$key] === null)) {
                $text = trim((string) $input[$key]);
                $out[$key] = $text === '' ? null : $text;
            }
        }

        // Les listes déroulantes renvoient '' pour « Choisir… » : traité comme non renseigné.
        foreach (['gender_identity', 'orientation', 'ally_role', 'zone_id', 'commune_id'] as $key) {
            if (array_key_exists($key, $input) && $input[$key] === '') {
                $out[$key] = null;
            }
        }

        if (isset($input['email']) && is_string($input['email'])) {
            $out['email'] = OtpService::normalizeEmail($input['email']);
        }

        if (isset($input['country_code']) && is_string($input['country_code'])) {
            $out['country_code'] = strtoupper(trim($input['country_code']));
        }

        $merged = $out + $input;
        $memberType = $merged['member_type'] ?? null;

        // « Vous êtes ? » : ce qui ne concerne pas le choix fait est ignoré.
        if ($memberType === MemberType::Ally->value) {
            foreach (self::MEMBER_ONLY as $key) {
                $out[$key] = null;
            }
        } elseif ($memberType === MemberType::Member->value) {
            $out['ally_role'] = null;
            $out['ally_role_other'] = null;
        }

        // La précision n'a de sens que pour la réponse « autre ».
        foreach (['gender_identity', 'orientation', 'ally_role'] as $key) {
            if (($out[$key] ?? $merged[$key] ?? null) !== RegistrationOptions::OTHER) {
                $out[$key.'_other'] = null;
            }
        }

        return $out;
    }

    /**
     * Données validées → cas d'usage RegisterUser.
     *
     * @param  array<string, mixed>  $v  résultat de `validated()`
     */
    public static function toData(array $v, ?string $ip, ?string $userAgent): RegisterData
    {
        $text = static fn (string $key): ?string => isset($v[$key]) && is_string($v[$key]) && $v[$key] !== '' ? $v[$key] : null;
        $id = static fn (string $key): ?int => isset($v[$key]) && is_numeric($v[$key]) ? (int) $v[$key] : null;
        $memberType = RegistrationOptions::memberTypeOf((string) ($v['member_type'] ?? ''));
        $allyRole = $text('ally_role');

        return new RegisterData(
            firstName: (string) ($v['first_name'] ?? ''),
            lastName: (string) ($v['last_name'] ?? ''),
            pseudo: (string) ($v['pseudo'] ?? ''),
            email: (string) ($v['email'] ?? ''),
            age: (int) ($v['age'] ?? 0),
            pronouns: (string) ($v['pronouns'] ?? ''),
            countryCode: (string) ($v['country_code'] ?? ''),
            memberType: $memberType,
            password: (string) ($v['password'] ?? ''),
            acceptsTerms: filter_var($v['cgu'] ?? false, FILTER_VALIDATE_BOOLEAN),
            acceptsPrivacy: filter_var($v['privacy'] ?? false, FILTER_VALIDATE_BOOLEAN),
            city: $text('city'),
            phone: $text('phone'),
            zoneId: $id('zone_id'),
            communeId: $id('commune_id'),
            genderIdentity: $text('gender_identity'),
            orientation: $text('orientation'),
            genderIdentityOther: $text('gender_identity_other'),
            orientationOther: $text('orientation_other'),
            acceptsSensitiveData: filter_var($v['sensitive_data'] ?? false, FILTER_VALIDATE_BOOLEAN),
            allyRole: $allyRole === null ? null : AllyRole::tryFrom($allyRole),
            allyRoleOther: $text('ally_role_other'),
            ip: $ip,
            userAgent: $userAgent,
            invitationCode: $text('invitation'),
        );
    }
}
