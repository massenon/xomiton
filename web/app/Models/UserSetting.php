<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSetting extends Model
{
    /** @use HasFactory<UserSettingFactory> */
    use HasFactory;

    /**
     * Catégories de notifications que la personne ne peut pas désactiver.
     *
     * @var list<string>
     */
    public const LOCKED_CATEGORIES = ['securite', 'sos'];

    /**
     * Thèmes d'interface admis (dictionnaire : string, pas d'enum).
     *
     * @var list<string>
     */
    public const THEMES = ['light', 'dark', 'auto'];

    /**
     * Fréquences du résumé d'activité par e-mail.
     *
     * @var list<string>
     */
    public const DIGESTS = ['never', 'daily', 'weekly'];

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
        ];
    }

    /**
     * Préférences par défaut : tout activé dans l'application, e-mail pour les catégories importantes.
     *
     * @return array<string, array{in_app: bool, email: bool, push: bool}>
     */
    public static function defaultNotificationPrefs(): array
    {
        return [
            'securite' => ['in_app' => true, 'email' => true, 'push' => true],
            'sos' => ['in_app' => true, 'email' => true, 'push' => true],
            'communaute' => ['in_app' => true, 'email' => false, 'push' => true],
            'messages' => ['in_app' => true, 'email' => false, 'push' => true],
            'evenements' => ['in_app' => true, 'email' => true, 'push' => false],
            'alertes' => ['in_app' => true, 'email' => true, 'push' => true],
            'newsletter' => ['in_app' => false, 'email' => true, 'push' => false],
        ];
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
        return $this->notification_digest !== 'never';
    }

    /**
     * Indique si un canal est actif pour une catégorie ; sécurité et SOS restent toujours actifs.
     */
    public function wants(string $category, string $channel): bool
    {
        if (in_array($category, self::LOCKED_CATEGORIES, true)) {
            return true;
        }

        $prefs = $this->notification_prefs ?? self::defaultNotificationPrefs();

        return (bool) ($prefs[$category][$channel] ?? true);
    }
}
