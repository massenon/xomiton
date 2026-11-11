<?php

declare(strict_types=1);

use App\Enums\ConsentKind;
use App\Enums\ProfileVisibility;
use App\Models\Consent;
use App\Models\Post;
use App\Models\UserBlock;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FeedFixtures as F;

/*
 * Écrans du profil (P8-07, P8-08, lot profil ; prototype my-profile*.php, partials/profile-head.php) : page Inertia
 * `Member/Profile` pour /profil et /membres/{pseudo} (noindex), six onglets du prototype (Activité : son profil
 * seulement), 404 si privé ou bloqué, bandeau du profil progressif, nom réel et champ sensible jamais servis à une
 * autre personne sans choix explicite (RG-02).
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->awa = F::member();
    $this->bea = F::member();
});

it('sert /profil : six onglets avec le compteur de publications, menu, colonne et bandeau progressif', function (): void {
    Post::factory()->count(3)->create(['user_id' => $this->awa->id]);
    $this->awa->profile->forceFill(['pronouns' => null, 'progressive_answered' => null])->save();

    $this->actingAs($this->awa)->get('/profil')
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Member/Profile')
            ->where('title', 'Mon profil')
            ->where('tab', 'posts')
            ->where('tabs.0', ['key' => 'posts', 'href' => route('profil'), 'count' => 3])
            ->where('tabs.5.key', 'activity')
            ->has('tabs', 6)
            ->where('profile.is_self', true)
            ->has('profile.completion')
            ->has('panel.data', 3)
            ->where('links.settings', route('parametres'))
            ->where('progressive.state.next', 'pronouns')
            ->has('progressive.options.zones')
            ->has('composer'));
});

it('ouvre chaque onglet par son adresse et le journal d’activité sur son seul profil', function (string $slug, string $tab): void {
    $this->actingAs($this->awa)->get('/profil/'.$slug)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Member/Profile')->where('tab', $tab));
})->with([
    ['a-propos', 'about'],
    ['connexions', 'connections'],
    ['medias', 'media'],
    ['evenements', 'events'],
    ['activite', 'activity'],
]);

it('sert /membres/{pseudo} sans onglet Activité, sans nom réel ni champ sensible non partagé', function (): void {
    $this->bea->profile->forceFill(['gender_identity' => 'femme_cis', 'orientation' => 'lesbienne', 'shared_fields' => null])->save();
    Consent::query()->create(['user_id' => $this->bea->id, 'kind' => ConsentKind::SensitiveData, 'version' => '1', 'granted' => true]);

    $response = $this->actingAs($this->awa)->get('/membres/'.$this->bea->pseudo.'/a-propos')
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Member/Profile')
            ->where('title', 'Profil de '.$this->bea->pseudo)
            ->where('tab', 'about')
            ->has('tabs', 5)
            ->where('profile.sensitive', [])
            ->where('progressive', null)
            ->where('composer', null)
            ->missing('profile.completion'));

    expect($response->getContent())->not->toContain($this->bea->name)
        ->and($response->getContent())->not->toContain('lesbienne')
        ->and($response->getContent())->not->toContain('femme_cis');

    $this->actingAs($this->awa)->get('/membres/'.$this->bea->pseudo.'/activite')->assertNotFound();
});

it('répond 404 pour un profil privé ou bloqué et renvoie son propre pseudo vers /profil', function (): void {
    $this->bea->profile->forceFill(['visibility' => ProfileVisibility::Private])->save();
    $this->actingAs($this->awa)->get('/membres/'.$this->bea->pseudo)->assertNotFound();

    $cyd = F::member();
    UserBlock::query()->create(['user_id' => $this->awa->id, 'blocked_user_id' => $cyd->id]);
    $this->actingAs($this->awa)->get('/membres/'.$cyd->pseudo)->assertNotFound();
    $this->actingAs($cyd)->get('/membres/'.$this->awa->pseudo)->assertNotFound();

    $this->actingAs($this->awa)->get('/membres/'.$this->awa->pseudo.'/connexions')->assertRedirect('/profil/connexions');
});

it('n’affiche plus le bandeau quand tous les groupes du profil progressif sont traités', function (): void {
    $this->awa->profile->forceFill(['progressive_answered' => ['pronouns', 'member_type', 'ally_status', 'gender_identity', 'orientation', 'age', 'location', 'interests']])->save();

    $this->actingAs($this->awa)->get('/profil')
        ->assertInertia(fn (Assert $page) => $page->where('progressive', null));
});

it('exige une session pour /profil', function (): void {
    $this->get('/profil')->assertRedirect('/connexion');
});
