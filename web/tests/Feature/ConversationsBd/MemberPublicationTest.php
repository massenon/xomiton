<?php

declare(strict_types=1);

use App\Application\Content\ConversationsBd\ImportConversationsBd;
use App\Application\Moderation\DecideReport;
use App\Application\Seo\SitemapBuilder;
use App\Enums\BdConversationStatus;
use App\Enums\ModerationKind;
use App\Livewire\Admin\Ccc\Conversations as CccConversations;
use App\Livewire\Admin\Moderation\ConversationsBd as ModerationQueue;
use App\Models\AuditLog;
use App\Models\BdConversation;
use App\Models\BdScene;
use App\Models\DatabaseNotification;
use App\Models\ModerationReport;
use App\Models\User;
use App\Notifications\TeamNotice;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/*
 * P20-08 : publication et modération des créations — proposer (anonyme par défaut, pseudonyme sur choix ; grammaire et
 * filtres exigés, au moins une bulle), file /admin/moderation/conversations-bd (`moderation.manage` : modérateur·rices
 * compris·es), publier (slug unique, page publique, plan du site) ou refuser (motif obligatoire), notification de
 * l'auteur·rice, retrait à tout moment (dépublication immédiate), correction puis nouvelle proposition, signalement d'une
 * création publiée traité dans /admin/moderation (masquer = retirer), journal d'audit.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    app(ImportConversationsBd::class)();
    BdScene::query()->update(['is_active' => true]);
});

function bdpUser(string $role = 'membre', array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    $user->syncRoles([$role]);

    return $user;
}

/** Brouillon valide de 3 cases, enregistré par l'API. */
function bdpDraft(User $author, string $title = 'Le soir où j’ai dit non'): BdConversation
{
    test()->actingAs($author)->postJson('/api/v1/bd-conversations', ['title' => $title, 'summary' => 'Fifamè ose dire non.'])->assertCreated();
    $conversation = BdConversation::query()->where('author_id', $author->getKey())->latest('id')->firstOrFail();
    $panels = [];
    foreach ([1, 2, 3] as $n) {
        $panels[] = ['scene' => 'm01-case-'.$n, 'bubbles' => [[
            'type' => 'parole', 'personnage' => 'fifame', 'texte' => 'Non, je ne viendrai pas, case '.$n.'.',
            'position' => ['x' => 4, 'y' => 4], 'queue' => ['vers' => 'personnage', 'x' => 30, 'y' => 70],
        ]]];
    }
    test()->actingAs($author)->putJson('/api/v1/bd-conversations/'.$conversation->ulid, ['title' => $title, 'summary' => 'Fifamè ose dire non.', 'panels' => $panels])->assertOk();

    return $conversation->refresh();
}

function bdpSubmit(User $author, BdConversation $conversation, bool $pseudo = false): void
{
    test()->actingAs($author)->postJson('/api/v1/bd-conversations/'.$conversation->ulid.'/submit', ['show_pseudonym' => $pseudo])->assertOk()
        ->assertJsonPath('data.status', 'proposee');
}

it('propose une création anonyme par défaut et la garde invisible avant la décision', function (): void {
    $author = bdpUser('membre', ['pseudo' => 'Etoile229']);
    $conversation = bdpDraft($author);
    bdpSubmit($author, $conversation);

    $conversation->refresh();
    expect($conversation->statusValue())->toBe(BdConversationStatus::Submitted)
        ->and((bool) $conversation->is_anonymous)->toBeTrue()
        ->and($conversation->submitted_at)->not->toBeNull();
    $this->get('/conversations-bd/'.$conversation->slug)->assertNotFound();
    expect((string) $this->get('/conversations-bd')->getContent())->not->toContain('Le soir où j’ai dit non');
    expect(AuditLog::query()->where('action', 'conversations_bd.member_submitted')->exists())->toBeTrue();
});

it('refuse de proposer une conversation sans bulle ou qui enfreint la grammaire', function (): void {
    $author = bdpUser();
    $this->actingAs($author)->postJson('/api/v1/bd-conversations', ['title' => 'Vide'])->assertCreated();
    $empty = BdConversation::query()->where('author_id', $author->getKey())->firstOrFail();
    $panels = array_map(static fn (int $n): array => ['scene' => 'm01-case-'.$n, 'bubbles' => []], [1, 2, 3]);
    $this->actingAs($author)->putJson('/api/v1/bd-conversations/'.$empty->ulid, ['title' => 'Vide', 'panels' => $panels])->assertOk();

    $response = $this->actingAs($author)->postJson('/api/v1/bd-conversations/'.$empty->ulid.'/submit')->assertStatus(422)->assertJsonPath('code', 'bd_invalid');
    expect(array_column($response->json('issues'), 'code'))->toContain('no_bubble');

    $long = bdpDraft($author, 'Trop long');
    $bubble = $long->panels()->first()->bubbles()->first();
    $bubble->forceFill(['text' => implode(' ', array_fill(0, 25, 'mot'))])->save();
    $this->actingAs($author)->postJson('/api/v1/bd-conversations/'.$long->ulid.'/submit')->assertStatus(422)->assertJsonPath('code', 'bd_invalid');
    expect($long->refresh()->statusValue())->toBe(BdConversationStatus::Draft);
});

it('donne la file aux modérateur·rices, publie avec un slug unique, notifie et indexe', function (): void {
    $author = bdpUser('membre', ['pseudo' => 'Etoile229']);
    $conversation = bdpDraft($author, 'Non, c’est non');
    bdpSubmit($author, $conversation, true);
    $moderator = bdpUser('moderateur');

    $this->actingAs($moderator)->get('/admin/moderation/conversations-bd')->assertOk()->assertSee('Non, c’est non')->assertSee('Etoile229');
    $this->actingAs(bdpUser())->get('/admin/moderation/conversations-bd')->assertForbidden();

    Livewire::actingAs($moderator)->test(ModerationQueue::class)
        ->assertSee('viendrai')
        ->call('openDecision', $conversation->getKey(), 'publish')
        ->call('publish')
        ->assertHasNoErrors();

    $conversation->refresh();
    expect($conversation->statusValue())->toBe(BdConversationStatus::Published)
        // Le modèle m01 occupe déjà « non-cest-non » : slug unique.
        ->and($conversation->slug)->not->toBe('non-cest-non')->toStartWith('non-cest-non')
        ->and((int) $conversation->reviewed_by)->toBe((int) $moderator->getKey());
    $this->app['auth']->forgetGuards();

    $html = (string) $this->get('/conversations-bd/'.$conversation->slug)->assertOk()->getContent();
    expect($html)->toContain('Créée par')->toContain('Etoile229')->toContain('Signaler cette conversation')->toContain('index, follow');
    expect(app(SitemapBuilder::class)->xml())->toContain('/conversations-bd/'.$conversation->slug);

    $notice = DatabaseNotification::query()->where('notifiable_id', $author->getKey())->latest('id')->first();
    expect($notice)->not->toBeNull()->and((string) json_encode($notice->data))->toContain('bd_published');
    expect(AuditLog::query()->where('action', 'conversations_bd.member_published')->where('user_id', $moderator->getKey())->exists())->toBeTrue();
});

it('affiche « Anonyme » quand la personne n’a pas coché son pseudonyme', function (): void {
    $author = bdpUser('membre', ['pseudo' => 'Etoile229']);
    $conversation = bdpDraft($author);
    bdpSubmit($author, $conversation);
    Livewire::actingAs(bdpUser('moderateur'))->test(ModerationQueue::class)->call('openDecision', $conversation->getKey(), 'publish')->call('publish');

    $html = (string) $this->get('/conversations-bd/'.$conversation->refresh()->slug)->assertOk()->getContent();
    expect($html)->toContain('Créée par')->toContain('Anonyme')->not->toContain('Etoile229');
});

it('exige un motif pour refuser, prévient l’auteur·rice, qui corrige puis repropose', function (): void {
    Notification::fake();
    $author = bdpUser();
    $conversation = bdpDraft($author);
    bdpSubmit($author, $conversation);
    $moderator = bdpUser('moderateur');

    Livewire::actingAs($moderator)->test(ModerationQueue::class)
        ->call('openDecision', $conversation->getKey(), 'refuse')
        ->set('reason', '')
        ->call('refuse')
        ->assertHasErrors(['reason' => 'required']);
    expect($conversation->refresh()->statusValue())->toBe(BdConversationStatus::Submitted);

    Livewire::actingAs($moderator)->test(ModerationQueue::class)
        ->call('openDecision', $conversation->getKey(), 'refuse')
        ->set('reason', 'Ajoute une case qui montre la réaction de Rodrigue.')
        ->call('refuse')
        ->assertHasNoErrors();

    $conversation->refresh();
    expect($conversation->statusValue())->toBe(BdConversationStatus::Refused)
        ->and($conversation->refusal_reason)->toBe('Ajoute une case qui montre la réaction de Rodrigue.');
    Notification::assertSentTo($author, TeamNotice::class, fn (TeamNotice $notice): bool => $notice->kind === TeamNotice::BD_REFUSED && $notice->reason === 'Ajoute une case qui montre la réaction de Rodrigue.');
    $this->get('/conversations-bd/'.$conversation->slug)->assertNotFound();

    // L'auteur·rice voit le motif, corrige et propose de nouveau.
    $this->actingAs($author)->getJson('/api/v1/bd-conversations/'.$conversation->ulid)->assertJsonPath('data.refusal_reason', 'Ajoute une case qui montre la réaction de Rodrigue.')
        ->assertJsonPath('data.editable', true);
    $this->actingAs($author)->putJson('/api/v1/bd-conversations/'.$conversation->ulid, [
        'title' => 'Le soir où j’ai dit non',
        'panels' => $this->actingAs($author)->getJson('/api/v1/bd-conversations/'.$conversation->ulid)->json('data.panels'),
    ])->assertOk();
    bdpSubmit($author, $conversation);
    expect($conversation->refresh()->refusal_reason)->toBeNull();
});

it('ne laisse pas relire sa propre création', function (): void {
    $moderator = bdpUser('moderateur');
    $conversation = bdpDraft($moderator);
    bdpSubmit($moderator, $conversation);

    Livewire::actingAs($moderator)->test(ModerationQueue::class)->assertSee(__('admin.moderation.bd.own'))
        ->call('openDecision', $conversation->getKey(), 'publish')
        ->assertSet('conversationId', null);
    expect($conversation->refresh()->statusValue())->toBe(BdConversationStatus::Submitted);
});

it('laisse l’auteur·rice retirer à tout moment : dépublication immédiate et plan du site oublié', function (): void {
    $author = bdpUser();
    $conversation = bdpDraft($author);
    bdpSubmit($author, $conversation);
    Livewire::actingAs(bdpUser('moderateur'))->test(ModerationQueue::class)->call('openDecision', $conversation->getKey(), 'publish')->call('publish');
    $slug = $conversation->refresh()->slug;
    expect(app(SitemapBuilder::class)->xml())->toContain($slug);

    $this->actingAs($author)->postJson('/api/v1/bd-conversations/'.$conversation->ulid.'/withdraw')->assertOk()->assertJsonPath('data.status', 'retiree');
    $this->app['auth']->forgetGuards();

    $this->get('/conversations-bd/'.$slug)->assertNotFound();
    $this->get('/conversations-bd/'.$slug.'/pdf')->assertNotFound();
    expect(app(SitemapBuilder::class)->xml())->not->toContain($slug);
    expect((int) $conversation->refresh()->withdrawn_by)->toBe((int) $author->getKey());
    expect(AuditLog::query()->where('action', 'conversations_bd.member_withdrawn')->exists())->toBeTrue();
});

it('garde les créations des membres hors de l’onglet des modèles de /admin/ccc', function (): void {
    $author = bdpUser();
    bdpDraft($author, 'Création privée de membre');

    Livewire::actingAs(bdpUser('admin'))->test(CccConversations::class)
        ->assertSee('Non, c’est non')
        ->assertDontSee('Création privée de membre');
});

it('signale une création publiée avec les motifs du projet et la traite dans /admin/moderation', function (): void {
    $author = bdpUser();
    $conversation = bdpDraft($author);
    bdpSubmit($author, $conversation);
    $moderator = bdpUser('moderateur');
    Livewire::actingAs($moderator)->test(ModerationQueue::class)->call('openDecision', $conversation->getKey(), 'publish')->call('publish');
    $conversation->refresh();
    $reporter = bdpUser();

    // Page publique : formulaire POST sans JavaScript.
    $this->actingAs($reporter)->post('/conversations-bd/'.$conversation->slug.'/signaler', ['reason' => 'harcelement', 'details' => 'Moqueries.'])
        ->assertRedirect(route('conversations-bd.show', ['slug' => $conversation->slug]).'#signaler');
    $report = ModerationReport::query()->where('subject_type', $conversation->getMorphClass())->where('subject_id', $conversation->getKey())->firstOrFail();
    expect($report->kind)->toBe(ModerationKind::BdConversation)
        ->and((int) $report->reported_user_id)->toBe((int) $author->getKey())
        ->and((string) $report->excerpt)->toContain('Le soir où j’ai dit non');

    // Une seule fois par personne ; jamais sa propre création ; motif inconnu refusé.
    $this->actingAs($reporter)->postJson('/api/v1/bd-conversations/'.$conversation->ulid.'/report', ['reason' => 'harcelement'])->assertOk();
    $this->actingAs($author)->postJson('/api/v1/bd-conversations/'.$conversation->ulid.'/report', ['reason' => 'spam'])->assertForbidden();
    $this->actingAs(bdpUser())->postJson('/api/v1/bd-conversations/'.$conversation->ulid.'/report', ['reason' => 'filtre_automatique'])->assertStatus(422);
    expect(ModerationReport::query()->where('subject_id', $conversation->getKey())->where('subject_type', $conversation->getMorphClass())->count())->toBe(1);

    // La modération masque : la création est retirée (par la modération) et dépubliée.
    $this->actingAs($moderator)->get('/admin/moderation')->assertOk()->assertSee('Conversation en BD');
    app(DecideReport::class)($report, DecideReport::HIDE, $moderator, 'Contenu moqueur.');
    $conversation->refresh();
    expect($conversation->statusValue())->toBe(BdConversationStatus::Withdrawn)
        ->and($conversation->isWithdrawnByModeration())->toBeTrue();
    $this->app['auth']->forgetGuards();
    $this->get('/conversations-bd/'.$conversation->slug)->assertNotFound();

    // « Rendre visible » (décision distincte) : la création retirée par la modération redevient publique.
    app(DecideReport::class)->unhide($report->refresh(), $moderator);
    expect($conversation->refresh()->statusValue())->toBe(BdConversationStatus::Published);
});

it('ne propose le signalement qu’aux personnes connectées', function (): void {
    $author = bdpUser();
    $conversation = bdpDraft($author);
    bdpSubmit($author, $conversation);
    Livewire::actingAs(bdpUser('moderateur'))->test(ModerationQueue::class)->call('openDecision', $conversation->getKey(), 'publish')->call('publish');
    $this->app['auth']->forgetGuards();
    $slug = $conversation->refresh()->slug;

    expect((string) $this->get('/conversations-bd/'.$slug)->getContent())->toContain(__('pages/conversations_bd.report.login'));
    $this->post('/conversations-bd/'.$slug.'/signaler', ['reason' => 'spam'])->assertRedirect(route('connexion'));
});
