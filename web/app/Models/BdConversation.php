<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BdConversationOrigin;
use App\Enums\BdConversationStatus;
use App\Models\Concerns\HasUlid;
use Database\Factories\BdConversationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Conversation en BD (P20-03, PRD conversation-en-bd, DATABASE_DICTIONARY.md `bd_conversations`) : 3 à 8 cases
 * (`bd_panels`) portant des bulles (`bd_bubbles`). Modèle de l'équipe (`model_key` m01…m12, import) ou création d'un·e
 * membre (lot B : `author_id`, `is_anonymous` vrai par défaut, Q99). Seul le statut `publiee` est public et indexé.
 */
class BdConversation extends Model
{
    /** @use HasFactory<BdConversationFactory> */
    use HasFactory, HasUlid;

    protected $table = 'bd_conversations';

    /** @var list<string> */
    protected $fillable = [
        'slug', 'model_key', 'title', 'summary', 'theme', 'category', 'audience', 'key_message', 'content_warning',
        'discussion_questions', 'position', 'meta_title', 'meta_description', 'og_image_path', 'thematique_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'origin' => BdConversationOrigin::class,
            'status' => BdConversationStatus::class,
            'is_anonymous' => 'boolean',
            'discussion_questions' => 'array',
            'position' => 'integer',
            'author_id' => 'integer',
            'thematique_id' => 'integer',
            'reviewed_by' => 'integer',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'published_at' => 'datetime',
            'withdrawn_at' => 'datetime',
            'content_edited_at' => 'datetime',
            'status_changed_at' => 'datetime',
        ];
    }

    /** @return HasMany<BdPanel, $this> */
    public function panels(): HasMany
    {
        return $this->hasMany(BdPanel::class, 'conversation_id')->orderBy('position')->orderBy('id');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @param  Builder<BdConversation>  $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', BdConversationStatus::Published->value);
    }

    /** @param  Builder<BdConversation>  $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('id');
    }

    /** Statut lu sans dépendre du cast (valeur inconnue → null). */
    public function statusValue(): ?BdConversationStatus
    {
        $raw = $this->attributes['status'] ?? null;

        return BdConversationStatus::tryFrom(is_string($raw) ? $raw : '');
    }

    public function isPublished(): bool
    {
        return $this->statusValue() === BdConversationStatus::Published;
    }

    /** @return list<string> */
    public function questions(): array
    {
        return array_values(array_filter(array_map(static fn (mixed $q): string => is_string($q) ? trim($q) : '', (array) $this->discussion_questions), static fn (string $q): bool => $q !== ''));
    }
}
