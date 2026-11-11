<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\FaqFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Question fréquente de la page d'aide (DATABASE_DICTIONARY.md §3 `faqs`). `slug` (adresse `/aide/{slug}`, P4-05) est
 * dérivé de la question à la création s'il est vide, puis stable : modifier la question ne change pas l'adresse.
 */
class Faq extends Model
{
    /** @use HasFactory<FaqFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'category_id',
        'slug',
        'question',
        'answer',
        'position',
        'is_published',
        'show_on_home',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_published' => 'boolean',
            'show_on_home' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(static function (Faq $faq): void {
            if (trim((string) $faq->slug) === '') {
                $faq->slug = static::uniqueSlug((string) $faq->question, $faq->getKey());
            }
        });
    }

    /**
     * Slug unique dérivé de la question (slugBase), suffixe numérique en cas de collision.
     */
    public static function uniqueSlug(string $question, mixed $ignoreId = null): string
    {
        $base = static::slugBase($question);
        $slug = $base;

        for ($i = 2; static::query()->where('slug', $slug)->when($ignoreId !== null, static fn (Builder $query) => $query->whereKeyNot($ignoreId))->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }

    /**
     * Base de slug d'une question : 5 mots au plus (SEO_CONTENT_GUIDE_INSTRUCTION.md §4), 70 caractères au plus sans
     * tiret final (la route n'accepte que `[a-z0-9]+(-[a-z0-9]+)*`), apostrophes comme séparateurs (« Qu’est-ce » →
     * `qu-est-ce`, Str::slug les supprimerait), « question » si la question ne donne aucun mot. Utilisée aussi par la
     * migration qui ajoute la colonne (2026_10_25_080000).
     */
    public static function slugBase(string $question): string
    {
        $words = array_slice(explode('-', Str::slug(str_replace(['’', "'", '‘', 'ʼ'], ' ', $question))), 0, 5);
        $base = rtrim(Str::limit(implode('-', $words), 70, ''), '-');

        return $base !== '' ? $base : 'question';
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Réponses à « Cet article vous a-t-il aidé·e ? » (P4-05) ; compteur « utile » de /admin/contenu (P12-03).
     *
     * @return HasMany<FaqFeedback, $this>
     */
    public function feedback(): HasMany
    {
        return $this->hasMany(FaqFeedback::class);
    }

    /** @param  Builder<Faq>  $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    /** @param  Builder<Faq>  $query */
    public function scopeOnHome(Builder $query): void
    {
        $query->where('is_published', true)->where('show_on_home', true);
    }

    /** @param  Builder<Faq>  $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('id');
    }
}
