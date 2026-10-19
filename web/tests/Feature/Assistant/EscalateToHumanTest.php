<?php

declare(strict_types=1);

use App\Application\Assistant\AskAssistant;
use App\Domain\Alerts\Events\AlertCreated;
use App\Enums\AlertSource;
use App\Enums\AlertType;
use App\Enums\ContactChannel;
use App\Models\Alert;
use App\Models\AssistantConversation;
use App\Models\DirectoryContact;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\Support\AlertFixtures;

/*
 * « Parler à un humain » (P8-11, `POST assistant/escalate`) : alerte « besoin » ordinaire (CreateReport, routage RG-04
 * depuis le département du compte, AlertCreated), moyen de contact obligatoire, anonyme par défaut, contenu de la
 * conversation jamais copié, conversation enregistrée marquée et escaladée seulement si le pseudonyme est transmis,
 * `Idempotency-Key` obligatoire et rejeu sans doublon, compte connecté seulement.
 */

beforeEach(function (): void {
    $this->seed([ReferenceDataSeeder::class, RolesAndPermissionsSeeder::class]);
    Notification::fake();

    $this->zone = AlertFixtures::zone('Littoral');
    $this->focal = DirectoryContact::factory()->focalZone($this->zone)->create();
    $this->member = AlertFixtures::member();
    $this->member->forceFill(['zone_id' => $this->zone->id])->save();
});

function escalate(array $payload = [], ?string $key = null): Illuminate\Testing\TestResponse
{
    return test()->withHeaders(['Idempotency-Key' => $key ?? (string) Str::uuid()])
        ->postJson('/api/v1/assistant/escalate', ['contact' => '+229 01 97 38 88 47', ...$payload]);
}

it('crée une demande « besoin » routée par RG-04 depuis le département du compte, anonyme par défaut', function (): void {
    Event::fake([AlertCreated::class]);

    $this->actingAs($this->member);
    $response = escalate(['preferred_channel' => 'whatsapp'])
        ->assertCreated()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('data.type', 'need')
        ->assertJsonPath('data.routed.role', $this->focal->role->label());

    $alert = Alert::query()->where('tracking_code', $response->json('data.tracking_code'))->firstOrFail();

    expect($alert->type)->toBe(AlertType::Need)
        ->and($alert->user_id)->toBeNull()
        ->and($alert->is_anonymous)->toBeTrue()
        ->and($alert->routed_to)->toBe($this->focal->id)
        ->and($alert->zone_id)->toBe($this->zone->id)
        ->and($alert->needs)->toBe(['soutien_psychologique'])
        ->and($alert->contact)->toBe('+229 01 97 38 88 47')
        ->and($alert->preferred_channel)->toBe(ContactChannel::Whatsapp)
        ->and($alert->source)->toBe(AlertSource::App)
        ->and($alert->description)->toBe(__('assistant.escalation.default_message'))
        ->and($response->json('data'))->not->toHaveKeys(['contact', 'description']);

    Event::assertDispatched(AlertCreated::class, fn (AlertCreated $event): bool => $event->alertId === $alert->id);
});

it('transmet le pseudonyme sur demande, garde le message et ne copie jamais la conversation dans l’alerte', function (): void {
    $this->actingAs($this->member);
    $answer = app(AskAssistant::class)('il me frappe tous les soirs', $this->member, true);

    $response = escalate(['anonymous' => false, 'message' => '  Je voudrais parler à quelqu’un ce soir.  ', 'conversation_id' => $answer->conversationId])
        ->assertCreated();

    $alert = Alert::query()->where('tracking_code', $response->json('data.tracking_code'))->firstOrFail();
    $conversation = AssistantConversation::query()->findOrFail($answer->conversationId);

    expect($alert->user_id)->toBe($this->member->id)
        ->and($alert->description)->toBe('Je voudrais parler à quelqu’un ce soir.')
        ->and($alert->description)->not->toContain('frappe')
        ->and($conversation->escalated_at)->not->toBeNull()
        ->and($conversation->is_flagged)->toBeTrue()
        // La raison de crise posée par la base est gardée (première raison de relecture).
        ->and($conversation->flag_reason)->toBe("Mots de crise détectés\u{00A0}: frappe")
        // Contact et récit chiffrés au repos.
        ->and((string) DB::table('alerts')->where('id', $alert->id)->value('contact'))->not->toContain('97 38');
});

it('marque pour relecture une conversation enregistrée non signalée quand le pseudonyme est transmis', function (): void {
    $this->actingAs($this->member);
    $answer = app(AskAssistant::class)('Comment porter plainte ?', $this->member, true);

    escalate(['anonymous' => false, 'conversation_id' => $answer->conversationId])->assertCreated();

    $conversation = AssistantConversation::query()->findOrFail($answer->conversationId);

    expect($conversation->is_flagged)->toBeTrue()
        ->and($conversation->flag_reason)->toBe(__('assistant.escalation.flag_reason'))
        ->and($conversation->escalated_at)->not->toBeNull();
});

it('rejoue la même clé sans doublon et exige la clé, le moyen de contact et un compte', function (): void {
    $key = (string) Str::uuid();
    $this->actingAs($this->member);

    $first = escalate([], $key)->assertCreated();
    $replay = escalate([], $key)->assertCreated()->assertHeader('Idempotent-Replayed', 'true');

    expect($replay->json('data.tracking_code'))->toBe($first->json('data.tracking_code'))
        ->and(Alert::query()->where('type', AlertType::Need)->count())->toBe(1);

    $this->flushHeaders()->postJson('/api/v1/assistant/escalate', ['contact' => '+229 01 97 38 88 47'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'idempotency_key_required');

    escalate(['contact' => ''])->assertStatus(422)->assertJsonValidationErrors('contact');
    escalate(['contact' => 'pas un contact'])->assertStatus(422)->assertJsonValidationErrors('contact');
    escalate(['message' => str_repeat('a', 1001)])->assertStatus(422)->assertJsonValidationErrors('message');
    escalate(['preferred_channel' => 'pigeon'])->assertStatus(422)->assertJsonValidationErrors('preferred_channel');

    $other = AssistantConversation::factory()->create(['user_id' => User::factory()->create()->id]);
    escalate(['anonymous' => false, 'conversation_id' => $other->id])->assertNotFound();

    expect(Alert::query()->where('type', AlertType::Need)->count())->toBe(1)
        ->and($other->fresh()->escalated_at)->toBeNull();
});

it('ne relie pas la conversation enregistrée à une demande anonyme (escalated_at reste vide)', function (): void {
    $this->actingAs($this->member);
    $answer = app(AskAssistant::class)('il me frappe tous les soirs', $this->member, true);
    $other = AssistantConversation::factory()->create(['user_id' => User::factory()->create()->id]);

    $response = escalate(['conversation_id' => $answer->conversationId])->assertCreated();
    // Ignorée aussi pour la conversation d'un autre compte : ni 404 (pas d'oracle) ni marque.
    escalate(['conversation_id' => $other->id])->assertCreated();

    $alert = Alert::query()->where('tracking_code', $response->json('data.tracking_code'))->firstOrFail();
    $conversation = AssistantConversation::query()->findOrFail($answer->conversationId);

    expect($alert->user_id)->toBeNull()
        ->and($alert->is_anonymous)->toBeTrue()
        ->and($conversation->escalated_at)->toBeNull()
        // Marque de crise posée par la base à l'enregistrement, pas par la demande.
        ->and($conversation->flag_reason)->toBe("Mots de crise détectés\u{00A0}: frappe")
        ->and($other->fresh()->escalated_at)->toBeNull()
        ->and($other->fresh()->is_flagged)->toBeFalse();
});

it('refuse une demande sans compte (401)', function (): void {
    escalate()->assertUnauthorized();

    expect(Alert::query()->count())->toBe(0);
});

it('replie sur « Autre besoin » quand le besoin configuré n’existe plus', function (): void {
    config(['xomiton.assistant.escalation_need' => 'besoin-disparu']);
    $this->actingAs($this->member);

    $code = escalate()->assertCreated()->json('data.tracking_code');

    expect(Alert::query()->where('tracking_code', $code)->firstOrFail()->needs)->toBe(['autre']);
});
