<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CccKind;
use Database\Factories\CccToolFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Outil du kit CCC numérisé (DATABASE_DICTIONARY.md §3 `ccc_tools`) : `chapters` pour un module du manuel,
 * `pages` (planches `{image, caption, alt}`) pour une boîte à images ou une bande dessinée.
 */
class CccTool extends Model
{
    /** @use HasFactory<CccToolFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'kind',
        'key',
        'title',
        'description',
        'image_path',
        'pages',
        'chapters',
        'duration_minutes',
        'position',
        'is_published',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'kind' => CccKind::class,
            'pages' => 'array',
            'chapters' => 'array',
            'duration_minutes' => 'integer',
            'position' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'key';
    }

    /** @param  Builder<CccTool>  $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    /** @param  Builder<CccTool>  $query */
    public function scopeOfKind(Builder $query, CccKind $kind): void
    {
        $query->where('kind', $kind);
    }

    /** @param  Builder<CccTool>  $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('key');
    }

    public function pagesCount(): int
    {
        return count($this->pages ?? []);
    }
}
