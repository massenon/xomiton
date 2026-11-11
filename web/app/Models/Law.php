<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\LawFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Texte de loi présenté sur la page « Lois » (DATABASE_DICTIONARY.md §3 `laws`) ; `simple_summary` : version simple
 * facultative (P4-04, interrupteur « Lire en version simple », repli sur `summary`).
 */
class Law extends Model
{
    /** @use HasFactory<LawFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'slug',
        'title',
        'subtitle',
        'summary',
        'simple_summary',
        'category',
        'official_url',
        'position',
        'is_published',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @param  Builder<Law>  $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    /** @param  Builder<Law>  $query */
    public function scopeInCategory(Builder $query, string $category): void
    {
        $query->where('category', $category);
    }

    /** @param  Builder<Law>  $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('title');
    }
}
