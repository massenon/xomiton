<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\FaqFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Question fréquente de la page d'aide (DATABASE_DICTIONARY.md §3 `faqs`).
 */
class Faq extends Model
{
    /** @use HasFactory<FaqFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'category_id',
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

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
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
