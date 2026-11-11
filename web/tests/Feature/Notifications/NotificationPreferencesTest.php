<?php

declare(strict_types=1);

use App\Application\Shared\NotificationPreferences;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserSetting;
use Database\Seeders\RolesAndPermissionsSeeder;

/*
 * P3-02 : App\Application\Shared\NotificationPreferences (défauts de config/xomiton.php, catégories verrouillées, normalisation)
 * et PATCH /api/v1/me/settings (réglages + préférences par catégorie ; sécurité et SOS jamais désactivables ; 422, 401).
 */

it('expose les défauts et les verrous de config/xomiton.php, cohérents avec les modèles', function (): void {
    expect(NotificationPreferences::categories())->toBe(['securite', 'sos', 'alertes', 'messages', 'communaute', 'evenements', 'moderation', 'systeme'])
        ->and(NotificationPreferences::lockedCategories())->toBe(UserSetting::LOCKED_CATEGORIES)
        ->and(NotificationPreferences::isLocked('sos'))->toBeTrue()
        ->and(NotificationPreferences::isLocked('communaute'))->toBeFalse()
        ->and(NotificationPreferences::defaults()['messages'])->toBe(['in_app' => true, 'email' => false, 'push' => true])
        ->and(UserSetting::defaultNotificationPrefs())->toBe(NotificationPreferences::defaults())
        ->and(array_keys(NotificationPreferences::defaults()['sos']))->toBe(NotificationPreferences::CHANNELS);
});

it('normalise des préférences stockées : inconnues ignorées, canaux manquants complétés, verrous forcés', function (): void {
    $normalized = NotificationPreferences::normalize([
        'communaute' => ['email' => true],
        'securite' => ['in_app' => false, 'email' => false, 'push' => false],
        'newsletter' => ['email' => true],
        'messages' => 'oups',
    ]);

    expect($normalized['communaute'])->toBe(['in_app' => true, 'email' => true, 'push' => true])
        ->and($normalized['securite'])->toBe(['in_app' => true, 'email' => true, 'push' => true])
        ->and($normalized['messages'])->toBe(NotificationPreferences::defaults()['messages'])
        ->and($normalized)->not->toHaveKey('newsletter')
        ->and(NotificationPreferences::allows(null, 'messages', 'email'))->toBeFalse()
        ->and(NotificationPreferences::allows(['sos' => ['push' => false]], 'sos', 'push'))->toBeTrue()
        ->and(NotificationPreferences::allows([], 'categorie_inconnue', 'push'))->toBeTrue();
});

it('enregistre des préférences partielles, refuse une catégorie ou un canal inconnu et crée les réglages manquants', function (): void {
    $service = app(NotificationPreferences::class);
    $user = User::factory()->create();

    expect($user->settings)->toBeNull();

    $saved = $service->set($user, ['communaute' => ['push' => false], 'securite' => ['email' => false]]);

    expect($saved['communaute'])->toBe(['in_app' => true, 'email' => false, 'push' => false])
        ->and($saved['securite']['email'])->toBeTrue()
        ->and($user->fresh()->settings->notification_prefs['communaute']['push'])->toBeFalse()
        ->and($service->wants($user->fresh(), 'communaute', 'push'))->toBeFalse()
        ->and($service->wants($user->fresh(), 'securite', 'email'))->toBeTrue()
        ->and(fn () => $service->set($user, ['newsletter' => ['email' => true]]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $service->set($user, ['communaute' => ['sound' => true]]))->toThrow(InvalidArgumentException::class);
});

describe('PATCH /api/v1/me/settings', function (): void {
    beforeEach(function (): void {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->user = User::factory()->withProfile()->create();
        $this->user->assignRole('membre');
        $this->token = $this->user->createToken('Pixel 7')->plainTextToken;
    });

    it('met à jour thème, discrétion et préférences, renvoie le compte et journalise', function (): void {
        $response = $this->withToken($this->token)->patchJson('/api/v1/me/settings', [
            'theme' => 'dark',
            'mask_sensitive_notifications' => true,
            'notification_digest' => 'weekly',
            'notification_prefs' => ['communaute' => ['email' => true, 'push' => false], 'messages' => ['in_app' => false]],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.settings.theme', 'dark')
            ->assertJsonPath('data.settings.mask_sensitive_notifications', true)
            ->assertJsonPath('data.settings.notification_digest', 'weekly')
            ->assertJsonPath('data.settings.notification_prefs.communaute', ['in_app' => true, 'email' => true, 'push' => false])
            ->assertJsonPath('data.settings.notification_prefs.messages.in_app', false)
            ->assertJsonPath('data.settings.notification_prefs.evenements', NotificationPreferences::defaults()['evenements']);

        expect(AuditLog::query()->ofAction('me.settings_updated')->forSubject($this->user)->value('detail'))
            ->toContain('theme')->toContain('notification_prefs')->not->toContain('dark');
    });

    it('ignore une tentative de désactiver la sécurité du compte ou le SOS', function (): void {
        $this->withToken($this->token)->patchJson('/api/v1/me/settings', [
            'notification_prefs' => ['securite' => ['in_app' => false, 'email' => false, 'push' => false], 'sos' => ['push' => false]],
        ])
            ->assertOk()
            ->assertJsonPath('data.settings.notification_prefs.securite', ['in_app' => true, 'email' => true, 'push' => true])
            ->assertJsonPath('data.settings.notification_prefs.sos.push', true);
    });

    it('répond 422 pour une catégorie, un canal, un thème ou une langue inconnus, et 401 sans jeton', function (): void {
        $this->withToken($this->token)->patchJson('/api/v1/me/settings', ['notification_prefs' => ['newsletter' => ['email' => true]]])
            ->assertUnprocessable()->assertJsonValidationErrors(['notification_prefs']);

        $this->withToken($this->token)->patchJson('/api/v1/me/settings', ['notification_prefs' => ['communaute' => ['sound' => true]]])
            ->assertUnprocessable()->assertJsonValidationErrors(['notification_prefs.communaute']);

        $this->withToken($this->token)->patchJson('/api/v1/me/settings', ['theme' => 'sepia', 'language' => 'en', 'notification_digest' => 'hourly'])
            ->assertUnprocessable()->assertJsonValidationErrors(['theme', 'language', 'notification_digest']);

        $this->withoutToken();
        app('auth')->forgetGuards();
        $this->patchJson('/api/v1/me/settings', ['theme' => 'dark'])->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
        expect($this->user->fresh()->settings->theme)->not->toBe('sepia');
    });
});
