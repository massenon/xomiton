<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AssistantConversationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Conversation avec l'assistant (DATABASE_DICTIONARY.md §3 `assistant_conversations`) : d'un·e membre
 * ou d'un·e visiteur·se identifié·e par `session_key`.
 */
class AssistantConversation extends Model
{
    /** @use HasFactory<AssistantConversationFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'session_key',
        'is_flagged',
        'flag_reason',
        'escalated_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_flagged' => 'boolean',
            'escalated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<AssistantMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(AssistantMessage::class, 'conversation_id')->orderBy('id');
    }

    /** @param  Builder<AssistantConversation>  $query */
    public function scopeFlagged(Builder $query): void
    {
        $query->where('is_flagged', true);
    }

    /** @param  Builder<AssistantConversation>  $query */
    public function scopeEscalated(Builder $query): void
    {
        $query->whereNotNull('escalated_at');
    }

    /**
     * Conversations d'un·e membre connecté·e ou d'une session anonyme donnée.
     *
     * @param  Builder<AssistantConversation>  $query
     */
    public function scopeOwnedBy(Builder $query, ?User $user, ?string $sessionKey = null): void
    {
        if ($user !== null) {
            $query->where('user_id', $user->getKey());

            return;
        }

        $query->whereNull('user_id')->where('session_key', $sessionKey);
    }

    public function isAnonymous(): bool
    {
        return $this->user_id === null;
    }

    /**
     * Marque la conversation pour relecture humaine (mots bloquants, détresse, demande explicite).
     */
    public function flag(string $reason): void
    {
        $this->forceFill([
            'is_flagged' => true,
            'flag_reason' => mb_substr($reason, 0, 200),
        ])->save();
    }

    public function escalate(): void
    {
        $this->forceFill(['escalated_at' => $this->escalated_at ?? now()])->save();
    }
}
