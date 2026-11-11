<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Member;

use App\Application\Account\MemberNewsletter;
use App\Application\Account\UserSettings;
use App\Application\Auth\ConnectedDevices;
use App\Application\Shared\NotificationPreferences;
use App\Application\Shared\Referentials;
use App\Enums\ProfileVisibility;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Me\UpdateIdentityRequest;
use App\Http\Resources\Me\DeviceResource;
use App\Http\Resources\Me\IdentityResource;
use App\Http\Resources\Me\NewsletterResource;
use App\Http\Resources\Me\PrivacyResource;
use App\Http\Resources\Me\SecurityResource;
use App\Http\Resources\SessionResource;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Écran Vue « Paramètres » (P8-09, prototype settings.php l.100-101) : page Inertia `Member/Settings` dans le gabarit
 * membre, sections du prototype dans l'ordre — Profil · Mot de passe · Confidentialité · Notifications · Langue ·
 * Apparence · Sécurité (et appareils connectés) · Newsletter · Mes données · Supprimer mon compte ; *sans* sortie
 * rapide (D8). Chaque section a son état initial dans les props, sous la forme de l'API `me/*` (P6-06, P6-09, P8-09)
 * qui l'enregistre ensuite : identité, confidentialité, préférences de notification (sécurité et SOS verrouillées,
 * NotificationPreferences), sécurité, newsletter, sessions et appareils de l'application.
 */
final class SettingsPageController extends Controller
{
    public function __construct(
        private readonly ConnectedDevices $devices,
        private readonly MemberNewsletter $newsletter,
    ) {}

    public function __invoke(Request $request, Referentials $referentials): Response
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('profile');
        $settings = UserSettings::ensure($user);
        $title = (string) __('profile.settings.title');

        return Inertia::render('Member/Settings', [
            'title' => $title,
            'identity' => fn (): array => (new IdentityResource($user))->resolve($request),
            'zones' => fn (): array => array_map(static fn (array $zone): array => [
                'id' => $zone['id'],
                'name' => $zone['name'],
                'communes' => array_map(static fn (array $commune): array => ['id' => $commune['id'], 'name' => $commune['name']], $zone['communes']),
            ], $referentials->zones()),
            'privacy' => fn (): array => (new PrivacyResource($user))->resolve($request),
            'visibilities' => array_map(static fn (ProfileVisibility $visibility): array => [
                'value' => $visibility->value,
                'label' => $visibility->label(),
            ], ProfileVisibility::cases()),
            'notifications' => fn (): array => self::notifications($settings),
            'security' => fn (): array => (new SecurityResource($user))->resolve($request),
            'appearance' => ['theme' => in_array($settings->theme, UserSetting::THEMES, true) ? $settings->theme : 'auto'],
            'language' => [
                'current' => (string) ($settings->language ?: config('xomiton.default_locale', 'fr')),
                'available' => array_values((array) config('xomiton.locales', ['fr'])),
                'planned' => array_values((array) config('xomiton.locales_planned', [])),
            ],
            'newsletter' => fn (): array => (new NewsletterResource($this->newsletter->find($user)))->resolve($request),
            'sessions' => fn (): array => SessionResource::collection($this->devices->list(
                $user,
                null,
                $request->hasSession() ? $request->session()->getId() : null,
            ))->resolve($request),
            'devices' => fn (): array => DeviceResource::collection(
                $user->syncDevices()->orderByDesc('last_seen_at')->orderByDesc('id')->get(),
            )->resolve($request),
            'limits' => [
                'password_min' => (int) config('xomiton.accounts.password_min_length', 10),
                'name_max' => UpdateIdentityRequest::NAME_MAX,
                'bio_max' => UpdateIdentityRequest::BIO_MAX,
                'deletion_days' => (int) config('xomiton.accounts.deletion_grace_days', 30),
                'export_hours' => (int) config('xomiton.accounts.export_link_hours', 48),
            ],
            'can' => [
                'delete_account' => $user->can('deleteOwnAccount', $user),
            ],
        ])->withViewData(['title' => $title]);
    }

    /**
     * Préférences par catégorie (ordre de la configuration), catégories verrouillées signalées, résumé d'activité.
     *
     * @return array{prefs: array<string, array{in_app: bool, email: bool, push: bool}>, categories: list<array{key: string, locked: bool}>, channels: list<string>, digest: string, digests: list<string>}
     */
    private static function notifications(UserSetting $settings): array
    {
        return [
            'prefs' => $settings->notificationPrefs(),
            'categories' => array_map(static fn (string $category): array => [
                'key' => $category,
                'locked' => NotificationPreferences::isLocked($category),
            ], NotificationPreferences::categories()),
            'channels' => NotificationPreferences::CHANNELS,
            'digest' => in_array($settings->notification_digest, UserSetting::DIGESTS, true) ? (string) $settings->notification_digest : UserSetting::DIGEST_NEVER,
            'digests' => UserSetting::DIGESTS,
        ];
    }
}
