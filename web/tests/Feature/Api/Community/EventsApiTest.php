<?php

declare(strict_types=1);

use App\Enums\GroupPrivacy;
use App\Enums\GroupRole;
use App\Enums\MembershipStatus;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\DatabaseNotification;
use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\UserBlock;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Support\FeedFixtures as F;

/*
 * P8-04, docs/API.md §15 : événements par ULID ; **adresse exacte et lien de connexion seulement pour qui a répondu
 * « Je participe »** (simulateur.html l.1164), l'organisation et la modération ; groupe privé invisible des non-membres ;
 * « Je participe » / annuler idempotent avec compteur ; création (bornes, filtre de mots, groupe, `is_public` réservé à
 * la modération, cloche aux membres du groupe).
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->awa = F::member();
    $this->bea = F::member();
});

it('répond 200 sans adresse ni lien pour une non-participante, puis les livre après « Je participe »', function (): void {
    $event = Event::factory()->withAddress('Maison des jeunes, salle 3')->create([
        'place' => 'Cotonou', 'is_online' => true, 'online_url' => 'https://visio.example.org/atelier', 'starts_at' => now()->addDays(3),
    ]);

    F::as($this->bea)->getJson('/api/v1/events/'.$event->ulid)
        ->assertOk()
        ->assertJsonPath('data.id', $event->ulid)
        ->assertJsonPath('data.place', 'Cotonou')
        ->assertJsonPath('data.location_label', 'Cotonou · en ligne')
        ->assertJsonPath('data.exact_place_reserved', true)
        ->assertJsonPath('data.rsvp', null)
        ->assertJsonPath('data.can.rsvp', true)
        ->assertJsonMissingPath('data.address')
        ->assertJsonMissingPath('data.online_url');

    F::as($this->bea)->postJson('/api/v1/events/'.$event->ulid.'/rsvp', ['going' => true])
        ->assertOk()
        ->assertJsonPath('data.address', 'Maison des jeunes, salle 3')
        ->assertJsonPath('data.online_url', 'https://visio.example.org/atelier')
        ->assertJsonPath('data.exact_place_reserved', false)
        ->assertJsonPath('data.rsvp.status', 'going')
        ->assertJsonPath('data.rsvp.reminder', true)
        ->assertJsonPath('data.going_count', 1)
        ->assertJsonPath('data.can.cancel', true);

    // Même règle dans la liste : la participante a l'adresse, une autre membre toujours pas.
    F::as($this->bea)->getJson('/api/v1/events')->assertOk()->assertJsonPath('data.0.rsvp.status', 'going')->assertJsonPath('data.0.address', 'Maison des jeunes, salle 3');
    F::as($this->awa)->getJson('/api/v1/events')->assertOk()->assertJsonMissingPath('data.0.address');
    F::as($this->awa)->getJson('/api/v1/events/'.$event->ulid)->assertOk()->assertJsonMissingPath('data.address');

    // Organisatrice et modération voient l'adresse sans participer.
    F::as($event->organizer)->getJson('/api/v1/events/'.$event->ulid)->assertJsonPath('data.address', 'Maison des jeunes, salle 3');
    F::as(F::member('moderateur'))->getJson('/api/v1/events/'.$event->ulid)->assertJsonPath('data.address', 'Maison des jeunes, salle 3');

    // Adresse chiffrée au repos, jamais dans toArray().
    expect((string) DB::table('events')->where('id', $event->id)->value('address'))->not->toContain('Maison des jeunes')
        ->and($event->fresh()->toArray())->not->toHaveKey('address');
});

it('annule une participation (idempotent), garde ou retire le rappel, refuse un événement terminé', function (): void {
    $event = Event::factory()->create(['starts_at' => now()->addDay(), 'going_count' => 0]);
    $url = '/api/v1/events/'.$event->ulid.'/rsvp';

    F::as($this->awa)->postJson($url, ['going' => true, 'reminder' => false])->assertOk()->assertJsonPath('data.rsvp.reminder', false);
    F::as($this->awa)->postJson($url, ['going' => true, 'reminder' => false])->assertOk()->assertJsonPath('data.going_count', 1);
    F::as($this->bea)->postJson($url, ['going' => true])->assertOk()->assertJsonPath('data.going_count', 2);
    F::as($this->awa)->postJson($url, ['going' => false])->assertOk()->assertJsonPath('data.rsvp', null)->assertJsonPath('data.going_count', 1)->assertJsonMissingPath('data.address');
    F::as($this->awa)->postJson($url, ['going' => false])->assertOk()->assertJsonPath('data.going_count', 1);
    F::as($this->awa)->postJson($url, [])->assertUnprocessable()->assertJsonValidationErrors('going');

    expect(EventRsvp::query()->where('event_id', $event->id)->count())->toBe(1)
        ->and($event->fresh()->going_count)->toBe(1);

    $past = Event::factory()->past()->create();
    F::as($this->awa)->postJson('/api/v1/events/'.$past->ulid.'/rsvp', ['going' => true])
        ->assertUnprocessable()->assertJsonPath('code', 'event_finished');
    F::as($this->awa)->getJson('/api/v1/events/'.$past->ulid)->assertOk()->assertJsonPath('data.is_past', true)->assertJsonPath('data.can.rsvp', false);
});

it('cache les événements d’un groupe privé, les brouillons et les masqués (404), aux non-membres', function (): void {
    $private = Group::factory()->create(['privacy' => GroupPrivacy::Private, 'owner_id' => $this->awa->id]);
    GroupMember::query()->create(['group_id' => $private->id, 'user_id' => $this->awa->id, 'role' => GroupRole::Admin, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
    $secret = Event::factory()->forGroup($private)->create(['title' => 'Cercle privé', 'starts_at' => now()->addDays(2)]);
    $draft = Event::factory()->draft()->create(['starts_at' => now()->addDays(2)]);
    $hidden = Event::factory()->hidden()->create(['starts_at' => now()->addDays(2)]);
    $open = Event::factory()->create(['title' => 'Atelier ouvert', 'starts_at' => now()->addDays(4)]);

    F::as($this->bea)->getJson('/api/v1/events')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Atelier ouvert');
    foreach ([$secret, $draft, $hidden] as $event) {
        F::as($this->bea)->getJson('/api/v1/events/'.$event->ulid)->assertNotFound();
        F::as($this->bea)->postJson('/api/v1/events/'.$event->ulid.'/rsvp', ['going' => true])->assertNotFound();
    }

    F::as($this->awa)->getJson('/api/v1/events')->assertJsonCount(2, 'data')->assertJsonPath('data.0.title', 'Cercle privé');
    F::as($this->awa)->getJson('/api/v1/events/'.$secret->ulid)->assertOk()->assertJsonPath('data.group.name', $private->name);
    F::as($open->organizer)->getJson('/api/v1/events/'.$open->ulid)->assertOk()->assertJsonPath('data.can.update', true);
});

it('filtre : à venir, mes participations, passés, catégorie, recherche ; pagination par 6', function (): void {
    $atelier = Category::factory()->create(['kind' => Category::KIND_EVENT, 'slug' => 'atelier', 'label' => 'Atelier', 'is_active' => true]);
    Event::factory()->count(7)->sequence(fn ($s) => ['starts_at' => now()->addDays($s->index + 1), 'title' => 'Rencontre '.($s->index + 1), 'place' => 'Cotonou', 'description' => 'Échanges.'])->create();
    $mine = Event::factory()->inCategory($atelier)->create(['title' => 'Atelier « Dire non »', 'place' => 'Porto-Novo', 'starts_at' => now()->addDays(10)]);
    Event::factory()->past()->create(['title' => 'Causerie passée']);
    EventRsvp::factory()->create(['event_id' => $mine->id, 'user_id' => $this->awa->id, 'status' => 'going']);

    F::as($this->awa)->getJson('/api/v1/events?from=today')->assertOk()
        ->assertJsonCount(6, 'data')->assertJsonPath('meta.total', 8)->assertJsonPath('meta.last_page', 2)->assertJsonPath('data.0.title', 'Rencontre 1');
    F::as($this->awa)->getJson('/api/v1/events?filter=going')->assertJsonCount(1, 'data')->assertJsonPath('data.0.rsvp.status', 'going');
    F::as($this->awa)->getJson('/api/v1/events?filter=past')->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Causerie passée');
    F::as($this->awa)->getJson('/api/v1/events?category=atelier')->assertJsonCount(1, 'data')->assertJsonPath('data.0.category.slug', 'atelier');
    F::as($this->awa)->getJson('/api/v1/events?q=porto')->assertJsonCount(1, 'data');
    F::as($this->awa)->getJson('/api/v1/events?category=inconnue')->assertUnprocessable()->assertJsonValidationErrors('category');
    F::as($this->awa)->getJson('/api/v1/events?filter=tous')->assertUnprocessable()->assertJsonValidationErrors('filter');
});

it('crée un événement : 201 + Location, organisatrice, adresse chiffrée, journal ; bornes et filtre de mots', function (): void {
    $response = F::as($this->awa)->postJson('/api/v1/events', [
        'title' => 'Causerie : santé mentale',
        'starts_at' => now()->addDays(5)->format('Y-m-d\TH:i'),
        'place' => 'Parakou',
        'address' => 'Centre des femmes, porte 2',
        'description' => 'Échanges entre pairs.',
    ])->assertCreated()
        ->assertJsonPath('data.title', 'Causerie : santé mentale')
        ->assertJsonPath('data.organizer', $this->awa->pseudo)
        ->assertJsonPath('data.address', 'Centre des femmes, porte 2')
        ->assertJsonPath('data.is_public', false)
        ->assertJsonPath('data.can.update', true);

    $ulid = (string) $response->json('data.id');
    expect($response->headers->get('Location'))->toEndWith('/evenements/'.$ulid)
        ->and(Event::query()->where('ulid', $ulid)->value('organizer_id'))->toBe($this->awa->id)
        ->and(AuditLog::query()->where('action', 'event.created')->exists())->toBeTrue();
    F::as($this->bea)->getJson('/api/v1/events/'.$ulid)->assertOk()->assertJsonMissingPath('data.address');

    F::as($this->awa)->postJson('/api/v1/events', ['title' => 'ab', 'starts_at' => now()->addDay()->toIso8601String()])
        ->assertUnprocessable()->assertJsonPath('errors.title.0', 'Le titre doit contenir au moins 3 caractères.');
    F::as($this->awa)->postJson('/api/v1/events', ['title' => 'Atelier', 'starts_at' => now()->subDay()->toIso8601String()])
        ->assertUnprocessable()->assertJsonPath('errors.starts_at.0', 'Choisis une date et une heure à venir.');
    F::as($this->awa)->postJson('/api/v1/events', ['title' => 'Atelier', 'starts_at' => now()->addDay()->toIso8601String(), 'ends_at' => now()->addDays(40)->toIso8601String()])
        ->assertUnprocessable()->assertJsonPath('errors.ends_at.0', 'Un événement dure 31 jours au plus.');
    F::as($this->awa)->postJson('/api/v1/events', ['title' => 'Atelier', 'starts_at' => now()->addDay()->toIso8601String(), 'is_online' => true, 'online_url' => 'http://visio.example.org'])
        ->assertUnprocessable()->assertJsonPath('errors.online_url.0', 'Le lien doit commencer par https://.');
    F::as($this->awa)->postJson('/api/v1/events', ['title' => 'Atelier', 'starts_at' => now()->addDay()->toIso8601String(), 'is_public' => true])
        ->assertUnprocessable()->assertJsonPath('errors.is_public.0', 'Seule l’équipe de modération peut ouvrir un événement au public.');

    F::badWord('interdit');
    F::as($this->awa)->postJson('/api/v1/events', ['title' => 'Atelier interdit', 'starts_at' => now()->addDay()->toIso8601String()])
        ->assertUnprocessable()->assertJsonPath('code', 'content_blocked');

    F::as(F::member(verified: false))->postJson('/api/v1/events', ['title' => 'Atelier', 'starts_at' => now()->addDay()->toIso8601String()])->assertForbidden();
    expect(Event::query()->count())->toBe(1);
});

it('crée un événement de groupe : réservé aux membres actives, cloche aux autres membres ; public par la modération', function (): void {
    $group = Group::factory()->create(['privacy' => GroupPrivacy::Public, 'owner_id' => $this->awa->id]);
    GroupMember::query()->create(['group_id' => $group->id, 'user_id' => $this->awa->id, 'role' => GroupRole::Admin, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
    GroupMember::query()->create(['group_id' => $group->id, 'user_id' => $this->bea->id, 'role' => GroupRole::Member, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
    $outsider = F::member();
    $blocking = F::member();
    GroupMember::query()->create(['group_id' => $group->id, 'user_id' => $blocking->id, 'role' => GroupRole::Member, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
    UserBlock::factory()->create(['user_id' => $blocking->id, 'blocked_user_id' => $this->awa->id]);

    F::as($outsider)->postJson('/api/v1/events', ['title' => 'Atelier du groupe', 'starts_at' => now()->addDays(2)->toIso8601String(), 'group' => $group->ulid])
        ->assertUnprocessable()->assertJsonValidationErrors('group');

    F::as($this->awa)->postJson('/api/v1/events', ['title' => 'Atelier du groupe', 'starts_at' => now()->addDays(2)->toIso8601String(), 'group' => $group->ulid])
        ->assertCreated()->assertJsonPath('data.group.id', $group->ulid);

    $bell = DatabaseNotification::query()->where('notifiable_id', $this->bea->id)->where('category', DatabaseNotification::CATEGORY_COMMUNITY)->first();
    expect($bell)->not->toBeNull()
        ->and((string) $bell->data['url'])->toContain('/evenements/')
        ->and(DatabaseNotification::query()->where('notifiable_id', $this->awa->id)->count())->toBe(0)
        ->and(DatabaseNotification::query()->where('notifiable_id', $blocking->id)->count())->toBe(0);

    $moderator = F::member('moderateur');
    F::as($moderator)->postJson('/api/v1/events', ['title' => 'Journée de dépistage', 'starts_at' => now()->addDays(6)->toIso8601String(), 'place' => 'Cotonou', 'is_public' => true])
        ->assertCreated()->assertJsonPath('data.is_public', true);

    // Un événement d'un groupe privé ne peut pas être ouvert au public, même par la modération.
    $private = Group::factory()->create(['privacy' => GroupPrivacy::Private, 'owner_id' => $moderator->id]);
    GroupMember::query()->create(['group_id' => $private->id, 'user_id' => $moderator->id, 'role' => GroupRole::Admin, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
    F::as($moderator)->postJson('/api/v1/events', ['title' => 'Cercle public ?', 'starts_at' => now()->addDays(6)->toIso8601String(), 'group' => $private->ulid, 'is_public' => true])
        ->assertUnprocessable()->assertJsonPath('errors.is_public.0', 'Un événement d’un groupe privé ne peut pas être public.');
});

it('exige un compte (401) et l’accès au réseau social (403)', function (): void {
    $event = Event::factory()->create();

    $this->getJson('/api/v1/events')->assertUnauthorized();
    $this->getJson('/api/v1/events/'.$event->ulid)->assertUnauthorized();

    $this->awa->syncRoles([]);
    F::as($this->awa->fresh())->getJson('/api/v1/events')->assertForbidden();
    F::as($this->awa->fresh())->getJson('/api/v1/events/'.$event->ulid)->assertNotFound();
});

it('cache l’événement (liste, fiche, « Je participe ») quand l’organisatrice et la personne se bloquent', function (): void {
    $event = Event::factory()->withAddress('Maison des jeunes, salle 3')->create(['title' => 'Atelier bloqué', 'starts_at' => now()->addDays(2)]);
    Event::factory()->create(['title' => 'Atelier ouvert', 'starts_at' => now()->addDays(3)]);
    UserBlock::factory()->create(['user_id' => $event->organizer_id, 'blocked_user_id' => $this->awa->id]);
    UserBlock::factory()->create(['user_id' => $this->bea->id, 'blocked_user_id' => $event->organizer_id]);

    foreach ([$this->awa, $this->bea] as $member) {
        F::as($member)->getJson('/api/v1/events')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Atelier ouvert');
        F::as($member)->getJson('/api/v1/events/'.$event->ulid)->assertNotFound();
        F::as($member)->postJson('/api/v1/events/'.$event->ulid.'/rsvp', ['going' => true])->assertNotFound();
    }

    expect(EventRsvp::query()->where('event_id', $event->id)->count())->toBe(0);
    F::as(F::member())->getJson('/api/v1/events')->assertJsonCount(2, 'data');
    F::as(F::member('moderateur'))->getJson('/api/v1/events/'.$event->ulid)->assertOk();
});

it('exige un e-mail confirmé pour répondre « Je participe » (403), annuler reste possible', function (): void {
    $event = Event::factory()->withAddress('Maison des jeunes, salle 3')->create(['starts_at' => now()->addDays(2)]);
    $unverified = F::member(verified: false);

    F::as($unverified)->postJson('/api/v1/events/'.$event->ulid.'/rsvp', ['going' => true])
        ->assertForbidden()->assertJsonPath('code', 'email_unverified')->assertJsonMissingPath('data.address');
    F::as($unverified)->postJson('/api/v1/events/'.$event->ulid.'/rsvp', ['going' => false])->assertOk()->assertJsonPath('data.rsvp', null);
    expect(EventRsvp::query()->count())->toBe(0);
});

it('sert les lectures et réponses en no-store (adresse et lien déchiffrés), sans ETag', function (): void {
    $event = Event::factory()->withAddress('Maison des jeunes, salle 3')->create(['starts_at' => now()->addDays(2)]);

    foreach ([
        F::as($this->awa)->postJson('/api/v1/events/'.$event->ulid.'/rsvp', ['going' => true]),
        F::as($this->awa)->getJson('/api/v1/events/'.$event->ulid),
        F::as($this->awa)->getJson('/api/v1/events'),
    ] as $response) {
        $response->assertOk();
        expect($response->headers->get('Cache-Control'))->toContain('no-store')
            ->and($response->headers->has('ETag'))->toBeFalse();
    }
});

it('chiffre le lien de connexion au repos et ne le sérialise jamais par défaut', function (): void {
    $event = Event::factory()->create(['is_online' => true, 'online_url' => 'https://visio.example.org/atelier?code=1234', 'starts_at' => now()->addDays(2)]);

    expect((string) DB::table('events')->where('id', $event->id)->value('online_url'))->not->toContain('visio.example.org')
        ->and($event->fresh()->online_url)->toBe('https://visio.example.org/atelier?code=1234')
        ->and($event->fresh()->toArray())->not->toHaveKey('online_url')
        ->and(config('xomiton.encrypted_columns.events'))->toContain('online_url');
});

it('dit si le rappel de la veille peut encore partir (reminder_available)', function (): void {
    $soon = Event::factory()->create(['starts_at' => now()->addHours(30)]);
    $sent = Event::factory()->create(['starts_at' => now()->addHours(10), 'reminder_sent_at' => now()->subHour()]);

    F::as($this->awa)->getJson('/api/v1/events/'.$soon->ulid)->assertJsonPath('data.reminder_available', true);
    F::as($this->awa)->getJson('/api/v1/events/'.$sent->ulid)->assertJsonPath('data.reminder_available', false);
});
