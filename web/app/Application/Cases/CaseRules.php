<?php

declare(strict_types=1);

namespace App\Application\Cases;

use App\Domain\Cases\CaseNotes;
use App\Domain\Cases\CaseReferral;
use App\Domain\Cases\FollowupStep;
use App\Domain\Cases\ValidationRuleKey;
use App\Enums\AgeRange;
use App\Enums\CaseKind;
use App\Enums\CaseStatus;
use App\Enums\FollowupOutcome;
use App\Enums\Sex;
use App\Models\CaseFollowup;
use App\Models\CaseRecord;
use App\Models\User;
use App\Rules\AgeRangeWithinBounds;
use App\Rules\CommuneInZone;
use App\Rules\MinimumLength;
use App\Rules\NotFutureDate;
use Illuminate\Validation\Rule;

/**
 * Règles Laravel d'un dossier et d'une étape de suivi, selon le catalogue activable (P9-01, ValidationCatalog) :
 * partagées par les Form Requests de `pair/` (P9-02) et la validation des éléments de `sync/push` (P9-06,
 * CaseValidator) — mêmes règles côté serveur que celles servies au client par `GET pair/validation-rules`.
 *
 *  - toujours (structure de la base, ou règle verrouillée `required`) : champs obligatoires de la collecte
 *    (prototype collecte.php l.21-31), valeurs des listes (nature, sexe, tranche d'âge, orientation, étape,
 *    résultat), référentiels actifs, format des dates, longueurs ;
 *  - selon le catalogue : `date_not_future` (RG-07), `age_range` (RG-08, Q25 a : tranche dans les bornes de
 *    plausibilité), `commune_in_zone` (RG-08 ; désactivée, la commune doit seulement exister et être active),
 *    `min_len_20` (notes vides ou ≥ 20 caractères).
 *
 * Une modification (`PATCH`) ne valide que les champs envoyés ; un champ envoyé obligatoire ne peut pas être vidé.
 * La nature (`kind`) ne change jamais : le code de registre en dépend (RG-06).
 */
final class CaseRules
{
    /** Département contre lequel la commune d'une modification est contrôlée (withCurrentLocation) ; jamais validé ni écrit. */
    public const LOCATION_ZONE = '_location_zone_id';

    public function __construct(private readonly ValidationCatalog $catalog) {}

    /**
     * @return array<string, list<mixed>>
     */
    public function forCreate(): array
    {
        return [
            'client_uuid' => ['nullable', 'uuid'],
            'kind' => ['required', Rule::enum(CaseKind::class)],
        ] + $this->caseFields(creating: true);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function forUpdate(User $by): array
    {
        return [
            'kind' => ['prohibited'],
            'client_uuid' => ['prohibited'],
            'status' => ['sometimes', 'required', Rule::in(array_map(
                static fn (CaseStatus $status): string => $status->value,
                self::statusesFor($by),
            ))],
        ] + $this->caseFields(creating: false);
    }

    /**
     * Données d'une modification complétées de la localisation actuelle du dossier, pour que la cohérence commune ↔
     * département (RG-08, règle `commune_in_zone` active) porte sur le couple final : commune seule → contrôlée contre
     * le département du dossier, posé sous la clé interne LOCATION_ZONE (jamais `zone_id` : un département désactivé
     * depuis ne lève pas d'erreur sur un champ non envoyé, et rien n'est modifié) ; département seul → commune du dossier
     * revalidée (à renvoyer, ou à vider par `commune_id: null`, si elle n'est plus dans le nouveau département). Règle
     * désactivée : rien n'est ajouté.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function withCurrentLocation(array $input, CaseRecord $case): array
    {
        unset($input[self::LOCATION_ZONE]);

        if (! $this->catalog->isActive(ValidationRuleKey::CommuneInZone)) {
            return $input;
        }

        $hasZone = array_key_exists('zone_id', $input);
        $input[self::LOCATION_ZONE] = $hasZone ? $input['zone_id'] : $case->zone_id;

        if ($hasZone && ! array_key_exists('commune_id', $input) && $case->commune_id !== null) {
            $input['commune_id'] = $case->commune_id;
        }

        return $input;
    }

    /**
     * Statuts qu'un compte peut viser : ouvert, en cours, clos ; l'archivage relève de l'administration
     * (`cases.view_all`, P11-01).
     *
     * @return list<CaseStatus>
     */
    public static function statusesFor(User $by): array
    {
        $statuses = [CaseStatus::Open, CaseStatus::InProgress, CaseStatus::Closed];

        if ($by->can('cases.view_all')) {
            $statuses[] = CaseStatus::Archived;
        }

        return $statuses;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function forFollowupCreate(): array
    {
        return ['client_uuid' => ['nullable', 'uuid']] + $this->followupFields(creating: true);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function forFollowupUpdate(): array
    {
        return ['client_uuid' => ['prohibited']] + $this->followupFields(creating: false);
    }

    /**
     * Données d'une modification d'étape complétées de sa date actuelle (contrôle « prochaine date ≥ date »).
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function withCurrentDate(array $input, CaseFollowup $followup): array
    {
        if (array_key_exists('next_on', $input) && ! array_key_exists('date', $input)) {
            $input['date'] = $followup->date?->format('Y-m-d');
        }

        return $input;
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function caseFields(bool $creating): array
    {
        $presence = $creating ? ['required'] : ['sometimes', 'required'];

        return [
            'occurred_on' => [...$presence, 'date_format:Y-m-d', ...$this->notFuture()],
            'zone_id' => [...$presence, 'integer', Rule::exists('zones', 'id')->where('is_active', true)],
            'commune_id' => ['nullable', 'integer', $this->catalog->isActive(ValidationRuleKey::CommuneInZone)
                ? new CommuneInZone($creating ? 'zone_id' : self::LOCATION_ZONE)
                : Rule::exists('communes', 'id')->where('is_active', true)],
            'population_id' => [...$presence, 'integer', Rule::exists('populations', 'id')->where('is_active', true)],
            'sex' => [...$presence, Rule::enum(Sex::class)],
            'age_range' => [...$presence, Rule::enum(AgeRange::class), ...($this->catalog->isActive(ValidationRuleKey::AgeRange)
                ? [new AgeRangeWithinBounds($this->catalog->plausibility())]
                : [])],
            'violence_type_id' => [...$presence, 'integer', Rule::exists('violence_types', 'id')->where('is_active', true)],
            'referral' => ['nullable', 'string', Rule::in(CaseReferral::OPTIONS)],
            'notes' => ['nullable', 'string', 'max:'.CaseNotes::MAX_LENGTH, ...($this->catalog->isActive(ValidationRuleKey::MinLen20)
                ? [new MinimumLength]
                : [])],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function followupFields(bool $creating): array
    {
        $presence = $creating ? ['required'] : ['sometimes', 'required'];

        return [
            'date' => [...$presence, 'date_format:Y-m-d', ...$this->notFuture()],
            'step' => [...$presence, 'string', Rule::in(FollowupStep::OPTIONS)],
            'outcome' => [...$presence, Rule::enum(FollowupOutcome::class)],
            'next_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date'],
            'comment' => ['nullable', 'string', 'max:'.FollowupStep::COMMENT_MAX_LENGTH],
        ];
    }

    /**
     * @return list<NotFutureDate>
     */
    private function notFuture(): array
    {
        return $this->catalog->isActive(ValidationRuleKey::DateNotFuture) ? [new NotFutureDate] : [];
    }
}
