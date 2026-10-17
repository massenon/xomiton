<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AssistantMessageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Message d'une conversation avec l'assistant (DATABASE_DICTIONARY.md §3 `assistant_messages`) :
 * corps chiffré, horodatage de création seul.
 */
class AssistantMessage extends Model
{
    /** @use HasFactory<AssistantMessageFactory> */
    use HasFactory;

    public const ROLE_USER = 'user';

    public const ROLE_ASSISTANT = 'assistant';

    public const ROLE_SYSTEM = 'system';

    /** @var list<string> */
    public const ROLES = [
        self::ROLE_USER,
        self::ROLE_ASSISTANT,
        self::ROLE_SYSTEM,
    ];

    /** Un message n'est jamais modifié (dictionnaire : `created_at` seul). */
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'conversation_id',
        'role',
        'body',
        'matched_kb_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'body' => 'encrypted',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<AssistantConversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AssistantConversation::class, 'conversation_id');
    }

    /** @return BelongsTo<AssistantKb, $this> */
    public function matchedKb(): BelongsTo
    {
        return $this->belongsTo(AssistantKb::class, 'matched_kb_id');
    }

    /** @param  Builder<AssistantMessage>  $query */
    public function scopeFromUser(Builder $query): void
    {
        $query->where('role', self::ROLE_USER);
    }

    /** @param  Builder<AssistantMessage>  $query */
    public function scopeFromAssistant(Builder $query): void
    {
        $query->where('role', self::ROLE_ASSISTANT);
    }

    public function isFromUser(): bool
    {
        return $this->role === self::ROLE_USER;
    }
}
