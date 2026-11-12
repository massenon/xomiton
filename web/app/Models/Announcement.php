<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AnnouncementAudience;
use App\Enums\AnnouncementLevel;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Annonce globale (P18-13, Q97 ; DATABASE_DICTIONARY.md `announcements`) : bannière en haut de l'espace membre web et de
 * l'application, **jamais** sur le site public ni pour les visiteur·ses ; distincte du bandeau de maintenance. Période
 * `starts_at` → `ends_at` (fin facultative), public `membres` ou `pairs`, fermeture mémorisée par compte
 * (`announcement_dismissals`).
 */
class Announcement extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'title',
        'message',
        'link_path',
        'level',
        'audience',
        'starts_at',
        'ends_at',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /** @return HasMany<AnnouncementDismissal, $this> */
    public function dismissals(): HasMany
    {
        return $this->hasMany(AnnouncementDismissal::class);
    }

    public function level(): AnnouncementLevel
    {
        return AnnouncementLevel::tryFrom((string) $this->getAttributes()['level']) ?? AnnouncementLevel::Info;
    }

    public function audience(): AnnouncementAudience
    {
        return AnnouncementAudience::tryFrom((string) $this->getAttributes()['audience']) ?? AnnouncementAudience::Members;
    }

    /**
     * En cours à l'instant `$at` : commencée et pas encore finie.
     *
     * @param  Builder<Announcement>  $query
     */
    public function scopeCurrent(Builder $query, ?CarbonInterface $at = null): void
    {
        $at ??= now();
        $query->where('starts_at', '<=', $at)
            ->where(static fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $at));
    }

    /** Lecture d'une date brute (colonne `timestamp`) en Carbon, pour les services qui lisent les attributs bruts. */
    public function asDateTimePublic(mixed $value): CarbonInterface
    {
        return $this->asDateTime($value);
    }

    /** État affiché dans l'administration : `programmee`, `en_cours` ou `terminee`. */
    public function state(?CarbonInterface $at = null): string
    {
        $at ??= now();
        $starts = $this->asDateTime($this->getAttributes()['starts_at']);
        $ends = ($this->getAttributes()['ends_at'] ?? null) !== null ? $this->asDateTime($this->getAttributes()['ends_at']) : null;

        return match (true) {
            $starts->greaterThan($at) => 'programmee',
            $ends !== null && $ends->lessThanOrEqualTo($at) => 'terminee',
            default => 'en_cours',
        };
    }
}
