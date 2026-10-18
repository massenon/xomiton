<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Feed\ContentFilter;
use App\Enums\BadWordSeverity;
use Database\Factories\BadWordFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Terme filtré par la modération automatique (DATABASE_DICTIONARY.md §3 `bad_words`).
 */
class BadWord extends Model
{
    /** @use HasFactory<BadWordFactory> */
    use HasFactory;

    /** Liste des termes actifs mise en cache par App\Application\Feed\BadWordFilter, oubliée à chaque écriture. */
    public const CACHE_KEY = 'feed:bad_words';

    /** @var list<string> */
    protected $fillable = [
        'word',
        'severity',
        'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'severity' => BadWordSeverity::class,
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        $forget = static fn (): bool => Cache::forget(self::CACHE_KEY);

        static::saved($forget);
        static::deleted($forget);
    }

    /** @param  Builder<BadWord>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @param  Builder<BadWord>  $query */
    public function scopeBlocking(Builder $query): void
    {
        $query->where('severity', BadWordSeverity::Block);
    }

    /** @param  Builder<BadWord>  $query */
    public function scopeFlagging(Builder $query): void
    {
        $query->where('severity', BadWordSeverity::Flag);
    }

    /**
     * Le terme est-il présent dans le texte ? Comparaison insensible à la casse et aux accents,
     * sur mots entiers (« tarée » ne déclenche pas sur « notarée »).
     */
    public function appearsIn(string $text): bool
    {
        return ContentFilter::contains($text, (string) $this->word);
    }

    /**
     * Normalise le terme avant enregistrement pour garantir l'unicité fonctionnelle.
     */
    public function setWordAttribute(string $value): void
    {
        $this->attributes['word'] = Str::lower(trim(preg_replace('/\s+/u', ' ', $value) ?? $value));
    }
}
