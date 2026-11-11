<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catégorie typée par `kind` (thematique, resource, faq, event) ; (kind, slug) unique.
 */
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    public const KIND_THEMATIQUE = 'thematique';

    public const KIND_RESOURCE = 'resource';

    public const KIND_FAQ = 'faq';

    public const KIND_EVENT = 'event';

    /** @var list<string> */
    public const KINDS = [
        self::KIND_THEMATIQUE,
        self::KIND_RESOURCE,
        self::KIND_FAQ,
        self::KIND_EVENT,
    ];

    /** @var list<string> */
    protected $fillable = [
        'kind',
        'slug',
        'label',
        'icon',
        'color',
        'position',
        'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<Thematique, $this> */
    public function thematiques(): HasMany
    {
        return $this->hasMany(Thematique::class);
    }

    /** @return HasMany<\App\Models\Resource, $this> */
    public function resources(): HasMany
    {
        return $this->hasMany(Resource::class);
    }

    /** @return HasMany<Faq, $this> */
    public function faqs(): HasMany
    {
        return $this->hasMany(Faq::class);
    }

    /** @return HasMany<Event, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /** @param  Builder<Category>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @param  Builder<Category>  $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('label');
    }

    /** @param  Builder<Category>  $query */
    public function scopeOfKind(Builder $query, string $kind): void
    {
        $query->where('kind', $kind);
    }
}
