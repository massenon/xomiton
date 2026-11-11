<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Http\Middleware\HandleIdempotencyKey;
use App\Models\Alert;
use App\Models\Event;
use App\Models\Post;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\Support\AlertFixtures;
use Tests\Support\CommunityFixtures as C;
use Tests\Support\FeedFixtures as F;

/*
 * Garde « options limitées » (décision client du 11/11/2026) : un compte `en_attente` peut LIRE, utiliser le SOS, les
 * alertes et le signalement, éditer son profil ; il ne peut PAS publier, commenter, écrire, rejoindre un groupe ou un
 * événement, ni s'inscrire à la collecte. Une seule garde (`verified`, 403 `account_pending`), jamais sur la sécurité.
 */

beforeEach(function (): void {
    $this->seed([ReferenceDataSeeder::class, RolesAndPermissionsSeeder::class]);
    Notification::fake();
    $this->pending = F::member();
    $this->pending->forceFill(['status' => UserStatus::Pending, 'email_verified_at' => null])->save();
    $this->author = F::member();
});

it('refuse à un compte en attente de publier, commenter, écrire, rejoindre un groupe ou un événement (403 account_pending)', function (): void {
    $post = Post::factory()->create();
    $group = C::group($this->author);
    $event = Event::factory()->create(['starts_at' => now()->addDay(), 'going_count' => 0]);
    $other = F::member();

    $forbidden = fn ($response) => $response->assertForbidden()
        ->assertJsonPath('code', 'account_pending')
        ->assertJsonPath('message', __('auth.pending_restricted'));

    $forbidden(F::as($this->pending)->postJson('/api/v1/posts', ['body' => 'Bonjour à toutes']));
    $forbidden(F::as($this->pending)->postJson('/api/v1/posts/'.$post->ulid.'/comments', ['body' => 'Bravo']));
    $forbidden(F::as($this->pending)->postJson('/api/v1/conversations', ['kind' => 'private', 'user_id' => $other->id]));
    $forbidden(F::as($this->pending)->postJson('/api/v1/groups/'.$group->ulid.'/join'));
    $forbidden(F::as($this->pending)->postJson('/api/v1/groups', ['name' => 'Mon cercle', 'description' => 'Entraide', 'privacy' => 'public']));
    $forbidden(F::as($this->pending)->postJson('/api/v1/events/'.$event->ulid.'/rsvp', ['going' => true]));
    $forbidden(F::as($this->pending)->postJson('/api/v1/forums/topics', ['title' => 'Une question', 'body' => 'Une question importante ici.']));

    expect($event->fresh()->going_count)->toBe(0)
        ->and($group->fresh()->members_count)->toBe(1);
});

it('refuse l’espace pair et la collecte à un compte en attente (même avec un profil pair)', function (): void {
    F::as($this->pending)->getJson('/api/v1/pair/cases')->assertForbidden();
    F::as($this->pending)->postJson('/api/v1/pair/cases', [])->assertForbidden();
});

it('garde ouverts la lecture, le profil, les paramètres et les notifications pour un compte en attente', function (): void {
    $post = Post::factory()->create();

    F::as($this->pending)->getJson('/api/v1/feed')->assertOk();
    F::as($this->pending)->getJson('/api/v1/posts/'.$post->ulid)->assertOk();
    F::as($this->pending)->getJson('/api/v1/groups')->assertOk();
    F::as($this->pending)->getJson('/api/v1/events')->assertOk();
    F::as($this->pending)->getJson('/api/v1/forums')->assertOk();
    F::as($this->pending)->getJson('/api/v1/notifications')->assertOk();
    F::as($this->pending)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.pending_validation', true);
    F::as($this->pending)->getJson('/api/v1/thematiques')->assertOk();
    F::as($this->pending)->patchJson('/api/v1/me/profile', ['pronouns' => 'iel'])->assertOk();
    F::as($this->pending)->patchJson('/api/v1/me/settings', ['theme' => 'dark'])->assertOk();
    F::as($this->pending)->postJson('/api/v1/posts/'.$post->ulid.'/like')->assertSuccessful();
});

it('ne bloque JAMAIS la sécurité : SOS, signalement, alerte et signalement d’un contenu restent ouverts à un compte en attente', function (): void {
    F::as($this->pending)
        ->withHeader(HandleIdempotencyKey::HEADER, (string) Str::uuid())
        ->postJson('/api/v1/sos', ['zone_id' => AlertFixtures::zone('Littoral')->id, 'message' => 'Aide-moi'])
        ->assertCreated();

    F::as($this->pending)
        ->withHeader(HandleIdempotencyKey::HEADER, (string) Str::uuid())
        ->postJson('/api/v1/signalements', ['type' => 'report', 'category' => 'physique', 'description' => AlertFixtures::DESCRIPTION, 'zone_id' => AlertFixtures::zone('Zou')->id])
        ->assertCreated();

    expect(Alert::query()->count())->toBe(2);

    $post = Post::factory()->create();
    F::as($this->pending)->postJson('/api/v1/posts/'.$post->ulid.'/report', ['reason' => 'harcelement'])->assertStatus(201);
});

it('laisse un compte validé (e-mail confirmé par l’équipe) retrouver toutes les options', function (): void {
    $this->pending->forceFill(['status' => UserStatus::Active, 'email_verified_at' => now()])->save();

    F::as($this->pending)->postJson('/api/v1/posts', ['body' => 'Bonjour à toutes'])->assertCreated();
    F::as($this->pending)->postJson('/api/v1/groups', ['name' => 'Mon cercle', 'description' => 'Entraide', 'privacy' => 'public'])->assertCreated();
});

it('distingue un compte actif à l’e-mail non confirmé (email_unverified) d’un compte en attente (account_pending)', function (): void {
    $this->pending->forceFill(['status' => UserStatus::Active])->save();

    F::as($this->pending)->postJson('/api/v1/posts', ['body' => 'Bonjour à toutes'])
        ->assertForbidden()->assertJsonPath('code', 'email_unverified');
});

it('applique la même garde au web : le bandeau d’attente remplace « Confirme ton e-mail » dans le gabarit public', function (): void {
    $this->actingAs($this->pending)->get('/ressources')
        ->assertOk()
        ->assertSee('Ton compte attend la validation de l’équipe')
        ->assertDontSee('Confirme ton e-mail');

    $this->pending->forceFill(['status' => UserStatus::Active])->save();
    $this->actingAs($this->pending->fresh())->get('/ressources')->assertSee('Confirme ton e-mail')->assertDontSee('Ton compte attend la validation');
});
