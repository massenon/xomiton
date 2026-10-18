<?php

declare(strict_types=1);

use App\Application\Alerts\Dispatch\NotifiedPeople;
use App\Domain\Alerts\Dispatch\NotifiedState;
use App\Domain\Alerts\Dispatch\RecipientKind;
use App\Enums\NotificationChannel;
use App\Http\Resources\Alerts\NotifiedPersonResource;
use App\Models\AlertNotification;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Blade;
use Tests\Support\SosScenario;

/*
 * P5-03 : « Personnes prévenues » (page /sos, GET sos/{id}) : une ligne par personne, ordre routé → focal → équipe →
 * confiance, état vu / SMS envoyé / en attente, libellé public, numéro seulement s'il peut être montré.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->scene = SosScenario::make();
    $alert = $this->scene->alert;
    // Scène de la maquette mobile : le point focal a vu l'alerte, l'admin est prévenu, le contact de confiance a reçu un SMS.
    AlertNotification::factory()->forAlert($alert)->toTrusted($this->scene->member)->sent()->create();
    AlertNotification::factory()->forAlert($alert)->toContact($this->scene->focal)->seen()->create();
    AlertNotification::factory()->forAlert($alert)->toUser($this->scene->admin)->sent()->create();
    AlertNotification::factory()->forAlert($alert)->toUser($this->scene->admin, NotificationChannel::Sms)->failed()->create();
});

it('liste chaque personne une fois, dans l’ordre d’appel, avec son état', function (): void {
    $people = NotifiedPeople::for($this->scene->alert);

    expect($people->pluck('key')->all())->toBe([
        'contact:'.$this->scene->focal->id,
        'user:'.$this->scene->admin->id,
        'trusted:'.$this->scene->member->id,
    ])->and($people->pluck('state')->all())->toBe([NotifiedState::Seen, NotifiedState::Sent, NotifiedState::SmsSent])
        ->and($people[0]->kind)->toBe(RecipientKind::Focal)
        ->and($people[0]->label)->toBe('Mireille TOGNIFODE')
        ->and($people[0]->role)->toBe('Point focal · Littoral')
        ->and($people[0]->phone)->toBe(SosScenario::FOCAL_PHONE)
        ->and($people[1]->label)->toBe('Équipe Xomiton')
        ->and($people[1]->role)->toBe('Admin de garde')
        ->and($people[1]->phone)->toBeNull()
        ->and($people[2]->label)->toBe('Chantal')
        ->and($people[2]->phone)->toBe(SosScenario::TRUSTED_PHONE)
        ->and(NotifiedPeople::primaryContact($people)?->key)->toBe('contact:'.$this->scene->focal->id);
});

it('cache le nom et le numéro d’une fiche non publique, et retombe sur le contact de confiance', function (): void {
    $this->scene->focal->update(['is_public' => false]);

    $people = NotifiedPeople::for($this->scene->alert);

    expect($people[0]->label)->toBe('Point focal')
        ->and($people[0]->phone)->toBeNull()
        ->and(NotifiedPeople::primaryContact($people)?->key)->toBe('trusted:'.$this->scene->member->id);
});

it('n’affiche pas une personne dont toutes les remises ont été abandonnées', function (): void {
    $gone = User::factory()->create();
    AlertNotification::factory()->forAlert($this->scene->alert)->toUser($gone)->skipped(AlertNotification::SKIP_UNAVAILABLE)->create();

    expect(NotifiedPeople::for($this->scene->alert)->pluck('key'))->not->toContain('user:'.$gone->id);
});

it('sert exactement les clés du contrat JSON, sans référence ni donnée interne', function (): void {
    $json = NotifiedPersonResource::collection(NotifiedPeople::for($this->scene->alert))->resolve();

    expect(array_keys($json[0]))->toBe(['key', 'kind', 'label', 'role', 'state', 'state_label', 'phone', 'seen_at', 'updated_at'])
        ->and($json[0]['state_label'])->toBe('A vu ton alerte')
        ->and($json[0]['seen_at'])->toMatch('/^\d{4}-\d{2}-\d{2}T/')
        ->and($json[1]['state_label'])->toBe('Prévenu·e · en attente')
        ->and(json_encode($json))->not->toContain('recipient')->not->toContain('endpoint')->not->toContain('lat');
});

it('rend le bloc « Personnes prévenues » : badge Vu, appel seulement avec un numéro, zone aria-live', function (): void {
    $html = Blade::render('<x-alerts.notified :people="$people" />', ['people' => NotifiedPeople::for($this->scene->alert)]);

    expect($html)->toContain('Personnes prévenues')
        ->toContain('aria-live="polite"')
        ->toContain('Mireille TOGNIFODE')
        ->toContain('A vu ton alerte')
        ->toContain('href="tel:+2290164404040"')
        ->toContain('href="tel:+2290197388847"')
        ->toContain('xmNotified(')
        ->and(substr_count($html, '<li class="d-flex'))->toBeGreaterThanOrEqual(3)
        ->and(substr_count($html, '>Vu</span>'))->toBeGreaterThanOrEqual(1);

    $empty = Blade::render('<x-alerts.notified :people="[]" />');
    expect($empty)->toContain(__('alerts.sos.done.received'));
});

it('passe au sondage le jeton X-Sos-Token et le gabarit d’heure de LocaleFormatter, jamais le jeton sans point d’accès', function (): void {
    $polling = Blade::render('<x-alerts.notified :people="[]" endpoint="/api/v1/sos/01J" token="jeton-proprietaire" />');
    $static = Blade::render('<x-alerts.notified :people="[]" token="jeton-proprietaire" />');

    expect($polling)->toContain('jeton-proprietaire')
        ->toContain('{H}')
        ->toContain('{mm}')
        ->and($static)->not->toContain('jeton-proprietaire');
});
