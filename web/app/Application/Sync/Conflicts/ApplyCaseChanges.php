<?php

declare(strict_types=1);

namespace App\Application\Sync\Conflicts;

use App\Application\Cases\CaseQuality;
use App\Application\Cases\CaseValidator;
use App\Application\Cases\Data\CaseChanges as ValidatedCaseChanges;
use App\Application\Cases\PeerSpace;
use App\Application\Sync\Push\CaseChanges;
use App\Application\Sync\Push\ItemRejected;
use App\Domain\Cases\Exceptions\CaseLocked;
use App\Domain\Sync\Events\SyncConflictDetected;
use App\Domain\Sync\FieldMerge;
use App\Domain\Sync\FieldOwner;
use App\Enums\AgeRange;
use App\Enums\CaseStatus;
use App\Enums\ConflictStatus;
use App\Enums\Sex;
use App\Models\CaseRecord;
use App\Models\SyncConflict;
use App\Models\SyncDevice;
use App\Models\User;
use App\Support\Audit;
use BackedEnum;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Modification hors connexion d'un dossier (`collecte`, `op: update`, P9-06, SOFTWARE_ARCHITECTURE.md §5) : fusion par
 * champ (FieldMerge) — statut et attribution : le serveur gagne ; notes : l'appareil gagne si son compte a collecté le
 * dossier ; autre champ modifié des deux côtés : conflit 409 enregistré dans `sync_conflicts` (valeurs chiffrées), la
 * valeur du serveur reste en place jusqu'à la décision de l'administration (*Hors connexion & synchro*), compteur
 * `sync_devices.conflicts_count`, puis SyncConflictDetected après validation (cloche admin et journal sans les valeurs).
 *
 * Mêmes gardes qu'UpdateCase (`PATCH pair/cases/{code}`) : espace pair (PeerSpace, RG-03 : compte actif), dossier hors
 * de la portée du compte (CaseRecordPolicy::update) ou introuvable : 404 neutre ; archivé ou anonymisé : 422
 * `case_locked` ; valeurs validées par CaseValidator (CaseRules::forUpdate : catalogue activable, commune ↔ département
 * du dossier selon `commune_in_zone`) ; dossier relu sous verrou (`lockForUpdate`) avant la fusion, qui compare donc à
 * la valeur courante même si un `PATCH` arrive en même temps ; journal « cases.updated » au format d'UpdateCase avec
 * `source=sync` ; avertissements CaseQuality. Statut : transitions de CaseStatus, jamais l'archivage depuis l'appareil.
 */
final class ApplyCaseChanges
{
    /** Champs modifiables depuis l'appareil (`collected_by`, l'attribution, n'en fait pas partie : il reste au serveur). */
    public const FIELDS = ['occurred_on', 'commune_id', 'population_id', 'sex', 'age_range', 'violence_type_id', 'referral', 'notes', 'status'];

    /** @var array<string, FieldOwner> */
    private const OWNERS = [
        'status' => FieldOwner::Server,
        'notes' => FieldOwner::Author,
    ];

    public function __construct(
        private readonly CaseValidator $validator,
        private readonly CaseQuality $quality,
    ) {}

    public function __invoke(User $user, SyncDevice $device, CaseChanges $changes): CaseChangeResult
    {
        if (! PeerSpace::allows($user)) {
            throw ItemRejected::forbidden();
        }

        $case = $this->find($changes);

        if ($case === null || Gate::forUser($user)->denies('update', $case)) {
            throw ItemRejected::notFound();
        }

        self::ensureWritable($case);

        $values = $this->deviceValues($case, $user, $changes->changes);
        $base = array_map(self::normalize(...), array_intersect_key($changes->base, $values));

        /** @var array{0: CaseRecord, 1: array<string, string|null>, 2: list<string>, 3: list<SyncConflict>, 4: list<string>, 5: CaseStatus} $outcome */
        $outcome = DB::transaction(function () use ($case, $device, $user, $values, $base): array {
            /** @var CaseRecord $fresh */
            $fresh = CaseRecord::query()->whereKey($case->getKey())->lockForUpdate()->firstOrFail();
            self::ensureWritable($fresh);

            $merge = FieldMerge::resolve(
                server: $this->serverValues($fresh, array_keys($values)),
                base: $base,
                device: $values,
                owners: self::OWNERS,
                deviceIsAuthor: $fresh->collected_by !== null && (int) $fresh->collected_by === (int) $user->getKey(),
            );

            $applied = $merge->applied;
            $kept = $merge->kept;

            if (array_key_exists('status', $applied) && ! $this->statusAllowed($fresh, $applied['status'])) {
                unset($applied['status']);
                $kept[] = 'status';
            }

            $from = $fresh->status;
            $dirty = [];

            if ($applied !== []) {
                $fresh->forceFill($this->attributes($applied));
                $dirty = array_keys($fresh->getDirty());
                $fresh->save();
            }

            $rows = [];

            foreach ($merge->conflicts as $field => $pair) {
                $rows[] = SyncConflict::query()->create([
                    'device_id' => $device->getKey(),
                    'subject_type' => $fresh->getMorphClass(),
                    'subject_id' => $fresh->getKey(),
                    'field' => $field,
                    'server_value' => $pair['server'],
                    'device_value' => $pair['device'],
                    'status' => ConflictStatus::Open,
                ]);
            }

            if ($rows !== []) {
                $device->increment('conflicts_count', count($rows));
            }

            return [$fresh, $applied, array_values(array_unique($kept)), $rows, $dirty, $from];
        });

        [$fresh, $applied, $kept, $conflicts, $dirty, $from] = $outcome;
        $this->audit($user, $fresh, $dirty, $from);

        // Après la transaction : cloche admin et journal (NotifyAdminsOfSyncConflict), jamais les valeurs.
        foreach ($conflicts as $conflict) {
            event(new SyncConflictDetected(
                conflictId: (int) $conflict->getKey(),
                deviceId: (int) $device->getKey(),
                subjectType: $fresh->getMorphClass(),
                subjectId: (int) $fresh->getKey(),
                field: (string) $conflict->field,
                occurredAt: new DateTimeImmutable,
            ));
        }

        $fresh->refresh();

        return new CaseChangeResult(
            $fresh,
            array_keys($applied),
            $kept,
            array_map(static fn (SyncConflict $conflict): array => ['field' => (string) $conflict->field, 'conflict_id' => (int) $conflict->getKey()], $conflicts),
            $this->quality->warnings($fresh, $user),
        );
    }

    /** Valeur comparable d'un champ : chaîne, ou null pour une valeur vide (dates `AAAA-MM-JJ`, énumérations par valeur). */
    public static function normalize(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            $value instanceof BackedEnum => (string) $value->value,
            $value instanceof DateTimeInterface => $value->format('Y-m-d'),
            is_bool($value) => $value ? '1' : '0',
            is_int($value), is_float($value) => (string) $value,
            is_string($value) => trim($value) === '' ? null : trim($value),
            default => null,
        };
    }

    /** Archivé ou anonymisé : lecture seule, comme `PATCH pair/cases/{code}` (UpdateCase, 422 `case_locked`). */
    private static function ensureWritable(CaseRecord $case): void
    {
        if ($case->isAnonymized() || $case->status === CaseStatus::Archived) {
            throw new CaseLocked;
        }
    }

    private function find(CaseChanges $changes): ?CaseRecord
    {
        $query = CaseRecord::query();

        return match (true) {
            $changes->caseId !== null => $query->whereKey($changes->caseId)->first(),
            $changes->caseCode !== null => $query->where('code', strtoupper($changes->caseCode))->first(),
            default => null,
        };
    }

    /**
     * Valeurs de l'appareil validées par les règles de `PATCH pair/cases/{code}` (CaseValidator::changes : catalogue
     * activable, statuts permis au compte, commune ↔ département du dossier), puis normalisées pour la comparaison.
     *
     * @param  array<string, mixed>  $changes
     * @return array<string, string|null>
     */
    private function deviceValues(CaseRecord $case, User $user, array $changes): array
    {
        $payload = array_intersect_key($changes, array_flip(self::FIELDS));

        try {
            $validated = $this->validator->changes($case, $user, $payload);
        } catch (ValidationException $exception) {
            $errors = [];

            foreach ($exception->errors() as $field => $messages) {
                $errors['changes.'.$field] = array_values($messages);
            }

            throw ItemRejected::invalid($errors);
        }

        return $this->comparable($validated, array_keys($payload));
    }

    /**
     * @param  list<string>  $fields
     * @return array<string, string|null>
     */
    private function comparable(ValidatedCaseChanges $validated, array $fields): array
    {
        $values = [];

        foreach ($fields as $field) {
            $values[$field] = $field === 'status'
                ? self::normalize($validated->status)
                : self::normalize($validated->attributes[$field] ?? null);
        }

        return $values;
    }

    /**
     * @param  list<string>  $fields
     * @return array<string, string|null>
     */
    private function serverValues(CaseRecord $case, array $fields): array
    {
        $values = [];

        foreach ($fields as $field) {
            $values[$field] = self::normalize($case->getAttribute($field));
        }

        return $values;
    }

    private function statusAllowed(CaseRecord $case, ?string $target): bool
    {
        $next = $target === null ? null : CaseStatus::tryFrom($target);

        return $next !== null && $next !== CaseStatus::Archived && $case->status->canTransitionTo($next);
    }

    /**
     * @param  array<string, string|null>  $applied
     * @return array<string, mixed>
     */
    private function attributes(array $applied): array
    {
        $attributes = [];

        foreach ($applied as $field => $value) {
            $attributes[$field] = match ($field) {
                'status' => CaseStatus::from((string) $value),
                'sex' => $value === null ? null : Sex::from($value),
                'age_range' => $value === null ? null : AgeRange::from($value),
                'commune_id', 'population_id', 'violence_type_id' => $value === null ? null : (int) $value,
                default => $value,
            };
        }

        if (isset($attributes['status'])) {
            $attributes['closed_at'] = $attributes['status'] === CaseStatus::Closed ? now() : null;
        }

        return $attributes;
    }

    /**
     * Journal « cases.updated » au format d'UpdateCase (champs et statut, jamais leur contenu), avec `source=sync`.
     *
     * @param  list<string>  $dirty
     */
    private function audit(User $user, CaseRecord $case, array $dirty, CaseStatus $from): void
    {
        $fields = array_values(array_intersect(ValidatedCaseChanges::FIELDS, $dirty));
        $to = $case->status;

        if ($fields === [] && $from === $to) {
            return;
        }

        Audit::log('cases.updated', $case, sprintf(
            'code=%s ; champs=%s%s ; source=sync',
            $case->code,
            $fields === [] ? '-' : implode(',', $fields),
            $from !== $to ? sprintf(' ; statut=%s→%s', $from->value, $to->value) : '',
        ), $user);
    }
}
