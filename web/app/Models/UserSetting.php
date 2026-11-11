<?php

declare(strict_types=1);

namespace App\Models;

use App\Application\Shared\NotificationPreferences;
use Database\Factories\UserSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSetting extends Model
{
    /** @use HasFactory<UserSettingFactory> */
    use HasFactory;

    /**
     * Catégories de notifications que la personne ne peut pas désactiver (miroir de `locked` dans
     * config/xomiton.php « notifications.categories », vérifié par un test).
     *
     * @var list<string>
     */
    public const LOCKED_CATEGORIES = DatabaseNotification::MANDATORY_CATEGORIES;

    /**
     * Thèmes d'interface admis (dictionnaire : string, pas d'enum).
     *
     * @var list<string>
     */
    public const THEMES = ['light', 'dark', 'auto'];

    /** Alias accepté par l'API (prototype mobile « système », P6-06) et stocké sous `auto`. */
    public const THEME_SYSTEM_ALIAS = 'system';

    /**
     * Fréquences du résumé d'activité par e-mail (dictionnaire : `notification_digest`, pas d'enum dédié).
     */
    public const DIGEST_NEVER = 'never';

    public const DIGEST_DAILY = 'daily';

    public const DIGEST_WEEKLY = 'weekly';

    /** @var list<string> */
    public const DIGESTS = [self::DIGEST_NEVER, self::DIGEST_DAILY, self::DIGEST_WEEKLY];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'theme',
        'language',
        'notification_prefs',
        'mask_sensitive_notifications',
        'notification_digest',
        'sos_share_gps',
        'pin_enabled',
        'pin_hash',
        'neutral_tab_title',
        'sounds_enabled',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'pin_hash',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'notification_prefs' => 'array',
            'mask_sensitive_notifications' => 'boolean',
            'sos_share_gps' => 'boolean',
            'pin_enabled' => 'boolean',
            'neutral_tab_title' => 'boolean',
            'sounds_enabled' => 'boolean',
        ];
    }

    /**
     * Préférences par défaut (config/xomiton.php « notifications.categories », App\Application\Shared\NotificationPreferences).
     *
     * @return array<string, array{in_app: bool, email: bool, push: bool}>
     */
    public static function defaultNotificationPrefs(): array
    {
        return NotificationPreferences::defaults();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wantsDigest(): bool
    {
        return $this->notification_digest !== self::DIGEST_NEVER;
    }

    /**
     * Indique si un canal (`in_app`, `email`, `push`) est actif pour une catégorie ; sécurité et SOS restent toujours actifs.
     */
    public function wants(string $category, string $channel): bool
    {
        return NotificationPreferences::allows($this->notification_prefs, $category, $channel);
    }

    /**
     * Préférences complètes et cohérentes (catégories connues, canaux présents, verrous appliqués).
     *
     * @return array<string, array{in_app: bool, email: bool, push: bool}>
     */
    public function notificationPrefs(): array
    {
        return NotificationPreferences::normalize($this->notification_prefs);
    }
}
