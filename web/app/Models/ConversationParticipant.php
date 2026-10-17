<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ConversationParticipantFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Participation d'un·e membre à une conversation : rôle, curseur de lecture, sourdine, départ.
 */
class ConversationParticipant extends Model
{
    /** @use HasFactory<ConversationParticipantFactory> */
    use HasFactory;

    public const ROLE_MEMBER = 'member';

    public const ROLE_ADMIN = 'admin';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'conversation_id',
        'user_id',
        'role',
        'last_read_message_id',
        'muted_until',
        'left_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_read_message_id' => 'integer',
            'muted_until' => 'datetime',
            'left_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Participant·es encore présent·es.
     *
     * @param  Builder<ConversationParticipant>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('left_at');
    }

    /**
     * @param  Builder<ConversationParticipant>  $query
     */
    public function scopeAdmins(Builder $query): void
    {
        $query->where('role', self::ROLE_ADMIN);
    }

    /**
     * Participant·es à notifier (présent·es et non en sourdine).
     *
     * @param  Builder<ConversationParticipant>  $query
     */
    public function scopeNotifiable(Builder $query): void
    {
        $query->active()->where(function (Builder $q): void {
            $q->whereNull('muted_until')->orWhere('muted_until', '<', now());
        });
    }

    public function isMuted(): bool
    {
        return $this->muted_until !== null && $this->muted_until->isFuture();
    }

    public function hasLeft(): bool
    {
        return $this->left_at !== null;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /**
     * Nombre de messages non lus depuis le curseur de lecture.
     */
    public function unreadCount(): int
    {
        return $this->conversation->messages()
            ->where('id', '>', $this->last_read_message_id ?? 0)
            ->where(function (Builder $q): void {
                $q->whereNull('user_id')->orWhere('user_id', '!=', $this->user_id);
            })
            ->count();
    }
}
