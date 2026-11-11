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
 *
 * Entrée dans un **groupe** (ajout ou retour après un départ), quel que soit le chemin (Messaging\ManageConversation,
 * Community\Groups\GroupDiscussion) : `joined_after_message_id` et `last_read_message_id` sont posés sur le dernier
 * message de la conversation — la personne ne lit que la suite (ConversationDirectory), sans non-lus d'avant son entrée.
 * Une conversation privée garde son historique (la quitter la masque seulement). `left_reason` : `left` (départ de son
 * propre chef, jamais réintégrée par quelqu'un d'autre) ou `removed` (retirée par un·e admin).
 */
class ConversationParticipant extends Model
{
    /** @use HasFactory<ConversationParticipantFactory> */
    use HasFactory;

    public const ROLE_MEMBER = 'member';

    public const ROLE_ADMIN = 'admin';

    /** `left_reason` : départ de son propre chef (personne d'autre ne la réintègre). */
    public const LEFT_SELF = 'left';

    /** `left_reason` : retirée par un·e admin du groupe (peut être ajoutée de nouveau). */
    public const LEFT_REMOVED = 'removed';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'conversation_id',
        'user_id',
        'role',
        'joined_after_message_id',
        'last_read_message_id',
        'muted_until',
        'left_at',
        'left_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'joined_after_message_id' => 'integer',
            'last_read_message_id' => 'integer',
            'muted_until' => 'datetime',
            'left_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $participant): void {
            $entering = ! $participant->exists
                || ($participant->isDirty('left_at') && $participant->left_at === null && $participant->getOriginal('left_at') !== null);

            if (! $entering) {
                return;
            }

            $participant->left_reason = null;
            $conversation = $participant->conversation;

            if ($conversation === null || ! $conversation->isGroup()) {
                return;
            }

            $latest = Message::query()->where('conversation_id', $conversation->getKey())->max('id');
            $participant->joined_after_message_id = $latest === null ? null : (int) $latest;

            if ($latest !== null && (int) ($participant->last_read_message_id ?? 0) < (int) $latest) {
                $participant->last_read_message_id = (int) $latest;
            }
        });
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

    /** Un·e admin ne peut ajouter de nouveau que quelqu'un qu'on a retiré, jamais une personne partie d'elle-même. */
    public function canBeAddedBack(): bool
    {
        return ! $this->hasLeft() || $this->left_reason === self::LEFT_REMOVED;
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
            ->where('id', '>', max($this->last_read_message_id ?? 0, $this->joined_after_message_id ?? 0))
            ->where(function (Builder $q): void {
                $q->whereNull('user_id')->orWhere('user_id', '!=', $this->user_id);
            })
            ->count();
    }
}
