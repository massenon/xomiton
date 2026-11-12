<?php

declare(strict_types=1);

use App\Application\Content\ConversationsBd\ConversationContentFilter;
use App\Application\Content\ConversationsBd\ImportConversationsBd;
use App\Application\Seo\SitemapBuilder;
use App\Enums\BadWordSeverity;
use App\Enums\BdConversationStatus;
use App\Models\AuditLog;
use App\Models\BadWord;
use App\Models\BdConversation;
use App\Models\BdScene;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia;

/*
 * P20-07 : éditeur des conversations en BD des membres — pages Inertia (« Mes conversations », éditeur), API
 * /api/v1/bd-conversations (créer un brouillon privé, enregistrer, lire, supprimer), bibliothèque de scènes actives,
 * casting de la scène, grammaire côté serveur (rendue sans bloquer le brouillon, exigée à la proposition), filtres de
 * contenu (mot interdit, téléphone, e-mail, lien), droits (création d'une autre personne : 404 ; sans `social.access` :
 * 403), aucun import d'image.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    app(ImportConversationsBd::class)();
    BdScene::query()->update(['is_active' => true]);
});

function bdmMember(string $role = 'membre'): User
{
    $user = User::factory()->create();
    $user->syncRoles([$role]);

    return $user;
}

/** Cases valides : 3 scènes de m01, une parole de Fifamè par case. */
function bdmPanels(int $count = 3, string $text = 'Je n’ai pas envie de venir ce soir.'): array
{
    $panels = [];

    for ($n = 1; $n <= $count; $n++) {
        $panels[] = ['scene' => 'm01-case-'.min($n, 5), 'bubbles' => [[
            'type' => 'parole', 'personnage' => 'fifame', 'texte' => $text,
            'position' => ['x' => 4, 'y' => 4], 'queue' => ['vers' => 'personnage', 'x' => 30, 'y' => 70],
        ]]];
    }

    return $panels;
}

function bdmCreate(User $user, string $title = 'Ma soirée'): BdConversation
{
    test()->actingAs($user)->postJson('/api/v1/bd-conversations', ['title' => $title])->assertCreated();

    return BdConversation::query()->where('author_id', $user->getKey())->latest('id')->firstOrFail();
}

it('crée un brouillon privé, anonyme par défaut, à l’adresse privée par ULID', function (): void {
    $user = bdmMember();
    $response = $this->actingAs($user)->postJson('/api/v1/bd-conversations', ['title' => 'Ma soirée', 'summary' => 'Une soirée entre amies.'])->assertCreated();

    $conversation = BdConversation::query()->where('author_id', $user->getKey())->firstOrFail();
    expect($conversation->statusValue())->toBe(BdConversationStatus::Draft)
        ->and($conversation->isMemberCreation())->toBeTrue()
        ->and((bool) $conversation->is_anonymous)->toBeTrue()
        ->and($conversation->slug)->toStartWith('brouillon-');
    $response->assertJsonPath('data.status', 'brouillon')->assertJsonPath('data.editable', true)
        ->assertJsonPath('data.editor_url', route('mes-conversations-bd.edit', ['ulid' => $conversation->ulid]));
    expect(AuditLog::query()->where('action', 'conversations_bd.member_created')->exists())->toBeTrue();

    // Jamais public : la page publique et le plan du site l'ignorent.
    $this->get('/conversations-bd/'.$conversation->slug)->assertNotFound();
    expect(app(SitemapBuilder::class)->xml())->not->toContain($conversation->slug);
});

it('sert « Mes conversations » et l’éditeur en pages Inertia, sans aperçu de partage', function (): void {
    $user = bdmMember();
    $conversation = bdmCreate($user);

    $this->actingAs($user)->get('/mes-conversations-bd')->assertOk()
        ->assertHeader('X-Robots-Tag')
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Member/ConversationsBd/Index')->has('conversations', 1)
            ->where('conversations.0.status', 'brouillon'));

    $html = (string) $this->actingAs($user)->get('/mes-conversations-bd/'.$conversation->ulid)->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Member/ConversationsBd/Editor')
            ->where('conversation.ulid', $conversation->ulid)
            ->has('library.scenes')
            ->where('library.limits.words_max', 20))
        ->getContent();
    expect($html)->not->toContain('og:title')->toContain('noindex');
});

it('ne propose que les scènes actives dans la bibliothèque, avec leur casting', function (): void {
    BdScene::query()->where('key', 'm01-case-2')->update(['is_active' => false]);
    $data = $this->actingAs(bdmMember())->getJson('/api/v1/bd-conversations/library')->assertOk()->json('data');

    $keys = array_column($data['scenes'], 'key');
    expect($keys)->toContain('m01-case-1')->not->toContain('m01-case-2');
    $first = collect($data['scenes'])->firstWhere('key', 'm01-case-1');
    expect(array_column($first['characters'], 'name'))->toContain('Fifamè')
        ->and($first)->not->toHaveKey('upload');
});

it('enregistre le brouillon (cases, bulles) et rend les contrôles de la grammaire sans bloquer', function (): void {
    $user = bdmMember();
    $conversation = bdmCreate($user);

    // Une seule case : brouillon accepté, la grammaire signale le nombre de cases.
    $response = $this->actingAs($user)->putJson('/api/v1/bd-conversations/'.$conversation->ulid, ['title' => 'Ma soirée', 'panels' => bdmPanels(1)])->assertOk();
    expect(array_column($response->json('data.issues'), 'code'))->toContain('panel_count')
        ->and($response->json('data.saved_at'))->not->toBeNull();

    $this->actingAs($user)->putJson('/api/v1/bd-conversations/'.$conversation->ulid, ['title' => 'Ma soirée au lycée', 'panels' => bdmPanels(3)])->assertOk()
        ->assertJsonPath('data.issues', []);

    $conversation->refresh();
    expect($conversation->title)->toBe('Ma soirée au lycée')
        ->and($conversation->panels()->count())->toBe(3)
        ->and($conversation->panels()->first()->bubbles()->first()->text)->toBe('Je n’ai pas envie de venir ce soir.');

    $this->actingAs($user)->getJson('/api/v1/bd-conversations/'.$conversation->ulid)->assertOk()
        ->assertJsonPath('data.panels.0.scene', 'm01-case-1')
        ->assertJsonPath('data.panels.0.bubbles.0.personnage', 'fifame');
});

it('refuse un personnage absent de la scène et une scène inconnue', function (): void {
    $user = bdmMember();
    $conversation = bdmCreate($user);
    $panels = bdmPanels(3);
    $panels[1]['bubbles'][0]['personnage'] = 'joel';

    $this->actingAs($user)->putJson('/api/v1/bd-conversations/'.$conversation->ulid, ['title' => 'Ma soirée', 'panels' => $panels])
        ->assertStatus(422)->assertJsonPath('code', 'bd_invalid_input')
        ->assertJsonValidationErrors(['panels.1.bubbles.0.personnage']);

    $panels = bdmPanels(3);
    $panels[0]['scene'] = 'scene-inventee';
    $this->actingAs($user)->putJson('/api/v1/bd-conversations/'.$conversation->ulid, ['title' => 'Ma soirée', 'panels' => $panels])
        ->assertStatus(422)->assertJsonValidationErrors(['panels.0.scene']);
});

it('ignore toute image envoyée : une case ne référence qu’une scène de la bibliothèque', function (): void {
    $user = bdmMember();
    $conversation = bdmCreate($user);
    $panels = bdmPanels(3);
    $panels[0]['image'] = 'https://exemple.org/photo.jpg';
    $panels[0]['image_path'] = 'uploads/photo.jpg';

    $this->actingAs($user)->putJson('/api/v1/bd-conversations/'.$conversation->ulid, ['title' => 'Ma soirée', 'panels' => $panels])->assertOk();
    $panel = $conversation->panels()->with('scene')->first();
    expect($panel->scene->key)->toBe('m01-case-1')
        ->and((string) $panel->scene->image_path)->not->toContain('photo');
});

it('bloque les numéros de téléphone, les adresses e-mail, les liens et les mots interdits avec un message clair', function (string $text, string $code): void {
    BadWord::query()->create(['word' => 'motinterdit', 'severity' => BadWordSeverity::Block, 'category' => 'insulte', 'is_active' => true]);
    $user = bdmMember();
    $conversation = bdmCreate($user);

    $response = $this->actingAs($user)->putJson('/api/v1/bd-conversations/'.$conversation->ulid, ['title' => 'Ma soirée', 'panels' => bdmPanels(3, $text)])
        ->assertStatus(422)->assertJsonPath('code', 'bd_content_rejected');
    expect($response->json('errors')['panels.0.bubbles.0.texte'][0])->toBe(__('conversations_bd.filter.'.$code));
    // Rien n'est enregistré.
    expect($conversation->panels()->count())->toBe(0);
})->with([
    'téléphone' => ['Appelle-moi au +229 97 00 11 22 ce soir', 'phone'],
    'e-mail' => ['Écris-moi à fifame.test@exemple.com', 'email'],
    'e-mail déguisé' => ['Écris à fifame (at) exemple point com', 'email'],
    'lien' => ['Regarde https://exemple.org/page', 'link'],
    'domaine nu' => ['Va sur monsite.com pour voir', 'link'],
    'mot interdit' => ['Tu es un motinterdit', 'banned'],
]);

it('filtre aussi le titre et le résumé, à la création comme à l’enregistrement', function (): void {
    $user = bdmMember();
    $this->actingAs($user)->postJson('/api/v1/bd-conversations', ['title' => 'Mon numéro 97 00 11 22'])
        ->assertStatus(422)->assertJsonValidationErrors(['title']);

    $conversation = bdmCreate($user);
    $this->actingAs($user)->putJson('/api/v1/bd-conversations/'.$conversation->ulid, ['title' => 'Ma soirée', 'summary' => 'Voir www.exemple.org', 'panels' => []])
        ->assertStatus(422)->assertJsonValidationErrors(['summary']);
});

it('laisse passer un texte ordinaire (heures, pourcentages, ponctuation collée)', function (): void {
    $filter = app(ConversationContentFilter::class);
    expect($filter->check(['a' => 'On se voit à 18 h 30, d’accord.Me voilà !', 'b' => 'J’ai eu 12/20 au devoir, 100 % content.', 'c' => 'Il est 10h.']))->toBe([]);
});

it('n’ouvre jamais la création d’une autre personne ni un modèle de l’équipe (404)', function (): void {
    $owner = bdmMember();
    $conversation = bdmCreate($owner);
    $other = bdmMember();

    $this->actingAs($other)->getJson('/api/v1/bd-conversations/'.$conversation->ulid)->assertNotFound();
    $this->actingAs($other)->putJson('/api/v1/bd-conversations/'.$conversation->ulid, ['title' => 'Piratage', 'panels' => []])->assertNotFound();
    $this->actingAs($other)->deleteJson('/api/v1/bd-conversations/'.$conversation->ulid)->assertNotFound();
    $this->actingAs($other)->get('/mes-conversations-bd/'.$conversation->ulid)->assertNotFound();

    $template = BdConversation::query()->whereNotNull('model_key')->firstOrFail();
    $this->actingAs($owner)->getJson('/api/v1/bd-conversations/'.$template->ulid)->assertNotFound();
});

it('exige la permission sociale et une connexion', function (): void {
    $this->getJson('/api/v1/bd-conversations')->assertUnauthorized();
    $this->get('/mes-conversations-bd')->assertRedirect();

    $user = bdmMember();
    $user->syncRoles([]);
    $this->actingAs($user->fresh())->getJson('/api/v1/bd-conversations')->assertForbidden();
    $this->actingAs($user->fresh())->get('/mes-conversations-bd')->assertForbidden();
});

it('supprime un brouillon mais pas une création proposée', function (): void {
    $user = bdmMember();
    $conversation = bdmCreate($user);
    $this->actingAs($user)->putJson('/api/v1/bd-conversations/'.$conversation->ulid, ['title' => 'Ma soirée', 'panels' => bdmPanels(3)])->assertOk();
    $this->actingAs($user)->postJson('/api/v1/bd-conversations/'.$conversation->ulid.'/submit')->assertOk();

    $this->actingAs($user)->deleteJson('/api/v1/bd-conversations/'.$conversation->ulid)->assertStatus(422)->assertJsonPath('code', 'bd_not_editable');
    $this->actingAs($user)->putJson('/api/v1/bd-conversations/'.$conversation->ulid, ['title' => 'Autre', 'panels' => []])->assertStatus(422);

    $draft = bdmCreate($user, 'Brouillon à jeter');
    $this->actingAs($user)->deleteJson('/api/v1/bd-conversations/'.$draft->ulid)->assertOk();
    expect(BdConversation::query()->find($draft->getKey()))->toBeNull();
});
