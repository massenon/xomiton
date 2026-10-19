<?php

declare(strict_types=1);

namespace App\Application\Sync\Pull;

use App\Application\Sync\Pull\Collections\CaseFollowupsCollection;
use App\Application\Sync\Pull\Collections\CasesCollection;
use App\Application\Sync\Pull\Collections\DirectoryContactsCollection;
use App\Application\Sync\Pull\Collections\EmergencyContactsCollection;
use App\Application\Sync\Pull\Collections\RoutedAlertsCollection;
use App\Application\Sync\Pull\Collections\ThematiquesCollection;
use App\Application\Sync\Pull\Collections\ValidationRulesCollection;
use App\Models\Alert;
use App\Models\CaseFollowup;
use App\Models\CaseRecord;
use App\Models\DirectoryContact;
use App\Models\SyncTombstone;
use App\Models\Thematique;
use App\Models\ValidationRule;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pierres tombales de `sync/pull` (P9-06) pour ce que la ligne ne dit plus : suppression définitive (fiche, thématique,
 * règle, suivi, dossier, alerte), fiche d'annuaire qui quitte l'annuaire public (`is_public` retiré : les fiches non
 * publiques ne sont jamais candidates, pour ne pas révéler l'identifiant d'une fiche réservée au réseau) et sortie de
 * portée d'un compte précis (dossier réattribué : ancien·ne collecteur·rice ; alerte routée ailleurs : compte lié à
 * l'ancienne fiche). Collections limitées au compte (dossiers, suivis, alertes routées) : jamais de pierre tombale pour
 * tout le monde (dossier sans collecteur·rice, alerte sans compte lié : aucun appareil ne l'a reçu). Une suppression
 * logique, une dépublication de thématique, un archivage ou une clôture se lisent sur la ligne (`updated_at`) et n'en
 * écrivent pas. Écouteurs Eloquent posés par AppServiceProvider::boot ; une mise à jour de masse
 * (`Query\Builder::update`) n'en déclenche pas : les écrans qui réattribuent ou dépublient passent par le modèle. Une
 * écriture ratée est journalisée, jamais bloquante.
 */
final class TombstoneRecorder
{
    /** Collections servies depuis `directory_contacts`. */
    private const DIRECTORY = [EmergencyContactsCollection::NAME, DirectoryContactsCollection::NAME];

    public static function register(): void
    {
        // Annuaire : seules les fiches publiques ont pu atteindre un appareil.
        DirectoryContact::forceDeleted(static function (DirectoryContact $contact): void {
            if ((bool) $contact->is_public) {
                self::record(self::DIRECTORY, (string) $contact->getKey(), null);
            }
        });
        DirectoryContact::updated(static function (DirectoryContact $contact): void {
            if ($contact->wasChanged('is_public') && (bool) $contact->getOriginal('is_public') && ! (bool) $contact->is_public) {
                self::record(self::DIRECTORY, (string) $contact->getKey(), null);
            }
        });
        Thematique::forceDeleted(static fn (Thematique $thematique) => self::record([ThematiquesCollection::NAME], (string) $thematique->getKey(), null));
        ValidationRule::deleted(static fn (ValidationRule $rule) => self::record([ValidationRulesCollection::NAME], (string) $rule->getKey(), null));

        CaseRecord::deleted(static fn (CaseRecord $case) => self::recordFor([CasesCollection::NAME], (string) $case->getKey(), self::int($case->collected_by)));
        CaseRecord::updated(static function (CaseRecord $case): void {
            $previous = self::int($case->getOriginal('collected_by'));

            if (! $case->wasChanged('collected_by') || $previous === null) {
                return;
            }

            self::recordFor([CasesCollection::NAME], (string) $case->getKey(), $previous);

            foreach (CaseFollowup::query()->where('case_id', $case->getKey())->pluck('id') as $followup) {
                self::recordFor([CaseFollowupsCollection::NAME], (string) $followup, $previous);
            }
        });
        CaseFollowup::deleted(static fn (CaseFollowup $followup) => self::recordFor(
            [CaseFollowupsCollection::NAME], (string) $followup->getKey(), self::int(CaseRecord::query()->whereKey($followup->case_id)->value('collected_by')),
        ));

        Alert::deleted(static fn (Alert $alert) => self::recordFor([RoutedAlertsCollection::NAME], (string) $alert->ulid, self::contactUser($alert->routed_to)));
        Alert::updated(static function (Alert $alert): void {
            if ($alert->wasChanged('routed_to')) {
                self::recordFor([RoutedAlertsCollection::NAME], (string) $alert->ulid, self::contactUser($alert->getOriginal('routed_to')));
            }
        });
    }

    /**
     * Pierre tombale d'une collection limitée au compte : rien sans compte (aucun appareil n'a reçu la ligne).
     *
     * @param  list<string>  $collections
     */
    private static function recordFor(array $collections, string $key, ?int $userId): void
    {
        if ($userId !== null) {
            self::record($collections, $key, $userId);
        }
    }

    /**
     * @param  list<string>  $collections
     */
    public static function record(array $collections, string $key, ?int $userId): void
    {
        try {
            foreach ($collections as $collection) {
                SyncTombstone::query()->create(['collection' => $collection, 'subject_key' => $key, 'user_id' => $userId]);
            }
        } catch (Throwable $exception) {
            Log::warning('sync.tombstone_not_recorded', ['collection' => implode(',', $collections), 'error' => $exception->getMessage()]);
        }
    }

    private static function contactUser(mixed $contactId): ?int
    {
        $id = self::int($contactId);

        return $id === null ? null : self::int(DirectoryContact::query()->withTrashed()->whereKey($id)->value('user_id'));
    }

    private static function int(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
