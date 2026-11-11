<?php

declare(strict_types=1);

use App\Application\Alerts\Support\AcknowledgeLink;
use App\Enums\AlertStatus;
use App\Http\Middleware\EnsureNotInMaintenance;
use App\Models\Alert;
use App\Models\AlertEscalation;
use App\Models\DirectoryContact;
use App\Support\Settings;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Support\PrototypeDirectory;

/*
 * P5-02 : page d'accusé de réception par lien signé (contact d'annuaire sans compte, gabarit sobre, vous). Le GET
 * n'écrit rien ; le POST signé + CSRF accuse réception et révèle le moyen de contact ; lien altéré, expiré ou d'une
 * autre fiche → 403 ; limiteur dédié `acknowledge` ; servie pendant la maintenance (RG-14) ; la fiche qui a accusé
 * retrouve les coordonnées par un nouveau POST.
 */

beforeEach(function (): void {
    $this->seed([ReferenceDataSeeder::class, RolesAndPermissionsSeeder::class, SettingsSeeder::class]);
    Notification::fake();
    $this->withoutMiddleware(Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    $this->contact = DirectoryContact::factory()->create();
    $this->alert = Alert::factory()->sos()->routedTo($this->contact)->withContact('+229 01 97 38 88 47')
        ->create(['zone_id' => PrototypeDirectory::zone('Littoral')->id, 'lat' => '6.365360', 'lng' => '2.418330', 'location_accuracy' => 18, 'description' => 'Devant le marché']);
});

it('affiche une confirmation sans accuser réception au GET', function (): void {
    $this->get(AcknowledgeLink::url($this->alert, $this->contact))
        ->assertOk()
        ->assertSee(__('alerts.acknowledge.title', ['code' => $this->alert->displayCode()]))
        ->assertSee(__('alerts.acknowledge.confirm'))
        ->assertSee('noindex', false)
        ->assertDontSee('97 38 88 47')
        ->assertDontSee('Devant le marché')
        ->assertDontSee('google.com/maps', false);

    expect($this->alert->fresh()->acknowledged_at)->toBeNull();
});

it('accuse réception au POST et révèle le moyen de contact et la position', function (): void {
    $this->post(AcknowledgeLink::url($this->alert, $this->contact))
        ->assertOk()
        ->assertSee(__('alerts.acknowledge.done'))
        ->assertSee('href="tel:+2290197388847"', false)
        ->assertSee('https://www.google.com/maps?q=6.36536,2.41833', false)
        ->assertSee('Devant le marché');

    $fresh = $this->alert->fresh();

    expect($fresh->acknowledged_at)->not->toBeNull()
        ->and($fresh->acknowledged_by)->toBeNull()
        ->and($fresh->status)->toBe(AlertStatus::Acknowledged);
});

it('refuse un lien altéré, expiré ou d’une autre fiche, et accepte la cible d’une escalade', function (): void {
    $url = AcknowledgeLink::url($this->alert, $this->contact);
    $stranger = DirectoryContact::factory()->create();
    $national = DirectoryContact::factory()->focalNational()->create();

    $this->get($url.'x')->assertForbidden()->assertSee(__('alerts.acknowledge.invalid'));
    $this->get(str_replace('contact='.$this->contact->id, 'contact='.$stranger->id, $url))->assertForbidden();
    $this->get(AcknowledgeLink::url($this->alert, $stranger))->assertForbidden();
    $this->post(AcknowledgeLink::url($this->alert, $stranger))->assertForbidden();
    $this->get(AcknowledgeLink::url($this->alert, $national))->assertForbidden();

    AlertEscalation::factory()->create(['alert_id' => $this->alert->id, 'from_contact_id' => $this->contact->id, 'to_contact_id' => $national->id]);
    $this->get(AcknowledgeLink::url($this->alert, $national))->assertOk();

    $this->travel(49)->hours();
    $this->get($url)->assertForbidden();

    expect($this->alert->fresh()->acknowledged_at)->toBeNull();
});

it('refuse le lien d’une fiche désactivée', function (): void {
    $url = AcknowledgeLink::url($this->alert, $this->contact);
    $this->contact->update(['is_active' => false]);

    $this->get($url)->assertForbidden();
});

it('signale une alerte déjà accusée ou annulée, sans nouvel accusé', function (): void {
    $this->alert->update(['acknowledged_at' => now()->subMinutes(3), 'status' => AlertStatus::Acknowledged]);
    $this->get(AcknowledgeLink::url($this->alert, $this->contact))->assertOk()->assertSee(Illuminate\Support\Str::before(__('alerts.acknowledge.already'), ' :date'))->assertDontSee(__('alerts.acknowledge.confirm'));
    $this->post(AcknowledgeLink::url($this->alert, $this->contact))->assertOk()->assertDontSee('97 38 88 47');

    $cancelled = Alert::factory()->sos()->cancelled()->routedTo($this->contact)->create();
    $this->get(AcknowledgeLink::url($cancelled, $this->contact))->assertOk()->assertSee(__('alerts.acknowledge.closed'));
    $this->post(AcknowledgeLink::url($cancelled, $this->contact))->assertOk()->assertSee(__('alerts.acknowledge.closed'));

    expect($cancelled->fresh()->acknowledged_at)->toBeNull();
});

it('a son propre limiteur par IP, alerte et fiche, indépendant du suivi par code', function (): void {
    config(['xomiton.rate_limits.acknowledge_per_hour_per_link' => 3, 'xomiton.rate_limits.tracking_per_hour_per_ip' => 1]);
    $url = AcknowledgeLink::url($this->alert, $this->contact);
    $other = Alert::factory()->routedTo($this->contact)->create();
    RateLimiter::hit('tracking|127.0.0.1', 3600);
    RateLimiter::hit('tracking|127.0.0.1', 3600);

    foreach (range(1, 3) as $ignored) {
        $this->get($url)->assertOk();
    }

    $this->get($url)->assertTooManyRequests();
    $this->get(AcknowledgeLink::url($other, $this->contact))->assertOk();
});

it('reste servie pendant la maintenance, sans ouvrir d’autres chemins /alertes/…', function (): void {
    Settings::set('maintenance', true);

    $this->get(AcknowledgeLink::url($this->alert, $this->contact))->assertOk();
    $this->get('/thematiques')->assertStatus(503);

    expect(EnsureNotInMaintenance::isAlwaysAllowed(Request::create('/alertes/'.$this->alert->ulid.'/accuser')))->toBeTrue()
        ->and(EnsureNotInMaintenance::isAlwaysAllowed(Request::create('/alertes/mes-alertes')))->toBeFalse()
        ->and(EnsureNotInMaintenance::isAlwaysAllowed(Request::create('/alertes/'.$this->alert->ulid.'/notes')))->toBeFalse();
});

it('affiche la priorité de l’alerte et « Reçue le »', function (): void {
    $report = Alert::factory()->routedTo($this->contact)->create();

    $this->get(AcknowledgeLink::url($report, $this->contact))->assertOk()
        ->assertSee(__('alerts.acknowledge.priority', ['priority' => __('enums/alerts.alert_priority.normale')]))
        ->assertSee(Illuminate\Support\Str::before(__('alerts.acknowledge.received_on'), ' :date'))
        ->assertDontSee(__('components.urgency.levels.low'));
    $this->get(AcknowledgeLink::url($this->alert, $this->contact))->assertOk()
        ->assertSee(__('alerts.acknowledge.priority', ['priority' => __('enums/alerts.alert_priority.critique')]));
});

it('redonne les coordonnées à la fiche qui a accusé réception, par un nouveau POST seulement', function (): void {
    $url = AcknowledgeLink::url($this->alert, $this->contact);
    $this->post($url)->assertOk()->assertSee('97 38 88 47');

    expect($this->alert->fresh()->acknowledged_by_contact_id)->toBe($this->contact->id);

    $this->get($url)->assertOk()
        ->assertSee(__('alerts.acknowledge.reveal'))
        ->assertDontSee(__('alerts.acknowledge.confirm'))
        ->assertDontSee('97 38 88 47');
    $this->post($url)->assertOk()->assertSee('href="tel:+2290197388847"', false);

    $national = DirectoryContact::factory()->focalNational()->create();
    AlertEscalation::factory()->create(['alert_id' => $this->alert->id, 'from_contact_id' => $this->contact->id, 'to_contact_id' => $national->id]);
    $this->get(AcknowledgeLink::url($this->alert, $national))->assertOk()->assertDontSee(__('alerts.acknowledge.reveal'));
    $this->post(AcknowledgeLink::url($this->alert, $national))->assertOk()->assertDontSee('97 38 88 47');
});

it('affiche l’e-mail en lien mailto et l’absence de contact', function (): void {
    $byMail = Alert::factory()->routedTo($this->contact)->withContact('nom@exemple.bj')->create();
    $silent = Alert::factory()->routedTo($this->contact)->create();

    $this->post(AcknowledgeLink::url($byMail, $this->contact))->assertOk()->assertSee('href="mailto:nom@exemple.bj"', false)->assertDontSee('google.com/maps', false);
    $this->post(AcknowledgeLink::url($silent, $this->contact))->assertOk()->assertSee(__('alerts.acknowledge.no_contact'));
});
