<?php

declare(strict_types=1);

use App\Enums\PostVisibility;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/*
 * P7-02, Q31 b : /p/{ulid}, lien de partage d'une publication — page publique neutre (titre générique, invitation à se
 * connecter, aucun contenu, noindex), identique que la publication existe ou non, sans lecture en base ; connecté·e :
 * redirection vers /fil/{ulid}.
 */

it('affiche une page neutre sans contenu, sans pseudonyme et non indexable', function (): void {
    $author = User::factory()->create(['pseudo' => 'Awa_Secret']);
    $post = Post::factory()->for($author)->visibility(PostVisibility::Followers)->create(['body' => 'Mon témoignage très personnel']);

    $response = $this->get('/p/'.$post->ulid)->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee(__('pages/partage.title'))
        ->assertSee('noindex, nofollow', false)
        ->assertSee(route('connexion', ['next' => '/fil/'.$post->ulid]), false)
        ->assertDontSee('Mon témoignage')
        ->assertDontSee('Awa_Secret');

    expect((string) $response->headers->get('Cache-Control'))->toContain('no-store');
});

it('répond la même page pour une publication inconnue, sans lire la base', function (): void {
    DB::enableQueryLog();
    $this->get('/p/01JABCDEFGHJKMNPQRSTVWXYZ0')->assertOk()->assertSee(__('pages/partage.lead'));

    $queries = collect(DB::getQueryLog())->pluck('query')->filter(fn (string $sql): bool => str_contains($sql, 'posts'));
    expect($queries)->toBeEmpty();
});

it('redirige une personne connectée vers le détail de la publication', function (): void {
    $this->actingAs(User::factory()->create())->get('/p/01JABCDEFGHJKMNPQRSTVWXYZ0')->assertRedirect('/fil/01JABCDEFGHJKMNPQRSTVWXYZ0');
});

it('refuse une adresse qui n’est pas un ULID (404)', function (): void {
    $this->get('/p/123')->assertNotFound();
});
