<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DatabaseNotificationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\DatabaseNotification as BaseDatabaseNotification;

/**
 * Notification en base (DATABASE_DICTIONARY.md §9 `notifications`) : modèle Laravel enrichi de la
 * catégorie (préférences par catégorie de `user_settings.notification_prefs`) et du drapeau sensible
 * (masquée dans la cloche quand `mask_sensitive_notifications` est actif).
 */
class DatabaseNotification extends BaseDatabaseNotification
{
    /** @use HasFactory<DatabaseNotificationFactory> */
    use HasFactory;

    /** Catégories de NOTIFICATIONS_INSTRUCTION.md ; `securite` et `sos` ne sont pas désactivables. */
    public const CATEGORY_SECURITY = 'securite';

    public const CATEGORY_SOS = 'sos';

    public const CATEGORY_COMMUNITY = 'communaute';

    public const CATEGORY_MESSAGES = 'messages';

    public const CATEGORY_EVENTS = 'evenements';

    public const CATEGORY_MODERATION = 'moderation';

    public const CATEGORY_SYSTEM = 'systeme';

    /** @var list<string> */
    public const MANDATORY_CATEGORIES = [self::CATEGORY_SECURITY, self::CATEGORY_SOS];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'is_sensitive' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<DatabaseNotification>  $query
     */
    public function scopeInCategory(Builder $query, string $category): void
    {
        $query->where('category', $category);
    }

    /**
     * @param  Builder<DatabaseNotification>  $query
     */
    public function scopeSensitive(Builder $query): void
    {
        $query->where('is_sensitive', true);
    }

    /**
     * @param  Builder<DatabaseNotification>  $query
     */
    public function scopeLatestFirst(Builder $query): void
    {
        $query->orderByDesc('created_at')->orderByDesc('id');
    }

    public function isSensitive(): bool
    {
        return $this->is_sensitive;
    }

    /** La personne ne peut pas désactiver cette catégorie dans ses préférences. */
    public function isMandatory(): bool
    {
        return in_array($this->category, self::MANDATORY_CATEGORIES, true);
    }
}
