<?php

declare(strict_types=1);

use App\Enums\ProfileVisibility;
use App\Models\Invitation;
use App\Models\User;
use App\Models\UserBlock;
use App\Models\Zone;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FeedFixtures as F;

/*
 * Écran Vue « Qui suivre et invitations » (P8-06 ; prototype who-to-follow.php) : page Inertia `Community/WhoToFollow`,
 * onglets et compteurs (`?tab=recues|envoyees`), suggestions filtrées sous la forme de l'API (docs/API.md §17), lecture
 * tolérante (onglet, zone, profil inconnus → valeur par défaut), `?invite=` qui prépare sans envoyer (jamais pour un
 * compte « non trouvable » ou superadmin sans lien, borné par `network_preview`), carte « Qui suivre » du fil.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->zone = Zone::factory()->create(['name' => 'Zou']);
    $this->awa = F::member();
    $this->bea = F::member();
});

it('sert /qui-suivre : suggestions, compteurs, filtres et réglages', function (): void {
    $this->bea->forceFill(['zone_id' => $this->zone->id])->save();
    Invitation::factory()->create(['from_user_id' => F::member()->id, 'to_user_id' => $this->awa->id]);

    $this->actingAs($this->awa)->get('/qui-suivre?zone='.$this->zone->id.'&role=membre&q=')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Community/WhoToFollow')
            ->where('title', 'Qui suivre et invitations')
            ->where('tab', 'suggestions')
            ->where('filters', ['q' => null, 'zone' => $this->zone->id, 'role' => 'membre'])
            ->where('roles', ['membre', 'pair', 'equipe'])
            ->where('search_max', 60)
            ->has('zones')
            ->where('counts', ['received' => 1, 'sent' => 0])
            ->has('suggestions.data', 1)
            ->where('suggestions.meta', ['next_cursor' => null, 'per_page' => 12])
            ->where('suggestions.data.0.id', $this->bea->id)
            ->where('suggestions.data.0.profile_url', route('membres.show', [$this->bea->pseudo]))
            ->where('suggestions.data.0.zone.name', 'Zou')
            ->missing('suggestions.data.0.name')
            ->missing('suggestions.data.0.email')
            ->where('invitations.data', [])
            ->where('invite', null)
            ->where('can', ['access' => true, 'invite' => true, 'follow' => true]));
});

it('sert les onglets reçues et envoyées, et revient aux suggestions pour un onglet ou un filtre inconnu', function (): void {
    Invitation::factory()->create(['from_user_id' => $this->bea->id, 'to_user_id' => $this->awa->id]);

    $this->actingAs($this->awa)->get('/qui-suivre?tab=recues')
        ->assertInertia(fn (Assert $page) => $page
            ->where('tab', 'received')
            ->has('invitations.data', 1)
            ->where('invitations.data.0.person.pseudo', $this->bea->pseudo)
            ->where('invitations.data.0.can.accept', true)
            ->where('suggestions.data', []));

    $this->actingAs($this->bea)->get('/qui-suivre?tab=envoyees')
        ->assertInertia(fn (Assert $page) => $page->where('tab', 'sent')->has('invitations.data', 1)->where('counts.sent', 1));

    $this->actingAs($this->awa)->get('/qui-suivre?tab=zz&zone=999&role=admin')
        ->assertInertia(fn (Assert $page) => $page
            ->where('tab', 'suggestions')
            ->where('filters', ['q' => null, 'zone' => null, 'role' => null]));
});

it('prépare ?invite= sans rien envoyer, et reste muet sur une personne bloquée ou privée', function (): void {
    $this->actingAs($this->awa)->get('/qui-suivre?invite='.$this->bea->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('invite.status', 'ready')
            ->where('invite.person.id', $this->bea->id)
            ->where('invite.person.pseudo', $this->bea->pseudo)
            ->where('invite.person.following', false)
            ->where('invite.person.can.invite', true));
    expect(Invitation::query()->count())->toBe(0);

    UserBlock::query()->create(['user_id' => $this->bea->id, 'blocked_user_id' => $this->awa->id]);
    $private = F::member();
    $private->profile->forceFill(['visibility' => ProfileVisibility::Private])->save();

    foreach ([$this->bea->id, $private->id, 999999] as $id) {
        $this->actingAs($this->awa)->get('/qui-suivre?invite='.$id)
            ->assertInertia(fn (Assert $page) => $page->where('invite', ['status' => 'unavailable', 'person' => null]));
    }
});

it('ne révèle pas par ?invite= un compte « non trouvable » ou superadmin, sauf lien déjà fait', function (): void {
    $hidden = F::member();
    $hidden->profile->forceFill(['searchable' => false])->save();
    $superadmin = F::member('superadmin');

    foreach ([$hidden->id, $superadmin->id] as $id) {
        $this->actingAs($this->awa)->get('/qui-suivre?invite='.$id)
            ->assertInertia(fn (Assert $page) => $page->where('invite', ['status' => 'unavailable', 'person' => null]));
    }

    F::follow($hidden, $this->awa);
    $this->actingAs($this->awa)->get('/qui-suivre?invite='.$hidden->id)
        ->assertInertia(fn (Assert $page) => $page->where('invite.status', 'ready')->where('invite.person.id', $hidden->id));
});

it('signale une invitation déjà en attente au lieu d’en préparer une autre (abonnement repris, invitation fermée)', function (): void {
    Invitation::factory()->create(['from_user_id' => $this->awa->id, 'to_user_id' => $this->bea->id]);
    F::follow($this->awa, $this->bea);

    $this->actingAs($this->awa)->get('/qui-suivre?invite='.$this->bea->id)
        ->assertInertia(fn (Assert $page) => $page
            ->where('invite.status', 'pending')
            ->where('invite.person.id', $this->bea->id)
            ->where('invite.person.following', true)
            ->where('invite.person.can.invite', false));
});

it('borne les aperçus ?invite= par minute (429, network_preview), jamais la page seule', function (): void {
    config(['xomiton.rate_limits.network_preview_per_minute' => 2]);

    $this->actingAs($this->awa)->get('/qui-suivre?invite='.$this->bea->id)->assertOk();
    $this->actingAs($this->awa)->get('/qui-suivre?invite=999999')->assertOk();
    $this->actingAs($this->awa)->get('/qui-suivre?invite='.$this->bea->id)->assertTooManyRequests();
    $this->actingAs($this->awa)->get('/qui-suivre')->assertOk();
    $this->actingAs($this->awa)->get('/qui-suivre?tab=recues')->assertOk();
});

it('sert une page vide sans accès au réseau et sans invitation possible sans e-mail confirmé', function (): void {
    $this->actingAs(User::factory()->create())->get('/qui-suivre?invite='.$this->bea->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.access', false)
            ->where('suggestions.data', [])
            ->where('counts', ['received' => 0, 'sent' => 0])
            ->where('invite', ['status' => 'unavailable', 'person' => null]));

    $this->actingAs(F::member(verified: false))->get('/qui-suivre')
        ->assertInertia(fn (Assert $page) => $page->where('can', ['access' => true, 'invite' => false, 'follow' => true]));
});

it('remplit la carte « Qui suivre » du fil (5 suggestions, rôle · zone, invitation à confirmer)', function (): void {
    $this->awa->forceFill(['zone_id' => $this->zone->id])->save();
    $this->bea->forceFill(['zone_id' => $this->zone->id])->save();
    foreach (range(1, 6) as $n) {
        F::member();
    }

    $this->actingAs($this->awa)->get('/fil')
        ->assertOk()
        ->assertDontSee($this->bea->pseudo)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Feed/Index')
            ->missing('who_to_follow')
            ->loadDeferredProps('aside', fn (Assert $reload) => $reload
                ->has('who_to_follow', 5)
                ->where('who_to_follow.0.id', $this->bea->id)
                ->where('who_to_follow.0.pseudo', $this->bea->pseudo)
                ->where('who_to_follow.0.meta', 'Membre · Zou')
                ->where('who_to_follow.0.invite_url', url('/qui-suivre?invite='.$this->bea->id))
                ->where('who_to_follow.0.href', route('membres.show', [$this->bea->pseudo]))
                ->where('who_to_follow.0.href', fn (string $href): bool => str_starts_with($href, url('/membres/')))));
});
