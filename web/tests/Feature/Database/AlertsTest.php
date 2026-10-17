<?php

declare(strict_types=1);

use App\Enums\AlertPriority;
use App\Enums\AlertSource;
use App\Enums\AlertStatus;
use App\Enums\AlertType;
use App\Enums\DeliveryStatus;
use App\Enums\DirectoryRole;
use App\Enums\NotificationChannel;
use App\Models\Alert;
use App\Models\AlertEscalation;
use App\Models\AlertNote;
use App\Models\AlertNotification;
use App\Models\AlertStatusChange;
use App\Models\DirectoryContact;
use App\Models\SyncDevice;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
 * Lot 6 « alerts » : une création par factory et une assertion métier par table
 * (DATABASE_DICTIONARY.md §5 et §10 `sync_devices`). Nécessite l'intégration des lots pour RefreshDatabase.
 */

describe('sync_devices', function (): void {
    it('crée un appareil avec un UUID unique et filtre les appareils actifs et silencieux', function (): void {
        $device = SyncDevice::factory()->withPending(7, 1)->create();
        SyncDevice::factory()->revoked()->create();
        SyncDevice::factory()->stale()->create();

        expect($device->device_uuid)->toHaveLength(36)
            ->and($device->is_revoked)->toBeFalse()
            ->and($device->hasConflicts())->toBeTrue()
            ->and($device->user)->toBeInstanceOf(User::class)
            ->and(SyncDevice::query()->active()->count())->toBe(2)
            ->and(SyncDevice::query()->stale()->count())->toBe(1)
            ->and(SyncDevice::query()->whereDeviceUuid($device->device_uuid)->exists())->toBeTrue();

        expect(fn () => SyncDevice::factory()->create(['device_uuid' => $device->device_uuid]))->toThrow(QueryException::class);
    });
});

describe('directory_contacts', function (): void {
    it('crée un contact avec un rôle casté et cache les notes internes', function (): void {
        $contact = DirectoryContact::factory()->role(DirectoryRole::Juriste)->withNotes('Rappeler le lundi')->create();

        expect($contact->role)->toBe(DirectoryRole::Juriste)
            ->and($contact->role->label())->toBe('Juriste')
            ->and($contact->is_active)->toBeTrue()
            ->and($contact->is_public)->toBeTrue()
            ->and($contact->is_national_focal)->toBeFalse()
            ->and($contact->isReachable())->toBeTrue()
            ->and($contact->zone)->toBeInstanceOf(Zone::class)
            ->and($contact->toArray())->not->toHaveKeys(['notes', 'user_id']);
    });

    it('expose les scopes du routage RG-04 : rôle + zone, point focal de zone, point focal national', function (): void {
        $zone = Zone::factory()->create();
        $psy = DirectoryContact::factory()->role(DirectoryRole::Psychologue)->inZone($zone)->create();
        DirectoryContact::factory()->role(DirectoryRole::Psychologue)->inZone($zone)->inactive()->create();
        DirectoryContact::factory()->focalZone($zone)->create();
        $national = DirectoryContact::factory()->focalNational()->create();
        DirectoryContact::factory()->hidden()->create();

        expect(DirectoryContact::query()->active()->withRole(DirectoryRole::Psychologue)->inZone($zone)->pluck('id')->all())->toBe([$psy->id])
            ->and(DirectoryContact::query()->active()->focal()->inZone($zone)->count())->toBe(1)
            ->and(DirectoryContact::query()->nationalFocal()->pluck('id')->all())->toBe([$national->id])
            ->and($national->zone_id)->toBeNull()
            ->and(DirectoryContact::query()->public()->count())->toBe(4);
    });

    it('supprime logiquement un contact et conserve l’alerte routée (nullOnDelete)', function (): void {
        $contact = DirectoryContact::factory()->create();
        $alert = Alert::factory()->routedTo($contact)->create();

        $contact->delete();

        expect(DirectoryContact::query()->count())->toBe(0)
            ->and(DirectoryContact::withTrashed()->count())->toBe(1)
            ->and($alert->fresh()->routed_to)->toBe($contact->id);

        $contact->forceDelete();

        expect($alert->fresh()->routed_to)->toBeNull();
    });
});

describe('alerts', function (): void {
    it('crée une alerte anonyme avec ULID, code de suivi unique et enums castés', function (): void {
        $alert = Alert::factory()->create();

        expect($alert->ulid)->toHaveLength(26)
            ->and($alert->tracking_code)->toHaveLength(10)
            ->and($alert->tracking_code)->toMatch('/^[A-HJ-NP-Z2-9]{10}$/')
            ->and($alert->is_anonymous)->toBeTrue()
            ->and($alert->authorLabel())->toBe('Anonyme')
            ->and($alert->type)->toBeIn([AlertType::Report, AlertType::Need])
            ->and($alert->status)->toBe(AlertStatus::New)
            ->and($alert->status->label())->toBe('Nouveau')
            ->and($alert->priority)->toBe(AlertPriority::Normal)
            ->and($alert->source)->toBe(AlertSource::Web)
            ->and($alert->needs)->toBeArray()->not->toBeEmpty()
            ->and(Alert::query()->whereTrackingCode(strtolower($alert->tracking_code))->exists())->toBeTrue();

        expect(fn () => Alert::factory()->create(['tracking_code' => $alert->tracking_code]))->toThrow(QueryException::class);
    });

    it('chiffre le récit, la position et le contact, et ne les sérialise pas', function (): void {
        $alert = Alert::factory()->sos()->withContact('+229 01 97 00 00 00')->create(['description' => 'Récit confidentiel']);

        $raw = DB::table('alerts')->where('id', $alert->id)->first();

        expect($raw->description)->not->toBe('Récit confidentiel')
            ->and($raw->lat)->not->toBe($alert->lat)
            ->and($raw->contact)->not->toBe('+229 01 97 00 00 00')
            ->and($alert->fresh()->description)->toBe('Récit confidentiel')
            ->and($alert->fresh()->contact)->toBe('+229 01 97 00 00 00')
            ->and($alert->coordinates())->toHaveKeys(['lat', 'lng'])
            ->and($alert->coordinates()['lat'])->toBeFloat()
            ->and($alert->toArray())->not->toHaveKeys(['lat', 'lng', 'contact', 'idempotency_key']);
    });

    it('affiche le pseudo d’un·e membre identifié·e et sélectionne les SOS à escalader (RG-05)', function (): void {
        $member = User::factory()->create();
        $identified = Alert::factory()->fromMember($member)->create();

        $late = Alert::factory()->sos()->createdMinutesAgo(6)->create();
        Alert::factory()->sos()->createdMinutesAgo(2)->create();
        Alert::factory()->sos()->createdMinutesAgo(10)->acknowledged()->create();
        Alert::factory()->sos()->createdMinutesAgo(10)->escalated()->create();
        Alert::factory()->sos()->createdMinutesAgo(10)->cancelled()->create();

        expect($identified->authorLabel())->toBe($member->pseudo)
            ->and($identified->is_anonymous)->toBeFalse()
            ->and($late->isSos())->toBeTrue()
            ->and($late->priority)->toBe(AlertPriority::Critical)
            ->and(Alert::query()->awaitingEscalation()->pluck('id')->all())->toBe([$late->id])
            ->and(Alert::query()->sos()->count())->toBe(5)
            ->and(Alert::query()->open()->count())->toBe(5);
    });

    it('impose une clé d’idempotence unique pour les alertes synchronisées hors connexion', function (): void {
        $device = SyncDevice::factory()->create();
        $alert = Alert::factory()->fromDevice($device)->create();

        expect($alert->source)->toBe(AlertSource::SyncOffline)
            ->and($alert->source->isOffline())->toBeTrue()
            ->and($alert->device->is($device))->toBeTrue()
            ->and(AlertStatus::New->canTransitionTo(AlertStatus::Acknowledged))->toBeTrue()
            ->and(AlertStatus::Closed->canTransitionTo(AlertStatus::New))->toBeFalse();

        expect(fn () => Alert::factory()->create(['idempotency_key' => $alert->idempotency_key]))->toThrow(QueryException::class);
    });
});

describe('alert_notes', function (): void {
    it('chiffre le corps de la note, n’a pas d’updated_at et empêche la suppression de l’alerte parente', function (): void {
        $note = AlertNote::factory()->create(['body' => 'Rappel effectué à 14h']);

        $raw = DB::table('alert_notes')->where('id', $note->id)->first();

        expect($raw->body)->not->toBe('Rappel effectué à 14h')
            ->and($note->fresh()->body)->toBe('Rappel effectué à 14h')
            ->and($note->created_at)->not->toBeNull()
            ->and(AlertNote::UPDATED_AT)->toBeNull()
            ->and($note->author)->toBeInstanceOf(User::class);

        expect(fn () => $note->alert->delete())->toThrow(QueryException::class);
    });
});

describe('alert_notifications', function (): void {
    it('crée une notification en attente, caste canal et statut, et retient celles à rejouer', function (): void {
        $pending = AlertNotification::factory()->forContact()->create();
        $failed = AlertNotification::factory()->failed()->create();
        AlertNotification::factory()->sent()->create();
        AlertNotification::factory()->exhausted()->create();
        $push = AlertNotification::factory()->channel(NotificationChannel::Push)->create();

        expect($pending->channel)->toBe(NotificationChannel::Sms)
            ->and($pending->status)->toBe(DeliveryStatus::Pending)
            ->and($pending->status->label())->toBe('En attente')
            ->and($pending->directoryContact)->toBeInstanceOf(DirectoryContact::class)
            ->and($failed->canRetry())->toBeTrue()
            ->and($push->recipientUserId())->toBeInt()
            ->and($pending->recipientUserId())->toBeNull()
            ->and(AlertNotification::query()->dueForRetry()->pluck('id')->sort()->values()->all())
            ->toBe(collect([$pending->id, $failed->id, $push->id])->sort()->values()->all());
    });
});

describe('alert_escalations', function (): void {
    it('journalise une escalade automatique vers le point focal national sans updated_at', function (): void {
        $escalation = AlertEscalation::factory()->create();
        AlertEscalation::factory()->manual()->fromNobody()->create();

        expect($escalation->reason)->toBe(AlertEscalation::REASON_NO_ACK)
            ->and($escalation->isAutomatic())->toBeTrue()
            ->and($escalation->toContact->is_national_focal)->toBeTrue()
            ->and($escalation->toContact->escalationsTo()->pluck('id')->all())->toBe([$escalation->id])
            ->and($escalation->fromContact->escalationsFrom()->count())->toBe(1)
            ->and($escalation->fromContact->role)->toBe(DirectoryRole::PointFocal)
            ->and($escalation->alert->isSos())->toBeTrue()
            ->and(AlertEscalation::UPDATED_AT)->toBeNull()
            ->and(AlertEscalation::query()->automatic()->count())->toBe(1)
            ->and(AlertEscalation::query()->manual()->count())->toBe(1);
    });
});

describe('alert_status_changes', function (): void {
    it('trace l’historique des statuts dans l’ordre, avec une première entrée sans statut d’origine', function (): void {
        $alert = Alert::factory()->create();
        $initial = AlertStatusChange::factory()->initial()->create(['alert_id' => $alert->id]);
        $change = AlertStatusChange::factory()->transition(AlertStatus::New, AlertStatus::InProgress)->create(['alert_id' => $alert->id]);

        expect($initial->isInitial())->toBeTrue()
            ->and($initial->from_status)->toBeNull()
            ->and($initial->to_status)->toBe(AlertStatus::New)
            ->and($change->to_status)->toBe(AlertStatus::InProgress)
            ->and($change->to_status->badge())->toBe('warning')
            ->and(AlertStatusChange::UPDATED_AT)->toBeNull()
            ->and($alert->statusChanges()->chronological()->pluck('id')->all())->toBe([$initial->id, $change->id]);
    });
});
