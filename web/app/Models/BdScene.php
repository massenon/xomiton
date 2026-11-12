<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\MediaUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Scène de la bibliothèque des conversations en BD (P20-03, P20-05 ; `bd_scenes`) : illustration validée SANS texte
 * (`assets/images/conversations-bd/<slug>/case-N.webp` pour les modèles), description (texte alternatif), personnages
 * présents, zone laissée calme pour les bulles, active ou non (une scène inactive n'est plus proposée à l'éditeur des
 * membres, lot B ; les cases qui l'utilisent restent lisibles).
 */
class BdScene extends Model
{
    protected $table = 'bd_scenes';

    /** @var list<string> */
    protected $fillable = ['key', 'title', 'image_path', 'description', 'characters', 'bubble_zone', 'origin', 'is_active', 'position'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'characters' => 'array',
            'is_active' => 'boolean',
            'position' => 'integer',
            'content_edited_at' => 'datetime',
        ];
    }

    /** @param  Builder<BdScene>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @param  Builder<BdScene>  $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('key');
    }

    public function hasImage(): bool
    {
        return MediaUrl::exists($this->image_path);
    }
}
