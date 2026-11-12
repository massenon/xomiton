<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Case d'une conversation en BD (P20-03 ; `bd_panels`) : rang de lecture, scène de la bibliothèque (image sans texte) et
 * description de la scène propre à la case (texte alternatif ; repli affiché tant que l'image manque).
 */
class BdPanel extends Model
{
    protected $table = 'bd_panels';

    /** @var list<string> */
    protected $fillable = ['conversation_id', 'scene_id', 'position', 'scene_description'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['conversation_id' => 'integer', 'scene_id' => 'integer', 'position' => 'integer'];
    }

    /** @return BelongsTo<BdConversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(BdConversation::class, 'conversation_id');
    }

    /** @return BelongsTo<BdScene, $this> */
    public function scene(): BelongsTo
    {
        return $this->belongsTo(BdScene::class, 'scene_id');
    }

    /** @return HasMany<BdBubble, $this> */
    public function bubbles(): HasMany
    {
        return $this->hasMany(BdBubble::class, 'panel_id')->orderBy('position')->orderBy('id');
    }
}
