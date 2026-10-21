<?php

declare(strict_types=1);

use App\Models\AppVersion;
use App\Support\Settings;

/*
 * P13-02 : GET /api/v1/app/version — version courante et minimale de l'application mobile, mise à jour disponible ou
 * requise selon la version annoncée ; public, sans session ni cookie, mis en cache 5 minutes, version mal formée ignorée.
 */

beforeEach(function (): void {
    AppVersion::factory()->version('1.4.2')->create(['deployed_at' => now()->subDays(20), 'notes' => 'Version précédente.']);
    AppVersion::factory()->version('1.5.0')->create(['deployed_at' => now()->subDay(), 'kind' => 'fonctionnalite', 'notes' => 'Nouvelle messagerie.']);
    AppVersion::factory()->version('1.6.0')->planned()->create();
});

it('sert la version courante, la minimale et les notes sans compte ni cookie', function (): void {
    Settings::set('app_min_version', '1.4.0');

    $response = $this->getJson('/api/v1/app/version')->assertOk()
        ->assertJsonPath('data.current', '1.5.0')
        ->assertJsonPath('data.minimum', '1.4.0')
        ->assertJsonPath('data.kind', 'fonctionnalite')
        ->assertJsonPath('data.notes', 'Nouvelle messagerie.')
        ->assertJsonPath('data.client_version', null)
        ->assertJsonPath('data.update_available', null)
        ->assertJsonPath('data.update_required', null)
        ->assertHeader('Cache-Control', 'max-age=300, public');

    expect($response->headers->getCookies())->toBe([]);
});

it('indique si une mise à jour est disponible ou obligatoire selon la version du client', function (): void {
    Settings::set('app_min_version', '1.4.0');

    $this->getJson('/api/v1/app/version?version=1.3.9')->assertOk()
        ->assertJsonPath('data.update_available', true)->assertJsonPath('data.update_required', true);
    $this->getJson('/api/v1/app/version?version=1.4.2')->assertOk()
        ->assertJsonPath('data.update_available', true)->assertJsonPath('data.update_required', false);
    $this->getJson('/api/v1/app/version', ['X-App-Version' => '1.5.0'])->assertOk()
        ->assertJsonPath('data.client_version', '1.5.0')->assertJsonPath('data.update_available', false)->assertJsonPath('data.update_required', false);
});

it('ignore une version mal formée et plafonne la minimale à la version courante', function (): void {
    Settings::set('app_min_version', '9.0.0');

    $this->getJson('/api/v1/app/version?version=abc')->assertOk()
        ->assertJsonPath('data.client_version', null)->assertJsonPath('data.update_required', null)
        ->assertJsonPath('data.minimum', '1.5.0');
    $this->getJson('/api/v1/app/version?version[]=1')->assertOk()->assertJsonPath('data.client_version', null);
});
