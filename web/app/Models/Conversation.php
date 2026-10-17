<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConversationKind;
use App\Models\Concerns\HasUlid;
use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Conversation de messagerie (DATABASE_DICTIONARY.md §4 `conversations`), privée ou de groupe.
 */
class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use HasFactory, HasUlid;

    /** Longueur de `last_message_preview` (aperçu en clair, tronqué). */
    public const PREVIEW_MAX_LENGTH = 120;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ulid',
        'kind',
        'title',
        'group_id',
        'created_by',
        'last_message_at',
        'last_message_preview',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ConversationKind::class,
            'last_message_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<ConversationParticipant, $this>
     */
    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    /**
     * Membres encore présents dans la conversation.
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
            ->withPivot(['role', 'last_read_message_id', 'muted_until', 'left_at'])
            ->wherePivotNull('left_at')
            ->withTimestamps();
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('id');
    }

    /**
     * @param  Builder<Conversation>  $query
     */
    public function scopePrivate(Builder $query): void
    {
        $query->where('kind', ConversationKind::Private);
    }

    /**
     * @param  Builder<Conversation>  $query
     */
    public function scopeGroups(Builder $query): void
    {
        $query->where('kind', ConversationKind::Group);
    }

    /**
     * Conversations auxquelles le ou la membre participe encore, dernière activité en tête.
     *
     * @param  Builder<Conversation>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->whereHas('participants', function (Builder $q) use ($user): void {
            $q->where('user_id', $user->id)->whereNull('left_at');
        })->orderByDesc('last_message_at');
    }

    /**
     * Met à jour la dénormalisation du dernier message (appelé par l'action d'envoi).
     */
    public function touchLastMessage(Message $message): void
    {
        $this->forceFill([
            'last_message_at' => $message->created_at,
            'last_message_preview' => mb_substr((string) $message->body, 0, self::PREVIEW_MAX_LENGTH),
        ])->save();
    }

    public function isGroup(): bool
    {
        return $this->kind->isGroup();
    }
}
