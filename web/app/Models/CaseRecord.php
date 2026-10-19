<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Cases\CaseCode;
use App\Enums\AgeRange;
use App\Enums\AlertSource;
use App\Enums\CaseKind;
use App\Enums\CaseStatus;
use App\Enums\Sex;
use Database\Factories\CaseRecordFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Dossier VBG / VDH collecté par une pair·e (table `cases` ; `Case` est un mot réservé PHP).
 * Jamais supprimé : clos, puis archivé, puis anonymisé. Les notes sont chiffrées et jamais sérialisées.
 */
class CaseRecord extends Model
{
    /** @use HasFactory<CaseRecordFactory> */
    use HasFactory;

    /** Largeur du compteur du code de registre (RG-06 : `VBG-0001`). */
    public const CODE_DIGITS = CaseCode::DIGITS;

    protected $table = 'cases';

    /** @var list<string> */
    protected $fillable = [
        'code',
        'kind',
        'occurred_on',
        'zone_id',
        'commune_id',
        'population_id',
        'sex',
        'age_range',
        'violence_type_id',
        'status',
        'collected_by',
        'referral',
        'notes',
        'source',
        'client_uuid',
        'device_id',
        'closed_at',
        'archived_at',
        'anonymized_at',
    ];

    /** @var list<string> */
    protected $hidden = [
        'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'kind' => CaseKind::class,
            'occurred_on' => 'date',
            'sex' => Sex::class,
            'age_range' => AgeRange::class,
            'status' => CaseStatus::class,
            'notes' => 'encrypted',
            'source' => AlertSource::class,
            'closed_at' => 'datetime',
            'archived_at' => 'datetime',
            'anonymized_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Zone, $this> */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /** @return BelongsTo<Commune, $this> */
    public function commune(): BelongsTo
    {
        return $this->belongsTo(Commune::class);
    }

    /** @return BelongsTo<Population, $this> */
    public function population(): BelongsTo
    {
        return $this->belongsTo(Population::class);
    }

    /** @return BelongsTo<ViolenceType, $this> */
    public function violenceType(): BelongsTo
    {
        return $this->belongsTo(ViolenceType::class);
    }

    /**
     * Pair·e qui a collecté le dossier.
     *
     * @return BelongsTo<User, $this>
     */
    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }

    /**
     * Appareil d'origine (synchronisation hors connexion).
     *
     * @return BelongsTo<SyncDevice, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(SyncDevice::class, 'device_id');
    }

    /** @return HasMany<CaseForm, $this> */
    public function forms(): HasMany
    {
        return $this->hasMany(CaseForm::class, 'case_id');
    }

    /** @return HasMany<CaseFollowup, $this> */
    public function followups(): HasMany
    {
        return $this->hasMany(CaseFollowup::class, 'case_id');
    }

    /**
     * Dossiers encore suivis (ouverts ou en cours).
     *
     * @param  Builder<CaseRecord>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', array_map(
            static fn (CaseStatus $status): string => $status->value,
            CaseStatus::activeStatuses(),
        ));
    }

    /** @param  Builder<CaseRecord>  $query */
    public function scopeOfKind(Builder $query, CaseKind $kind): void
    {
        $query->where('kind', $kind->value);
    }

    /** @param  Builder<CaseRecord>  $query */
    public function scopeWithStatus(Builder $query, CaseStatus $status): void
    {
        $query->where('status', $status->value);
    }

    /** @param  Builder<CaseRecord>  $query */
    public function scopeCollectedBy(Builder $query, User|int $user): void
    {
        $query->where('collected_by', $user instanceof User ? $user->getKey() : $user);
    }

    /** @param  Builder<CaseRecord>  $query */
    public function scopeInZone(Builder $query, Zone|int $zone): void
    {
        $query->where('zone_id', $zone instanceof Zone ? $zone->getKey() : $zone);
    }

    /**
     * Dossiers survenus dans la période (bornes incluses) : registres et indicateurs.
     *
     * @param  Builder<CaseRecord>  $query
     */
    public function scopeOccurredBetween(Builder $query, string $from, string $to): void
    {
        $query->whereBetween('occurred_on', [$from, $to]);
    }

    /**
     * Dossiers à anonymiser par `cases:anonymize` : clos ou archivés, pas encore anonymisés, dont le dernier
     * événement de cycle de vie (archivage, sinon clôture) remonte à plus de `$years` ans
     * (`xomiton.retention.sensitive_years_after_case` par défaut ; DATABASE_ENGINEERING.md §6).
     *
     * @param  Builder<CaseRecord>  $query
     */
    public function scopeDueForAnonymization(Builder $query, ?int $years = null): void
    {
        $cutoff = now()->subYears($years ?? (int) config('xomiton.retention.sensitive_years_after_case', 5));

        $query->whereNull('anonymized_at')
            ->whereIn('status', [CaseStatus::Closed->value, CaseStatus::Archived->value])
            ->whereRaw('COALESCE(archived_at, closed_at) < ?', [$cutoff->format('Y-m-d H:i:s')]);
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function isAnonymized(): bool
    {
        return $this->anonymized_at !== null;
    }

    /** Compteurs des codes de registre, une ligne par nature (migration `case_code_counters`). */
    public const CODE_COUNTERS_TABLE = 'case_code_counters';

    /**
     * Prochain code de registre pour la nature donnée (RG-06, App\Domain\Cases\CaseCode) : préfixe `VBG-` / `VDH-`,
     * compteur sur 4 chiffres au moins (`VBG-10000` suit `VBG-9999`), un compteur par nature, jamais réutilisé (les
     * dossiers ne sont pas supprimés). Le rang vient de la ligne de la nature dans `case_code_counters`, verrouillée
     * seule (`lockForUpdate` par clé primaire) : appelée dans la transaction de l'insertion (CreateCase), elle sérialise
     * deux collectes simultanées de la même nature sans verrouiller les dossiers (UpdateCase, `sync/push` passent).
     * Première attribution d'une nature, ou code déjà posé hors du compteur (import gardant les codes du prototype,
     * jeu d'essai) : reprise d'après le plus haut code en base (lecture complète, rare). En SQLite (tests) les verrous
     * sont ignorés, la base étant mono-écrivain.
     */
    public static function nextCode(CaseKind $kind): string
    {
        return DB::transaction(static function () use ($kind): string {
            $last = self::lockedSequence($kind);

            if ($last === null) {
                DB::table(self::CODE_COUNTERS_TABLE)->insertOrIgnore([
                    'kind' => $kind->value,
                    'last_sequence' => self::scannedNext($kind)->sequence - 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $last = self::lockedSequence($kind) ?? 0;
            }

            $code = CaseCode::of($kind, $last + 1);

            // Code déjà pris hors du compteur : le plus haut code en base le dépasse forcément.
            if (static::query()->where('code', $code->value())->exists()) {
                $code = self::scannedNext($kind);
            }

            DB::table(self::CODE_COUNTERS_TABLE)
                ->where('kind', $kind->value)
                ->update(['last_sequence' => $code->sequence, 'updated_at' => now()]);

            return $code->value();
        });
    }

    /** Dernier rang attribué pour la nature, ligne du compteur verrouillée ; null si la nature n'a pas encore de compteur. */
    private static function lockedSequence(CaseKind $kind): ?int
    {
        $sequence = DB::table(self::CODE_COUNTERS_TABLE)->where('kind', $kind->value)->lockForUpdate()->value('last_sequence');

        return $sequence === null ? null : (int) $sequence;
    }

    /**
     * Code qui suit le plus haut code en base pour la nature (tri par longueur puis par code : `VBG-10000` après
     * `VBG-9999`). Lecture partagée de toutes les lignes de la nature : réservée à la reprise du compteur.
     */
    private static function scannedNext(CaseKind $kind): CaseCode
    {
        $last = static::query()
            ->ofKind($kind)
            ->where('code', 'like', $kind->codePrefix().'%')
            ->sharedLock()
            ->orderByRaw('LENGTH(code) DESC')
            ->orderByDesc('code')
            ->value('code');

        return CaseCode::after($kind, $last === null ? null : (string) $last);
    }
}
