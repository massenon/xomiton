<?php

declare(strict_types=1);

use App\Domain\Alerts\Events\SosTriggered;
use App\Models\Alert;
use App\Models\User;
use App\Models\Zone;
use App\Notifications\SosTriggered as SosTriggeredNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

/*
 * SMS du contact de confiance (NOTIFICATIONS §2, P5-03) : personne sans compte jointe par Notification::route('sms'),
 * texte dédié (qui demande de l'aide, consigne d'appel), jamais le lien d'administration ni l'accusé de réception.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Notification::fake();
});

/**
 * SMS reçu par le contact de confiance pour l'alerte, ou null s'il n'est pas parti.
 */
function trustedContactSms(Alert $alert): ?string
{
    $text = null;

    Notification::assertSentOnDemand(SosTriggeredNotification::class, function (SosTriggeredNotification $n, array $channels, AnonymousNotifiable $notifiable) use ($alert, &$text): bool {
        if ($n->alertId !== $alert->id || $channels !== ['sms'] || $notifiable->routes['sms'] !== '+229 01 97 38 88 47') {
            return false;
        }

        $text = $n->toSms($notifiable);

        return true;
    });

    return $text;
}

it('envoie au contact de confiance un SMS dédié : qui demande de l’aide, consigne d’appel, sans lien admin', function (): void {
    $member = User::factory()->withProfile(['trusted_contact_phone' => '+229 01 97 38 88 47'])->create(['pseudo' => 'Awa_S']);
    $zone = Zone::factory()->create(['name' => 'Littoral', 'slug' => 'littoral']);
    $alert = Alert::factory()->sos()->fromMember($member)->located()->create(['zone_id' => $zone->id]);

    SosTriggered::dispatch((int) $alert->id, $member->id, $zone->id, null, true, null, new DateTimeImmutable);

    $sms = (string) trustedContactSms($alert);

    expect($sms)->toStartWith('Xomiton – SOS de Awa_S')
        ->toContain('contact de confiance', 'appelez-la', (string) ($alert->tracking_code ?? $alert->ulid))
        ->not->toContain('/admin', 'http', 'Accusez réception')
        ->and(mb_strlen($sms))->toBeLessThanOrEqual(160);
});

it('ne nomme pas la personne si l’alerte est anonyme et indique la zone sans position', function (): void {
    $member = User::factory()->withProfile(['trusted_contact_phone' => '+229 01 97 38 88 47'])->create(['pseudo' => 'Awa_S']);
    $zone = Zone::factory()->create(['name' => 'Littoral', 'slug' => 'littoral']);
    $alert = Alert::factory()->sos()->fromMember($member)->create(['zone_id' => $zone->id, 'is_anonymous' => true, 'pseudo' => null]);

    SosTriggered::dispatch((int) $alert->id, $member->id, $zone->id, null, false, null, new DateTimeImmutable);

    expect((string) trustedContactSms($alert))->toContain('(Littoral)', 'contact de confiance', 'Appelez-la')
        ->not->toContain('Awa_S', '/admin');
});

it('garde le SMS avec lien d’accusé de réception pour un compte d’intervenant·e', function (): void {
    $responder = User::factory()->withProfile()->create();

    expect((string) (new SosTriggeredNotification(1, 'CODE', 'Cotonou', 'Littoral', 'Awa_S'))->toSms($responder))
        ->toContain('Accusez réception', '/admin/alertes');
});
