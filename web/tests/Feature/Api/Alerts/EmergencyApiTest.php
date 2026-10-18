<?php

declare(strict_types=1);

use App\Application\Alerts\Queries\ListEmergencyContacts;
use App\Enums\DirectoryRole;
use App\Models\DirectoryContact;
use App\Support\Settings;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Support\AlertFixtures;

/*
 * P5-06 : GET urgences?zone= — numéros nationaux (paramètre emergency_numbers, sinon config) et fiches d'urgence de
 * l'annuaire (actives, is_public, rôle point focal / juriste / psychologue ou point focal national : Q19, l'annuaire
 * complet reste membre), celles du département d'abord puis les nationales ; même source que /urgences
 * (ListEmergencyContacts) ; cache serveur invalidé par l'annuaire ; cache HTTP public avec ETag comme les référentiels.
 * Écart suivi (plan Q59) : ni emergency_services ni directory_contacts.is_emergency n'existent encore.
 */

beforeEach(function (): void {
    $this->seed(ReferenceDataSeeder::class);
});

it('sert les numéros nationaux et les seules fiches publiques actives, sans e-mail ni notes', function (): void {
    $littoral = AlertFixtures::zone('Littoral');
    $public = DirectoryContact::factory()->role(DirectoryRole::Juriste)->inZone($littoral)->create(['name' => 'ADJOVI Rose', 'notes' => 'note interne']);
    DirectoryContact::factory()->inZone($littoral)->hidden()->create(['name' => 'MASQUÉE Fiche']);
    DirectoryContact::factory()->inZone($littoral)->inactive()->create(['name' => 'INACTIVE Fiche']);

    $response = $this->getJson('/api/v1/urgences')->assertOk()
        ->assertJsonPath('data.services.0.number', config('xomiton.assistant.emergency_numbers.0.number'))
        ->assertJsonPath('data.services.0.tel', 'tel:117')
        ->assertJsonPath('data.contacts.0.name', 'ADJOVI Rose')
        ->assertJsonPath('data.contacts.0.role', DirectoryRole::Juriste->value)
        ->assertJsonPath('data.contacts.0.zone.name', 'Littoral')
        ->assertJsonPath('meta.zone', null)
        ->assertJsonPath('meta.count', 1)
        ->assertJsonPath('meta.truncated', false)
        ->assertHeader('ETag');

    expect($response->headers->getCacheControlDirective('public'))->toBeTrue()
        ->and($response->headers->getCacheControlDirective('max-age'))->toBe((string) config('xomiton.referentials.http_max_age'));

    $body = (string) $response->getContent();

    expect($response->json('data.contacts'))->toHaveCount(1)
        ->and($body)->not->toContain((string) $public->email)->not->toContain('note interne');
});

it('filtre par département : fiches de la zone d’abord, puis les fiches nationales', function (): void {
    $littoral = AlertFixtures::zone('Littoral');
    $borgou = AlertFixtures::zone('Borgou');
    DirectoryContact::factory()->inZone($borgou)->create(['name' => 'ZOUNON Ailleurs']);
    DirectoryContact::factory()->focalNational()->create(['name' => 'AHO National']);
    DirectoryContact::factory()->role(DirectoryRole::Psychologue)->inZone($littoral)->create(['name' => 'ZINSOU Littoral']);

    $this->getJson('/api/v1/urgences?zone='.$littoral->id)->assertOk()
        ->assertJsonPath('meta.zone', ['id' => $littoral->id, 'name' => 'Littoral'])
        ->assertJsonPath('data.contacts.*.name', ['ZINSOU Littoral', 'AHO National'])
        ->assertJsonPath('data.contacts.1.national', true);

    $this->getJson('/api/v1/urgences?zone=999')->assertUnprocessable()->assertJsonValidationErrors('zone');
    $this->getJson('/api/v1/urgences?zone=abc')->assertUnprocessable()->assertJsonPath('code', 'validation_failed');
});

it('lit les numéros administrés (paramètre emergency_numbers) et répond 304 à un ETag connu', function (): void {
    Settings::set('emergency_numbers', [['label' => 'Ligne verte', 'number' => '+229 01 00 00 00 00']]);

    $first = $this->getJson('/api/v1/urgences')->assertOk()
        ->assertJsonPath('data.services', [['label' => 'Ligne verte', 'number' => '+229 01 00 00 00 00', 'tel' => 'tel:+2290100000000']]);

    expect(app(ListEmergencyContacts::class)()->services)->toBe([['label' => 'Ligne verte', 'number' => '+229 01 00 00 00 00']]);

    $this->getJson('/api/v1/urgences', ['If-None-Match' => (string) $first->headers->get('ETag')])->assertStatus(304);
});

it('ne sert jamais l’annuaire complet : seules les fiches d’urgence publiques sortent hors connexion (Q19)', function (): void {
    $littoral = AlertFixtures::zone('Littoral');

    foreach ([DirectoryRole::PairAnimateur, DirectoryRole::ResponsableReseau, DirectoryRole::PersonnelIdentifie, DirectoryRole::TravailleurSocial, DirectoryRole::Gynecologue] as $role) {
        DirectoryContact::factory()->role($role)->inZone($littoral)->create(['name' => 'HORS '.$role->value, 'is_public' => true]);
    }
    DirectoryContact::factory()->focalZone($littoral)->create(['name' => 'DOSSOU Focal']);
    DirectoryContact::factory()->role(DirectoryRole::Psychologue)->inZone($littoral)->create(['name' => 'KPADE Psy']);

    $response = $this->getJson('/api/v1/urgences')->assertOk()->assertJsonPath('meta.count', 2);

    expect($response->json('data.contacts.*.name'))->toBe(['DOSSOU Focal', 'KPADE Psy'])
        ->and((string) $response->getContent())->not->toContain('HORS ')
        ->and($this->getJson('/api/v1/urgences?zone='.$littoral->id)->json('data.contacts.*.name'))->toBe(['DOSSOU Focal', 'KPADE Psy']);
});

it('met la liste en cache serveur, invalidée par toute écriture sur l’annuaire', function (): void {
    $littoral = AlertFixtures::zone('Littoral');
    $this->getJson('/api/v1/urgences')->assertOk()->assertJsonPath('meta.count', 0);

    $contact = DirectoryContact::factory()->role(DirectoryRole::Juriste)->inZone($littoral)->create(['name' => 'AGOSSOU Juriste']);
    $this->getJson('/api/v1/urgences')->assertJsonPath('data.contacts.*.name', ['AGOSSOU Juriste']);

    // Sans écriture sur l'annuaire, la requête n'est pas rejouée.
    DB::enableQueryLog();
    $this->getJson('/api/v1/urgences')->assertJsonPath('meta.count', 1);
    expect(collect(DB::getQueryLog())->pluck('query')->filter(fn (string $sql): bool => str_contains($sql, 'directory_contacts')))->toBeEmpty();

    $contact->update(['is_public' => false]);
    $this->getJson('/api/v1/urgences')->assertJsonPath('meta.count', 0);
});

it('signale la troncature au-delà du plafond (meta.truncated)', function (): void {
    $littoral = AlertFixtures::zone('Littoral');
    DirectoryContact::factory()->count(ListEmergencyContacts::MAX_CONTACTS + 1)->role(DirectoryRole::Juriste)->inZone($littoral)->create();

    $this->getJson('/api/v1/urgences')->assertOk()
        ->assertJsonPath('meta.count', ListEmergencyContacts::MAX_CONTACTS)
        ->assertJsonPath('meta.truncated', true);
});
