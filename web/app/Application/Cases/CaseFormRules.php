<?php

declare(strict_types=1);

namespace App\Application\Cases;

use App\Domain\Cases\ValidationRuleKey;
use App\Models\CaseForm;
use App\Rules\MinimumLength;
use App\Rules\NotFutureDate;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;

/**
 * Règles d'une fiche de référence (P11-02, prototype admin/fiches.php l.19-34), selon le catalogue activable : date
 * des faits non future (`date_not_future`), description d'au moins 20 caractères (`min_len_20`). Le consentement de la
 * personne est obligatoire (la fiche ne s'enregistre pas sans) ; l'orientation ne vise qu'un contact actif de l'annuaire.
 */
final class CaseFormRules
{
    public const URGENCIES = ['normale', 'haute', 'critique'];

    public const CONSENT_MODES = ['oral', 'ecrit'];

    public function __construct(private readonly ValidationCatalog $catalog) {}

    /**
     * Règles de création, ou de modification quand `$existing` est donnée : les mêmes, avec deux nuances. La description
     * chiffrée n'est jamais pré-remplie, elle peut donc rester vide (= inchangée) ; l'orientation garde le contact déjà
     * choisi même s'il a été désactivé depuis.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(?CaseForm $existing = null): array
    {
        $currentContact = $existing?->referral_contact_id === null ? null : (int) $existing->referral_contact_id;

        return [
            'pseudo' => ['nullable', 'string', 'max:60'],
            'facts_date' => ['required', 'date_format:Y-m-d', ...($this->catalog->isActive(ValidationRuleKey::DateNotFuture) ? [new NotFutureDate] : [])],
            'facts_place' => ['nullable', 'string', 'max:120'],
            'facts_author' => ['nullable', 'string', 'max:120'],
            'facts_description' => [$existing === null ? 'required' : 'nullable', 'string', 'max:3000', ...($this->catalog->isActive(ValidationRuleKey::MinLen20) ? [new MinimumLength] : [])],
            'services' => ['array'],
            'services.*' => ['boolean'],
            'services_note' => ['nullable', 'string', 'max:1000'],
            'referral_contact_id' => ['nullable', 'integer', Rule::exists('directory_contacts', 'id')->where(static function (Builder $query) use ($currentContact): void {
                $query->where(static function (Builder $active) use ($currentContact): void {
                    $active->where('is_active', true);

                    if ($currentContact !== null) {
                        $active->orWhere('id', $currentContact);
                    }
                })->whereNull('deleted_at');
            })],
            'referral_date' => ['nullable', 'date_format:Y-m-d'],
            'referral_reason' => ['nullable', 'string', 'max:255'],
            'referral_urgency' => ['nullable', Rule::in(self::URGENCIES)],
            'consent_given' => ['accepted'],
            'consent_mode' => ['required', Rule::in(self::CONSENT_MODES)],
            'consent_sharing' => ['boolean'],
            'consent_signatory' => ['nullable', 'string', 'max:80'],
        ];
    }

    /**
     * Services reçus : seules les clés du catalogue sont gardées (une clé inconnue est ignorée, jamais enregistrée).
     *
     * @param  array<array-key, mixed>  $services
     * @return array<string, bool>
     */
    public static function services(array $services): array
    {
        return CaseForm::normalizeServices($services);
    }
}
