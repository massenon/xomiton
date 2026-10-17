<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CaseFormFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * Fiche de référence VBG / VDH d'un dossier (Module 4 du manuel : « Remplir une fiche de référence »).
 * L'identification est pseudonymisée ; auteur·e des faits, récit, note de services et signataire sont chiffrés.
 */
class CaseForm extends Model
{
    /** @use HasFactory<CaseFormFactory> */
    use HasFactory;

    public const REFERENCE_PREFIX = 'FR-';

    public const REFERENCE_DIGITS = 4;

    /** @var list<string> clés attendues dans `services` (DATABASE_DICTIONARY.md §6 ; `dignite` = kit de dignité, admin/fiches.php) */
    public const SERVICE_KEYS = ['ecoute', 'medical', 'psy', 'juridique', 'hebergement', 'dignite', 'securite', 'autre'];

    /** @var list<string> */
    protected $fillable = [
        'case_id',
        'reference',
        'created_by',
        'identification',
        'facts_date',
        'facts_place',
        'facts_type',
        'facts_author',
        'facts_description',
        'services',
        'services_note',
        'referral_contact_id',
        'referral_label',
        'referral_date',
        'referral_reason',
        'referral_urgency',
        'consent_given',
        'consent_mode',
        'consent_sharing',
        'consent_signatory',
    ];

    /** @var list<string> */
    protected $hidden = [
        'facts_author',
        'facts_description',
        'services_note',
        'consent_signatory',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'identification' => 'array',
            'facts_date' => 'date',
            'facts_author' => 'encrypted',
            'facts_description' => 'encrypted',
            'services' => 'array',
            'services_note' => 'encrypted',
            'referral_date' => 'date',
            'consent_given' => 'boolean',
            'consent_sharing' => 'boolean',
            'consent_signatory' => 'encrypted',
        ];
    }

    /** @return BelongsTo<CaseRecord, $this> */
    public function case(): BelongsTo
    {
        return $this->belongsTo(CaseRecord::class, 'case_id');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Contact d'annuaire vers lequel la personne est orientée.
     *
     * @return BelongsTo<DirectoryContact, $this>
     */
    public function referralContact(): BelongsTo
    {
        return $this->belongsTo(DirectoryContact::class, 'referral_contact_id');
    }

    /** @param  Builder<CaseForm>  $query */
    public function scopeForCase(Builder $query, CaseRecord|int $case): void
    {
        $query->where('case_id', $case instanceof CaseRecord ? $case->getKey() : $case);
    }

    /**
     * Fiches dont le consentement au partage a été donné (seules transmissibles à un partenaire).
     *
     * @param  Builder<CaseForm>  $query
     */
    public function scopeShareable(Builder $query): void
    {
        $query->where('consent_given', true)->where('consent_sharing', true);
    }

    /**
     * Services demandés (clés de `services` à vrai).
     *
     * @return list<string>
     */
    public function requestedServices(): array
    {
        return array_values(array_keys(array_filter($this->services ?? [])));
    }

    /**
     * Prochaine référence de fiche `FR-YYYY-0001` : compteur annuel, sérialisé par transaction + verrou.
     */
    public static function nextReference(?int $year = null): string
    {
        $prefix = self::REFERENCE_PREFIX.($year ?? (int) now()->format('Y')).'-';

        return DB::transaction(static function () use ($prefix): string {
            $last = static::query()
                ->where('reference', 'like', $prefix.'%')
                ->lockForUpdate()
                ->orderByDesc('reference')
                ->value('reference');

            $next = $last === null ? 1 : ((int) substr((string) $last, strlen($prefix))) + 1;

            return $prefix.str_pad((string) $next, self::REFERENCE_DIGITS, '0', STR_PAD_LEFT);
        });
    }
}
