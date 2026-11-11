<?php

declare(strict_types=1);

use App\Enums\AlertType;
use App\Enums\ContactChannel;
use App\Enums\RoutingFallback;
use App\Models\Alert;
use App\Models\AlertAttachment;
use Illuminate\Support\Facades\Schema;

/*
 * P5-01 : compléments du modèle Alert — colonnes `occurred_on`, `preferred_channel`, `reminded_at`, table
 * `alert_attachments`, casts d'enum, sélection RG-05 par type, fenêtre de repentir, code affiché.
 */

it('ajoute les colonnes et la table du lot, sans colonne chiffrée nouvelle', function (): void {
    expect(Schema::hasColumns('alerts', ['occurred_on', 'preferred_channel', 'reminded_at']))->toBeTrue()
        ->and(Schema::hasColumns('alert_attachments', ['alert_id', 'kind', 'disk', 'path', 'mime', 'size', 'created_at']))->toBeTrue()
        ->and(Schema::hasColumn('alert_attachments', 'updated_at'))->toBeFalse()
        ->and(array_filter((new AlertAttachment)->getCasts(), static fn (string $cast): bool => $cast === 'encrypted'))->toBe([]);
});

it('caste le repli de routage, le canal préféré et la date des faits', function (): void {
    $alert = Alert::factory()->routedTo(null, RoutingFallback::FocalZone)->preferredChannel(ContactChannel::Sms)
        ->withOccurredOn(now()->subDays(2))->reminded()->create()->fresh();

    expect($alert->routing_fallback)->toBe(RoutingFallback::FocalZone)
        ->and($alert->preferred_channel)->toBe(ContactChannel::Sms)
        ->and($alert->occurred_on->toDateString())->toBe(now()->subDays(2)->toDateString())
        ->and($alert->reminded_at)->not->toBeNull()
        ->and(Alert::factory()->routedTo(null, 'role_zone')->create()->fresh()->routing_fallback)->toBe(RoutingFallback::RoleZone);
});

it('sélectionne les alertes à escalader et à rappeler selon les délais de leur type', function (): void {
    $sos = Alert::factory()->sos()->createdMinutesAgo(6)->create();
    $recentReport = Alert::factory()->ofType(AlertType::Report)->createdMinutesAgo(6)->create();
    $dayOldReport = Alert::factory()->ofType(AlertType::Report)->createdMinutesAgo(25 * 60)->create();
    $oldNeed = Alert::factory()->ofType(AlertType::Need)->createdMinutesAgo(49 * 60)->create();
    Alert::factory()->sos()->safe()->createdMinutesAgo(10)->create();
    Alert::factory()->ofType(AlertType::Need)->reminded()->createdMinutesAgo(25 * 60)->create();

    expect(Alert::query()->awaitingEscalation()->orderBy('id')->pluck('id')->all())->toBe([$sos->id, $oldNeed->id])
        ->and(Alert::query()->awaitingReminder()->orderBy('id')->pluck('id')->all())->toBe([$dayOldReport->id, $oldNeed->id])
        ->and(Alert::query()->awaitingEscalation()->pluck('id'))->not->toContain($recentReport->id);
});

it('borne le repentir d’un SOS à 60 s après sa création et affiche le code préfixé', function (): void {
    $this->freezeSecond();
    $sos = Alert::factory()->sos()->create();
    $report = Alert::factory()->create();

    expect($sos->cancelWindowEndsAt()->equalTo($sos->created_at->copy()->addSeconds(60)))->toBeTrue()
        ->and($sos->canBeCancelledAt(now()->addSeconds(60)))->toBeTrue()
        ->and($sos->canBeCancelledAt(now()->addSeconds(61)))->toBeFalse()
        ->and($report->canBeCancelledAt(now()))->toBeFalse()
        ->and($sos->displayCode())->toBe('SIG-'.$sos->tracking_code)
        ->and(Alert::query()->whereTrackingCode('n’importe quoi')->exists())->toBeFalse()
        ->and(Alert::query()->whereTrackingCode(' sig-'.strtolower($sos->tracking_code))->value('id'))->toBe($sos->id)
        ->and(Alert::query()->terminal()->count())->toBe(0)
        ->and(Alert::query()->notTerminal()->count())->toBe(2);
});
