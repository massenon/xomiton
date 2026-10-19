<?php

declare(strict_types=1);

use App\Application\Community\Network\InvitationNotifier;
use App\Enums\InvitationStatus;
use App\Enums\ProfileVisibility;
use App\Models\DatabaseNotification;
use App\Models\Follow;
use App\Models\Invitation;
use App\Models\User;
use App\Models\UserBlock;
use App\Models\Zone;
use App\Notifications\InvitationAccepted;
use App\Notifications\InvitationReceived;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Testing\TestResponse;
use Tests\Support\FeedFixtures as F;

/*
 * P8-06, docs/API.md §17 : suggestions « Qui suivre » (pseudonyme, zone affichée, rôle public, aucune donnée privée),
 * suivre / ne plus suivre, invitations reçues et envoyées (compteurs), inviter sans doublon, accepter (connexion dans
 * les deux sens, InvitationAccepted), refuser (aucune notification, délai avant un nouvel envoi), annuler, cycle
 * inviter → annuler → réinviter sans doublon ni renotification, blocages respectés dans les deux sens, pagination par
 * curseur sans trou après un geste, comptes « non trouvables » hors d'atteinte d'un identifiant, limiteurs.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->zone = Zone::factory()->create(['name' => 'Zou']);
    $this->otherZone = Zone::factory()->create(['name' => 'Borgou']);
    $this->awa = F::member();
    $this->awa->forceFill(['zone_id' => $this->zone->id])->save();
});

function networkMember(array $profile = [], string $role = 'membre', array $user = []): User
{
    $member = User::factory()->withProfile($profile)->create($user);
    $member->assignRole($role);

    return $member;
}

function invite(User $from, User $to): TestResponse
{
    return F::as($from)->postJson('/api/v1/invitations', ['user_id' => $to->id]);
}

function suggestionIds(TestResponse $response): array
{
    return array_column($response->json('data'), 'id');
}

function receivedNotifications(User $user): int
{
    return DatabaseNotification::query()->where('notifiable_id', $user->id)->where('type', InvitationReceived::class)->count();
}

describe('suggestions', function (): void {
    it('propose les membres découvrables, sans donnée privée, même zone d’abord', function (): void {
        $far = networkMember(user: ['pseudo' => 'aaa-far', 'zone_id' => $this->otherZone->id]);
        $near = networkMember(user: ['pseudo' => 'zzz-near', 'zone_id' => $this->zone->id]);
        $hidden = networkMember(['show_zone' => false], user: ['pseudo' => 'bbb-hidden', 'zone_id' => $this->zone->id]);

        $response = F::as($this->awa)->getJson('/api/v1/follows/suggestions')->assertOk()
            ->assertJsonCount(3, 'data')->assertJsonPath('meta.next_cursor', null)->assertJsonPath('meta.per_page', 12);

        expect(suggestionIds($response))->toBe([$near->id, $far->id, $hidden->id])
            ->and($response->json('data.0'))->toMatchArray([
                'id' => $near->id, 'pseudo' => 'zzz-near', 'role' => 'member', 'role_label' => 'Membre',
                'zone' => ['id' => $this->zone->id, 'name' => 'Zou'], 'following' => false,
                'can' => ['follow' => true, 'invite' => true],
            ])
            ->and($response->json('data.2.zone'))->toBeNull();

        $body = (string) $response->getContent();
        foreach ([$near, $far, $hidden] as $person) {
            expect($body)->not->toContain($person->name)->not->toContain($person->email);
        }
        expect(array_keys($response->json('data.0')))->toBe(['id', 'pseudo', 'avatar_url', 'role', 'role_label', 'zone', 'profile_url', 'following', 'can']);
    });

    it('écarte soi-même, superadmin, comptes inactifs, profils fermés ou introuvables, et les blocages dans les deux sens', function (): void {
        $visible = networkMember();
        networkMember(role: 'superadmin');
        networkMember(user: ['status' => 'suspendu']);
        networkMember(user: ['status' => 'en_attente']);
        networkMember(['visibility' => ProfileVisibility::Followers]);
        networkMember(['visibility' => ProfileVisibility::Private]);
        networkMember(['searchable' => false]);
        User::factory()->withProfile()->create(); // sans rôle : pas de social.access
        $blockedByMe = networkMember();
        $blocksMe = networkMember();
        UserBlock::query()->create(['user_id' => $this->awa->id, 'blocked_user_id' => $blockedByMe->id]);
        UserBlock::query()->create(['user_id' => $blocksMe->id, 'blocked_user_id' => $this->awa->id]);

        expect(suggestionIds(F::as($this->awa)->getJson('/api/v1/follows/suggestions')->assertOk()))->toBe([$visible->id]);
    });

    it('écarte les personnes suivies, invitées, qui m’ont invitée ou qui ont refusé récemment', function (): void {
        $followed = networkMember();
        $invited = networkMember();
        $inviter = networkMember();
        $refusedRecently = networkMember();
        $refusedLongAgo = networkMember();
        $free = networkMember();
        F::follow($this->awa, $followed);
        Invitation::factory()->create(['from_user_id' => $this->awa->id, 'to_user_id' => $invited->id]);
        Invitation::factory()->create(['from_user_id' => $inviter->id, 'to_user_id' => $this->awa->id]);
        Invitation::factory()->refused()->create(['from_user_id' => $this->awa->id, 'to_user_id' => $refusedRecently->id, 'responded_at' => now()->subDays(2)]);
        Invitation::factory()->refused()->create(['from_user_id' => $this->awa->id, 'to_user_id' => $refusedLongAgo->id, 'responded_at' => now()->subDays(40)]);

        $ids = suggestionIds(F::as($this->awa)->getJson('/api/v1/follows/suggestions')->assertOk());

        expect($ids)->toEqualCanonicalizing([$refusedLongAgo->id, $free->id]);
    });

    it('filtre par pseudonyme (jamais le nom réel), zone affichée et rôle public', function (): void {
        $mariam = networkMember(user: ['pseudo' => 'mariam-bj', 'name' => 'Clarisse Secret', 'zone_id' => $this->zone->id]);
        $peer = networkMember(role: 'pair', user: ['pseudo' => 'pair-zou', 'zone_id' => $this->zone->id]);
        $team = networkMember(role: 'admin', user: ['pseudo' => 'equipe-xm', 'zone_id' => $this->otherZone->id]);
        $masked = networkMember(['show_zone' => false], user: ['pseudo' => 'masquee', 'zone_id' => $this->zone->id]);

        expect(suggestionIds(F::as($this->awa)->getJson('/api/v1/follows/suggestions?q=riam')))->toBe([$mariam->id])
            ->and(suggestionIds(F::as($this->awa)->getJson('/api/v1/follows/suggestions?q=Clarisse')))->toBe([])
            ->and(suggestionIds(F::as($this->awa)->getJson('/api/v1/follows/suggestions?zone='.$this->zone->id)))->toEqualCanonicalizing([$mariam->id, $peer->id])
            ->and(suggestionIds(F::as($this->awa)->getJson('/api/v1/follows/suggestions?role=peer')))->toBe([$peer->id])
            ->and(suggestionIds(F::as($this->awa)->getJson('/api/v1/follows/suggestions?role=team')))->toBe([$team->id])
            ->and(suggestionIds(F::as($this->awa)->getJson('/api/v1/follows/suggestions?role=member')))->toEqualCanonicalizing([$mariam->id, $masked->id]);

        $team->refresh();
        expect(F::as($this->awa)->getJson('/api/v1/follows/suggestions?role=team')->json('data.0'))
            ->toMatchArray(['role' => 'team', 'role_label' => 'Équipe Xomiton']);
        F::as($this->awa)->getJson('/api/v1/follows/suggestions?role=admin')->assertUnprocessable()->assertJsonValidationErrors('role');
        F::as($this->awa)->getJson('/api/v1/follows/suggestions?zone=99999')->assertUnprocessable()->assertJsonValidationErrors('zone');
    });

    it('déduit le rôle public de tous les rôles : une pair validée (membre + pair) porte le badge pair', function (): void {
        $peer = networkMember(user: ['pseudo' => 'aaa-pair']);
        $peer->assignRole('pair');
        $team = networkMember(user: ['pseudo' => 'bbb-equipe']);
        $team->assignRole('admin');

        $response = F::as($this->awa)->getJson('/api/v1/follows/suggestions')->assertOk();

        expect($response->json('data.0'))->toMatchArray(['id' => $peer->id, 'role' => 'peer', 'role_label' => 'Pair animateur·rice'])
            ->and($response->json('data.1'))->toMatchArray(['id' => $team->id, 'role' => 'team'])
            ->and(suggestionIds(F::as($this->awa)->getJson('/api/v1/follows/suggestions?role=peer')))->toBe([$peer->id])
            ->and(suggestionIds(F::as($this->awa)->getJson('/api/v1/follows/suggestions?role=member')))->toBe([]);
    });

    it('pagine par curseur : inviter ou suivre sur la page 1 ne fait sauter personne de la page 2', function (): void {
        $people = collect(['p-a', 'p-b', 'p-c', 'p-d', 'p-e'])->map(fn (string $pseudo): User => networkMember(user: ['pseudo' => $pseudo]));

        $first = F::as($this->awa)->getJson('/api/v1/follows/suggestions?per_page=2')->assertOk();
        expect(suggestionIds($first))->toBe([$people[0]->id, $people[1]->id]);
        invite($this->awa, $people[0])->assertCreated();
        F::as($this->awa)->postJson('/api/v1/follows', ['user_id' => $people[1]->id])->assertOk();

        $second = F::as($this->awa)->getJson('/api/v1/follows/suggestions?per_page=2&cursor='.urlencode((string) $first->json('meta.next_cursor')))->assertOk();
        expect(suggestionIds($second))->toBe([$people[2]->id, $people[3]->id]);
        $third = F::as($this->awa)->getJson('/api/v1/follows/suggestions?per_page=2&cursor='.urlencode((string) $second->json('meta.next_cursor')))->assertOk();
        expect(suggestionIds($third))->toBe([$people[4]->id])->and($third->json('meta.next_cursor'))->toBeNull();

        expect(suggestionIds(F::as($this->awa)->getJson('/api/v1/follows/suggestions?per_page=2&cursor=illisible')->assertOk()))->toBe([$people[2]->id, $people[3]->id]);
    });

    it('garde « même zone d’abord » d’une page à l’autre', function (): void {
        $far = networkMember(user: ['pseudo' => 'aaa-far', 'zone_id' => $this->otherZone->id]);
        $near = collect(['y-near', 'z-near'])->map(fn (string $pseudo): User => networkMember(user: ['pseudo' => $pseudo, 'zone_id' => $this->zone->id]));

        $first = F::as($this->awa)->getJson('/api/v1/follows/suggestions?per_page=2')->assertOk();
        $second = F::as($this->awa)->getJson('/api/v1/follows/suggestions?per_page=2&cursor='.urlencode((string) $first->json('meta.next_cursor')))->assertOk();

        expect(suggestionIds($first))->toBe([$near[0]->id, $near[1]->id])->and(suggestionIds($second))->toBe([$far->id]);
    });

    it('refuse les suggestions sans accès au réseau (social.access)', function (): void {
        F::as(User::factory()->create())->getJson('/api/v1/follows/suggestions')->assertForbidden();
        F::as(User::factory()->create())->getJson('/api/v1/invitations')->assertForbidden();
    });
});

describe('suivre', function (): void {
    it('suit puis ne suit plus, de façon idempotente', function (): void {
        $bea = networkMember();

        F::as($this->awa)->postJson('/api/v1/follows', ['user_id' => $bea->id])->assertOk()
            ->assertExactJson(['data' => ['user_id' => $bea->id, 'following' => true, 'changed' => true]]);
        F::as($this->awa)->postJson('/api/v1/follows', ['user_id' => $bea->id])->assertOk()->assertJsonPath('data.changed', false);
        expect(Follow::query()->between($this->awa->id, $bea->id)->count())->toBe(1);

        F::as($this->awa)->deleteJson('/api/v1/follows/'.$bea->id)->assertOk()
            ->assertExactJson(['data' => ['user_id' => $bea->id, 'following' => false, 'changed' => true]]);
        F::as($this->awa)->deleteJson('/api/v1/follows/'.$bea->id)->assertOk()->assertJsonPath('data.changed', false);
        expect(Follow::query()->between($this->awa->id, $bea->id)->exists())->toBeFalse();
    });

    it('répond la même chose pour un compte inconnu, bloqué, réservé à ses connexions ou soi-même', function (): void {
        $connections = networkMember(['visibility' => ProfileVisibility::Followers]);
        $private = networkMember(['visibility' => ProfileVisibility::Private]);
        $blocksMe = networkMember();
        UserBlock::query()->create(['user_id' => $blocksMe->id, 'blocked_user_id' => $this->awa->id]);

        foreach ([$connections->id, $private->id, $blocksMe->id, $this->awa->id, 999999] as $id) {
            F::as($this->awa)->postJson('/api/v1/follows', ['user_id' => $id])->assertUnprocessable()
                ->assertJsonPath('code', 'follow_unavailable')
                ->assertJsonPath('message', __('community.follow.errors.follow_unavailable'));
        }
        expect(Follow::query()->count())->toBe(0);
    });

    it('laisse ne plus suivre après un blocage', function (): void {
        $bea = networkMember();
        F::follow($this->awa, $bea);
        UserBlock::query()->create(['user_id' => $bea->id, 'blocked_user_id' => $this->awa->id]);

        F::as($this->awa)->deleteJson('/api/v1/follows/'.$bea->id)->assertOk()->assertJsonPath('data.changed', true);
    });
});

describe('inviter', function (): void {
    it('envoie une invitation (201), la notifie et la compte, sans doublon au rejeu', function (): void {
        $bea = networkMember();

        invite($this->awa, $bea)->assertCreated()
            ->assertJsonPath('data.status', 'en_attente')
            ->assertJsonPath('data.direction', 'sent')
            ->assertJsonPath('data.person.id', $bea->id)
            ->assertJsonPath('data.person.pseudo', $bea->pseudo)
            ->assertJsonPath('data.can', ['accept' => false, 'refuse' => false, 'cancel' => true])
            ->assertJsonPath('meta.created', true)
            ->assertJsonPath('meta.counts', ['received' => 0, 'sent' => 1]);

        invite($this->awa, $bea)->assertOk()->assertJsonPath('meta.created', false);

        expect(Invitation::query()->count())->toBe(1)->and(receivedNotifications($bea))->toBe(1);
        $notification = DatabaseNotification::query()->where('notifiable_id', $bea->id)->firstOrFail();
        expect($notification->data)->toMatchArray([
            'title' => 'Nouvelle invitation',
            'body' => $this->awa->pseudo.' t’a envoyé une invitation.',
            'url' => url('/qui-suivre?tab=recues'),
            'invitation_id' => Invitation::query()->value('id'),
            'user_id' => $this->awa->id,
        ]);
        expect((string) json_encode($notification->data))->not->toContain($this->awa->name);
    });

    it('répond la même chose pour un compte inconnu, bloqué, privé, inactif ou soi-même', function (): void {
        $private = networkMember(['visibility' => ProfileVisibility::Private]);
        $suspended = networkMember(user: ['status' => 'suspendu']);
        $blockedByMe = networkMember();
        UserBlock::query()->create(['user_id' => $this->awa->id, 'blocked_user_id' => $blockedByMe->id]);

        foreach ([$private->id, $suspended->id, $blockedByMe->id, $this->awa->id, 999999] as $id) {
            F::as($this->awa)->postJson('/api/v1/invitations', ['user_id' => $id])->assertUnprocessable()
                ->assertJsonPath('code', 'invitation_unavailable')
                ->assertJsonPath('message', __('community.follow.errors.unavailable'));
        }
        expect(Invitation::query()->count())->toBe(0)->and(DatabaseNotification::query()->count())->toBe(0);
    });

    it('ne laisse pas un identifiant atteindre un compte « non trouvable » ou superadmin sans lien', function (): void {
        $hidden = networkMember(['searchable' => false]);
        $superadmin = networkMember(role: 'superadmin');
        $followsMe = networkMember(['searchable' => false]);
        F::follow($followsMe, $this->awa);

        foreach ([$hidden, $superadmin] as $person) {
            invite($this->awa, $person)->assertUnprocessable()->assertJsonPath('code', 'invitation_unavailable');
        }
        invite($this->awa, $followsMe)->assertCreated();
        expect(Invitation::query()->count())->toBe(1);
    });

    it('permet d’inviter un profil « connexions » qu’on suit déjà, pas un qu’on ne suit pas', function (): void {
        $connections = networkMember(['visibility' => ProfileVisibility::Followers]);

        invite($this->awa, $connections)->assertUnprocessable()->assertJsonPath('code', 'invitation_unavailable');
        F::follow($this->awa, $connections);
        invite($this->awa, $connections)->assertCreated();
    });

    it('renvoie vers « Invitations reçues » quand l’autre personne a déjà invité', function (): void {
        $bea = networkMember();
        invite($bea, $this->awa)->assertCreated();

        invite($this->awa, $bea)->assertUnprocessable()->assertJsonPath('code', 'invitation_from_them');
    });

    it('plafonne les invitations par jour (429, network_invite) et les gestes par minute (429, community_write)', function (): void {
        config(['xomiton.rate_limits.network_invites_per_day' => 2]);
        [$a, $b, $c] = [networkMember(), networkMember(), networkMember()];

        invite($this->awa, $a)->assertCreated();
        invite($this->awa, $b)->assertCreated();
        invite($this->awa, $c)->assertTooManyRequests();
        expect(Invitation::query()->count())->toBe(2);

        config(['xomiton.rate_limits.community_write_per_minute' => 2]);
        $bea = F::member();
        F::as($bea)->postJson('/api/v1/follows', ['user_id' => $a->id])->assertOk();
        F::as($bea)->deleteJson('/api/v1/follows/'.$a->id)->assertOk();
        F::as($bea)->postJson('/api/v1/follows', ['user_id' => $a->id])->assertTooManyRequests();
    });

    it('exige un e-mail confirmé pour inviter', function (): void {
        $unverified = F::member(verified: false);

        invite($unverified, networkMember())->assertForbidden();
    });

    it('cycle inviter → annuler → réinviter sans doublon ni nouvelle notification dans le délai', function (): void {
        $bea = networkMember();
        $first = invite($this->awa, $bea)->assertCreated()->json('data.id');

        F::as($this->awa)->deleteJson('/api/v1/invitations/'.$first)->assertNoContent();
        expect(Invitation::query()->count())->toBe(0)
            ->and(DatabaseNotification::query()->where('notifiable_id', $bea->id)->whereNull('read_at')->count())->toBe(0);
        F::as($bea)->getJson('/api/v1/invitations')->assertOk()->assertJsonPath('meta.counts.received', 0);

        invite($this->awa, $bea)->assertCreated()->assertJsonPath('meta.counts.sent', 1);
        expect(Invitation::query()->count())->toBe(1)->and(receivedNotifications($bea))->toBe(1);

        $this->travel(25)->hours();
        F::as($this->awa)->deleteJson('/api/v1/invitations/'.Invitation::query()->value('id'))->assertNoContent();
        invite($this->awa, $bea)->assertCreated();
        expect(Invitation::query()->count())->toBe(1)->and(receivedNotifications($bea))->toBe(2);
    });
});

describe('répondre', function (): void {
    it('accepte : connexion dans les deux sens, InvitationAccepted livrée, cloche soldée, réponse idempotente', function (): void {
        $bea = networkMember();
        $id = invite($bea, $this->awa)->json('data.id');

        F::as($this->awa)->getJson('/api/v1/invitations?box=received')->assertOk()
            ->assertJsonPath('meta.counts', ['received' => 1, 'sent' => 0])
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonPath('data.0.direction', 'received')
            ->assertJsonPath('data.0.person.pseudo', $bea->pseudo)
            ->assertJsonPath('data.0.can', ['accept' => true, 'refuse' => true, 'cancel' => false]);

        F::as($this->awa)->postJson("/api/v1/invitations/{$id}/accept")->assertOk()
            ->assertJsonPath('data.status', 'acceptee')
            ->assertJsonPath('meta.counts', ['received' => 0, 'sent' => 0]);
        F::as($this->awa)->postJson("/api/v1/invitations/{$id}/accept")->assertOk();

        expect(Follow::query()->between($bea->id, $this->awa->id)->exists())->toBeTrue()
            ->and(Follow::query()->between($this->awa->id, $bea->id)->exists())->toBeTrue()
            ->and(DatabaseNotification::query()->where('notifiable_id', $this->awa->id)->whereNull('read_at')->count())->toBe(0);

        $accepted = DatabaseNotification::query()->where('notifiable_id', $bea->id)->where('type', InvitationAccepted::class)->get();
        expect($accepted)->toHaveCount(1)
            ->and($accepted->first()->data)->toMatchArray(['body' => $this->awa->pseudo.' a accepté ton invitation. Vous êtes maintenant connecté·es.', 'user_id' => $this->awa->id]);

        invite($bea, $this->awa)->assertUnprocessable()->assertJsonPath('code', 'already_connected');
        F::as($this->awa)->postJson("/api/v1/invitations/{$id}/refuse")->assertUnprocessable()->assertJsonPath('code', 'invitation_not_pending');
    });

    it('accepte pendant une course d’invitations croisées : l’invitation inverse est close et sa cloche soldée', function (): void {
        $bea = networkMember();
        $id = invite($bea, $this->awa)->assertCreated()->json('data.id');
        $inverse = Invitation::factory()->create(['from_user_id' => $this->awa->id, 'to_user_id' => $bea->id]);
        app(InvitationNotifier::class)->invited($inverse, $this->awa, $bea);
        expect(DatabaseNotification::query()->where('notifiable_id', $bea->id)->whereNull('read_at')->count())->toBe(1);

        F::as($this->awa)->postJson("/api/v1/invitations/{$id}/accept")->assertOk()->assertJsonPath('meta.counts', ['received' => 0, 'sent' => 0]);

        expect($inverse->refresh()->status)->toBe(InvitationStatus::Accepted)
            ->and(DatabaseNotification::query()->where('notifiable_id', $bea->id)->where('type', InvitationReceived::class)->whereNull('read_at')->count())->toBe(0)
            ->and(DatabaseNotification::query()->where('notifiable_id', $bea->id)->where('type', InvitationAccepted::class)->count())->toBe(1);
        F::as($bea)->getJson('/api/v1/invitations?box=received')->assertOk()->assertJsonCount(0, 'data');
    });

    it('pagine les invitations par curseur : répondre sur la page 1 ne fait sauter aucune invitation de la page 2', function (): void {
        $ids = collect(range(1, 5))->map(function (int $n): int {
            $this->travel(1)->minutes();

            return (int) invite(networkMember(), $this->awa)->assertCreated()->json('data.id');
        })->reverse()->values();

        $first = F::as($this->awa)->getJson('/api/v1/invitations?per_page=2')->assertOk();
        expect(array_column($first->json('data'), 'id'))->toBe([$ids[0], $ids[1]]);
        F::as($this->awa)->postJson("/api/v1/invitations/{$ids[0]}/accept")->assertOk();
        F::as($this->awa)->postJson("/api/v1/invitations/{$ids[1]}/refuse")->assertOk();

        $second = F::as($this->awa)->getJson('/api/v1/invitations?per_page=2&cursor='.urlencode((string) $first->json('meta.next_cursor')))->assertOk()
            ->assertJsonPath('meta.counts.received', 3);
        expect(array_column($second->json('data'), 'id'))->toBe([$ids[2], $ids[3]]);
        $third = F::as($this->awa)->getJson('/api/v1/invitations?per_page=2&cursor='.urlencode((string) $second->json('meta.next_cursor')))->assertOk();
        expect(array_column($third->json('data'), 'id'))->toBe([$ids[4]])->and($third->json('meta.next_cursor'))->toBeNull();
    });

    it('refuse sans notifier, puis bloque un nouvel envoi jusqu’au délai et rouvre la même ligne', function (): void {
        $bea = networkMember();
        $id = invite($bea, $this->awa)->json('data.id');

        F::as($this->awa)->postJson("/api/v1/invitations/{$id}/refuse")->assertOk()->assertJsonPath('data.status', 'refusee');
        F::as($this->awa)->postJson("/api/v1/invitations/{$id}/refuse")->assertOk();

        expect(DatabaseNotification::query()->where('notifiable_id', $bea->id)->count())->toBe(0)
            ->and(Follow::query()->count())->toBe(0);
        invite($bea, $this->awa)->assertUnprocessable()->assertJsonPath('code', 'invitation_unavailable');
        F::as($bea)->getJson('/api/v1/invitations?box=sent')->assertOk()->assertJsonCount(0, 'data');
        // Annuler une invitation refusée : 404, comme un blocage — la personne qui a invité ne distingue pas un refus (Q79).
        F::as($bea)->deleteJson("/api/v1/invitations/{$id}")->assertNotFound();

        $this->travel(31)->days();
        invite($bea, $this->awa)->assertCreated()->assertJsonPath('data.id', $id)->assertJsonPath('data.status', 'en_attente');
        expect(Invitation::query()->count())->toBe(1);
    });

    it('réserve accepter et refuser à la personne invitée, annuler à celle qui a invité ; 404 pour une autre', function (): void {
        $bea = networkMember();
        $chloe = networkMember();
        $id = invite($bea, $this->awa)->json('data.id');

        F::as($bea)->postJson("/api/v1/invitations/{$id}/accept")->assertForbidden();
        F::as($this->awa)->deleteJson("/api/v1/invitations/{$id}")->assertForbidden();
        F::as($chloe)->postJson("/api/v1/invitations/{$id}/accept")->assertNotFound();
        F::as($chloe)->deleteJson("/api/v1/invitations/{$id}")->assertNotFound();
        expect(Invitation::query()->firstOrFail()->status)->toBe(InvitationStatus::Pending);
    });

    it('fait disparaître une invitation dès qu’une des deux personnes bloque l’autre', function (): void {
        $bea = networkMember();
        $id = invite($bea, $this->awa)->json('data.id');
        UserBlock::query()->create(['user_id' => $this->awa->id, 'blocked_user_id' => $bea->id]);

        F::as($this->awa)->getJson('/api/v1/invitations')->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.counts.received', 0);
        F::as($bea)->getJson('/api/v1/invitations?box=sent')->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.counts.sent', 0);
        F::as($this->awa)->postJson("/api/v1/invitations/{$id}/accept")->assertNotFound();
        F::as($bea)->deleteJson("/api/v1/invitations/{$id}")->assertNotFound();
        expect(Follow::query()->count())->toBe(0);
    });

    it('ne notifie pas une acceptation à un compte suspendu entre-temps', function (): void {
        $bea = networkMember();
        $id = invite($bea, $this->awa)->json('data.id');
        $bea->forceFill(['status' => 'suspendu'])->save();

        F::as($this->awa)->postJson("/api/v1/invitations/{$id}/accept")->assertNotFound();
        expect(DatabaseNotification::query()->where('notifiable_id', $bea->id)->count())->toBe(0);
    });
});
