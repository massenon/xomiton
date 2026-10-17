<?php

declare(strict_types=1);

namespace App\Models;

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
    public const CODE_DIGITS = 4;

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

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function isAnonymized(): bool
    {
        return $this->anonymized_at !== null;
    }

    /**
     * Prochain code de registre pour la nature donnée (RG-06) : préfixe `VBG-` / `VDH-`, compteur sur
     * 4 chiffres, un compteur par nature, jamais réutilisé (les dossiers ne sont pas supprimés).
     * Transaction + verrou d'écriture sur la dernière ligne de la nature pour sérialiser deux saisies
     * simultanées ; en SQLite (tests) le verrou est ignoré, la base étant mono-écrivain.
     */
    public static function nextCode(CaseKind $kind): string
    {
        return DB::transaction(static function () use ($kind): string {
            $last = static::query()
                ->ofKind($kind)
                ->where('code', 'like', $kind->codePrefix().'%')
                ->lockForUpdate()
                ->orderByDesc('code')
                ->value('code');

            $next = $last === null ? 1 : ((int) substr((string) $last, strlen($kind->codePrefix()))) + 1;

            return $kind->codePrefix().str_pad((string) $next, self::CODE_DIGITS, '0', STR_PAD_LEFT);
        });
    }
}
