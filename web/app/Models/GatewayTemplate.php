<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\GatewayTemplateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Message clé réutilisable pour une publication sortante (DATABASE_DICTIONARY.md §8 `gateway_templates`).
 */
class GatewayTemplate extends Model
{
    /** @use HasFactory<GatewayTemplateFactory> */
    use HasFactory;

    /** Bornes de validation du prototype (admin/passerelles.php `tpl_save`). */
    public const TITLE_MIN_LENGTH = 3;

    public const BODY_MIN_LENGTH = 20;

    public const BODY_MAX_LENGTH = 500;

    /** Catégories proposées par le dictionnaire (colonne libre, non exhaustive). */
    public const CATEGORY_PREVENTION = 'prevention';

    public const CATEGORY_DEPISTAGE = 'depistage';

    public const CATEGORY_SOS = 'sos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'body',
        'category',
        'is_active',
        'position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * @param  Builder<GatewayTemplate>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<GatewayTemplate>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('id');
    }

    /**
     * @param  Builder<GatewayTemplate>  $query
     */
    public function scopeInCategory(Builder $query, string $category): void
    {
        $query->where('category', $category);
    }

    /** Référence stockée dans `outbound_posts.reference` quand la publication reprend ce modèle. */
    public function reference(): string
    {
        return (string) $this->getKey();
    }
}
