<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BubbleTailTarget;
use App\Enums\BubbleType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bulle d'une case (P20-03 ; `bd_bubbles`, format commun de GRAMMAIRE_DES_BULLES.md §6) : type, personnage, texte,
 * emphase, position x / y en % de la case (coin haut gauche de la bulle), queue {vers, x, y} en %, chaîne de bulles
 * associées (`linked_to` = rang de la bulle précédente dans la case), superposée, masquée, ordre de lecture (`position`).
 */
class BdBubble extends Model
{
    protected $table = 'bd_bubbles';

    /** @var list<string> */
    protected $fillable = [
        'panel_id', 'position', 'type', 'character_key', 'text', 'emphasis', 'x', 'y', 'tail_target', 'tail_x', 'tail_y',
        'linked_to', 'is_overlapping', 'is_masked',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'panel_id' => 'integer',
            'position' => 'integer',
            'emphasis' => 'array',
            'x' => 'float',
            'y' => 'float',
            'tail_x' => 'float',
            'tail_y' => 'float',
            'linked_to' => 'integer',
            'is_overlapping' => 'boolean',
            'is_masked' => 'boolean',
        ];
    }

    /** @return BelongsTo<BdPanel, $this> */
    public function panel(): BelongsTo
    {
        return $this->belongsTo(BdPanel::class, 'panel_id');
    }

    public function typeValue(): BubbleType
    {
        return BubbleType::tryFrom((string) $this->getAttribute('type')) ?? BubbleType::Speech;
    }

    public function tailTarget(): ?BubbleTailTarget
    {
        return BubbleTailTarget::tryFrom((string) $this->getAttribute('tail_target'));
    }

    /**
     * Bulle au format commun de la grammaire (§6), lu par le validateur et le rendu.
     *
     * @return array{type: string, personnage: string|null, texte: string, emphase: list<string>, position: array{x: float, y: float}, queue: array{vers: string, x: float|null, y: float|null}|null, associee_a: int|null, superpose: bool, masque: bool}
     */
    public function toGrammar(): array
    {
        $target = $this->tailTarget();

        return [
            'type' => $this->typeValue()->value,
            'personnage' => $this->character_key !== null && $this->character_key !== '' ? (string) $this->character_key : null,
            'texte' => (string) $this->text,
            'emphase' => array_values(array_filter(array_map(static fn (mixed $e): string => is_string($e) ? $e : '', (array) $this->emphasis))),
            'position' => ['x' => (float) $this->x, 'y' => (float) $this->y],
            'queue' => $target === null ? null : ['vers' => $target->value, 'x' => $this->tail_x === null ? null : (float) $this->tail_x, 'y' => $this->tail_y === null ? null : (float) $this->tail_y],
            'associee_a' => $this->linked_to === null ? null : (int) $this->linked_to,
            'superpose' => (bool) $this->is_overlapping,
            'masque' => (bool) $this->is_masked,
        ];
    }
}
