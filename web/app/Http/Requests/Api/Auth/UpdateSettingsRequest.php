<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Auth;

use App\Application\Shared\NotificationPreferences;
use App\Models\UserSetting;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PATCH /api/v1/me/settings (P3-02) : thème, langue, préférences de notification par catégorie (`in_app`, `email`,
 * `push` ; sécurité du compte et SOS verrouillés côté serveur, une valeur envoyée pour elles est ignorée), résumé
 * d'activité, discrétion, partage GPS au SOS, titre d'onglet neutre. Chaque champ absent est laissé tel quel.
 * Le code PIN (`pin_enabled`, `pin_hash`) relève de `me/security` (P6).
 */
final class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $categories = NotificationPreferences::categories();

        return [
            'theme' => ['sometimes', 'string', Rule::in(UserSetting::THEMES)],
            'language' => ['sometimes', 'string', Rule::in((array) config('xomiton.locales', ['fr']))],
            'notification_prefs' => ['sometimes', 'array', $this->knownKeys($categories, 'validation.in')],
            'notification_prefs.*' => ['array', $this->knownKeys(NotificationPreferences::CHANNELS, 'validation.in')],
            'notification_prefs.*.in_app' => ['sometimes', 'boolean'],
            'notification_prefs.*.email' => ['sometimes', 'boolean'],
            'notification_prefs.*.push' => ['sometimes', 'boolean'],
            'notification_digest' => ['sometimes', 'string', Rule::in(UserSetting::DIGESTS)],
            'mask_sensitive_notifications' => ['sometimes', 'boolean'],
            'sos_share_gps' => ['sometimes', 'boolean'],
            'neutral_tab_title' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'theme' => __('profile.settings.sections.appearance'),
            'language' => __('profile.settings.sections.language'),
            'notification_prefs' => __('notifications.preferences.title'),
            'notification_digest' => __('notifications.preferences.digest'),
            'mask_sensitive_notifications' => __('notifications.preferences.mask_sensitive'),
        ];
    }

    /**
     * Réglages validés, prêts pour le cas d'usage (seuls les champs présents).
     *
     * @return array<string, mixed>
     */
    public function toSettings(): array
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return $validated;
    }

    /**
     * Les clés d'un tableau doivent appartenir à une liste connue (catégories, canaux) : 422 sinon.
     *
     * @param  list<string>  $allowed
     */
    private function knownKeys(array $allowed, string $message): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail) use ($allowed, $message): void {
            if (! is_array($value)) {
                return;
            }

            foreach (array_keys($value) as $key) {
                if (! in_array((string) $key, $allowed, true)) {
                    $fail($message)->translate(['attribute' => $attribute.'.'.$key]);

                    return;
                }
            }
        };
    }
}
