<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Message d'une conversation (DATABASE_DICTIONARY.md §4 `messages`) : corps chiffré au repos.
 */
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'conversation_id',
        'user_id',
        'body',
        'media_path',
        'client_uuid',
        'delivered_at',
        'read_at',
        'deleted_for_everyone_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'body' => 'encrypted',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'deleted_for_everyone_at' => 'datetime',
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
     * Messages encore visibles (non supprimés pour tout le monde).
     *
     * @param  Builder<Message>  $query
     */
    public function scopeVisible(Builder $query): void
    {
        $query->whereNull('deleted_for_everyone_at');
    }

    /**
     * @param  Builder<Message>  $query
     */
    public function scopeUnread(Builder $query): void
    {
        $query->whereNull('read_at');
    }

    /**
     * Message déjà reçu pour ce `client_uuid` dans la conversation (idempotence hors ligne).
     *
     * @param  Builder<Message>  $query
     */
    public function scopeWithClientUuid(Builder $query, int $conversationId, string $clientUuid): void
    {
        $query->where('conversation_id', $conversationId)->where('client_uuid', $clientUuid);
    }

    public function isDeletedForEveryone(): bool
    {
        return $this->deleted_for_everyone_at !== null;
    }

    public function hasMedia(): bool
    {
        return $this->media_path !== null && $this->media_path !== '';
    }
}
