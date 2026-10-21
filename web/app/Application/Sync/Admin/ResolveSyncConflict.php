<?php

declare(strict_types=1);

namespace App\Application\Sync\Admin;

use App\Application\Cases\CaseValidator;
use App\Application\Cases\UpdateCase;
use App\Application\Sync\Conflicts\ApplyCaseChanges;
use App\Domain\Sync\Exceptions\ConflictResolutionRefused;
use App\Models\CaseRecord;
use App\Models\SyncConflict;
use App\Models\SyncDevice;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Résolution d'un conflit de synchronisation par l'administration (P13-01, `sync.manage`) : valeur du **serveur** (déjà en
 * place, rien à écrire), de l'**appareil** ou **fusion** saisie à la main (champs texte seulement : notes, orientation).
 * La valeur retenue est appliquée au dossier par UpdateCase après les règles de la collecte (CaseValidator : catalogue,
 * commune ↔ département, transitions de statut, dossier verrouillé refusé) ; le conflit passe à « résolu » avec
 * `resolved_value` chiffrée, `resolved_by` et `resolved_at` ; le compteur de l'appareil baisse ; la décision est
 * journalisée (`sync.conflict_resolved` : choix, dossier, champ, appareil — **jamais les valeurs**, ADR-33).
 */
final class ResolveSyncConflict
{
    /** Champs dont une fusion à la main a un sens (texte libre). */
    public const MERGEABLE = ['notes', 'referral'];

    public function __construct(
        private readonly CaseValidator $validator,
        private readonly UpdateCase $update,
    ) {}

    /**
     * @throws AuthorizationException
     * @throws ConflictResolutionRefused
     */
    public function __invoke(User $by, SyncConflict $conflict, string $resolution, ?string $merged = null): SyncConflict
    {
        if (! $by->can('sync.manage')) {
            throw new AuthorizationException;
        }

        if (! in_array($resolution, SyncConflict::RESOLUTIONS, true)) {
            throw ConflictResolutionRefused::unknownResolution();
        }

        if ($resolution === SyncConflict::RESOLUTION_MERGE) {
            if (! in_array($conflict->field, self::MERGEABLE, true)) {
                throw ConflictResolutionRefused::notMergeable();
            }

            $merged = ApplyCaseChanges::normalize($merged) ?? '';
        }

        DB::transaction(function () use ($by, $conflict, $resolution, $merged): void {
            /** @var SyncConflict $locked */
            $locked = SyncConflict::query()->whereKey($conflict->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->isOpen()) {
                throw ConflictResolutionRefused::alreadyResolved();
            }

            if ($resolution !== SyncConflict::RESOLUTION_SERVER) {
                $value = $resolution === SyncConflict::RESOLUTION_DEVICE ? $locked->device_value : $merged;
                $this->apply($by, $locked, $value);
            }

            $locked->resolve($resolution, $by, $merged);

            if ($locked->device_id !== null) {
                SyncDevice::query()->whereKey($locked->device_id)->where('conflicts_count', '>', 0)->decrement('conflicts_count');
            }

            $conflict->setRawAttributes($locked->getAttributes(), true);
        });

        Audit::log('sync.conflict_resolved', $conflict, sprintf(
            'choix=%s ; sujet=%s #%d ; champ=%s ; appareil=%s',
            $resolution,
            class_basename((string) $conflict->subject_type),
            $conflict->subject_id,
            $conflict->field,
            $conflict->device_id ?? '-',
        ), $by);

        return $conflict;
    }

    /** Écrit la valeur retenue sur le dossier, avec les règles de la collecte. */
    private function apply(User $by, SyncConflict $conflict, ?string $value): void
    {
        $subject = $conflict->subject;

        if (! $subject instanceof CaseRecord || ! in_array($conflict->field, ApplyCaseChanges::FIELDS, true)) {
            throw ConflictResolutionRefused::subjectMissing();
        }

        try {
            $changes = $this->validator->changes($subject, $by, [$conflict->field => $value]);
        } catch (ValidationException $exception) {
            throw ConflictResolutionRefused::invalidValue(array_values(array_merge(...array_values($exception->errors()))));
        }

        ($this->update)($by, $subject, $changes);
    }
}
