<?php

declare(strict_types=1);

namespace App\Application\Directory;

use App\Application\Cases\ValidationCatalog;
use App\Domain\Cases\ValidationRuleKey;
use App\Enums\DirectoryRole;
use App\Rules\CommuneInZone;
use App\Rules\EmailAddress;
use App\Rules\PhoneBj;
use Illuminate\Validation\Rule;

/**
 * Règles d'une fiche de l'annuaire (P11-04, prototype admin/annuaire.php l.34-37), selon le catalogue activable
 * (ValidationCatalog) : téléphone et WhatsApp béninois (`phone_bj`), adresse e-mail (`email`), commune du département
 * (`commune_in_zone`, sinon la commune doit seulement exister et être active). Mêmes règles pour la modale, l'import
 * CSV. Le point focal **national** est forcément un point focal (c'est lui que cible l'escalade
 * RG-05) ; une fiche doit pouvoir être jointe (téléphone, WhatsApp, e-mail ou compte lié).
 */
final class DirectoryContactRules
{
    public function __construct(private readonly ValidationCatalog $catalog) {}

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'role' => ['required', Rule::enum(DirectoryRole::class)],
            'org' => ['nullable', 'string', 'max:120'],
            'availability' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30', new PhoneBj],
            'email' => ['nullable', 'string', 'max:190', new EmailAddress],
            'whatsapp' => ['nullable', 'string', 'max:30', new PhoneBj],
            'zone_id' => ['nullable', 'integer', Rule::exists('zones', 'id')->where('is_active', true)],
            'commune_id' => ['nullable', 'integer', $this->catalog->isActive(ValidationRuleKey::CommuneInZone)
                ? new CommuneInZone('zone_id')
                : Rule::exists('communes', 'id')->where('is_active', true)],
            'is_active' => ['boolean'],
            'is_public' => ['boolean'],
            'is_emergency' => ['boolean'],
            'is_national_focal' => ['boolean'],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
        ];
    }

    /**
     * Contrôles entre champs, une fois les règles passées : champ => message (null : rien à signaler).
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, string>
     */
    public static function crossChecks(array $validated): array
    {
        $errors = [];

        if (! self::isReachable($validated)) {
            $errors['phone'] = (string) __('admin_data.directory.errors.unreachable');
        }

        if (($validated['is_national_focal'] ?? false) && ($validated['role'] ?? null) !== DirectoryRole::PointFocal->value) {
            $errors['is_national_focal'] = (string) __('admin_data.directory.errors.national_focal');
        }

        return $errors;
    }

    /**
     * Au moins un moyen de joindre la personne.
     *
     * @param  array<string, mixed>  $validated
     */
    public static function isReachable(array $validated): bool
    {
        foreach (['phone', 'whatsapp', 'email', 'user_id'] as $key) {
            $value = $validated[$key] ?? null;

            if ($value !== null && $value !== '') {
                return true;
            }
        }

        return false;
    }
}
