<?php

declare(strict_types=1);

use App\Enums\BadWordSeverity;
use App\Enums\ContentReportReason;
use App\Enums\ModerationKind;
use App\Enums\PostStatus;
use App\Models\DatabaseNotification;
use App\Models\Forum;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\ModerationReport;
use App\Models\User;
use App\Notifications\ContentReported as ContentReportedNotification;
use App\Notifications\ForumReplyPosted;
use Database\Seeders\ForumsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\Support\FeedFixtures as F;

/*
 * P8-05, docs/API.md §16 : 5 forums fixes seedés, ouvrir un sujet (modale de forums.php : titre ≥ 5, message ≥ 10,
 * forum, « Publier de façon anonyme »), « Sujets récents » (forum, recherche, « Charger plus »), fiche et compteur de
 * vues, répondre (≥ 2, anonyme possible), compteurs du sujet et du forum, cloche ForumReplyPosted, sujet fermé,
 * suppression, signalement et RG-16 comme le fil, filtre de mots.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(ForumsSeeder::class);
    $this->forum = Forum::query()->forKey('f3')->firstOrFail();
    $this->awa = F::member();
    $this->bea = F::member();
});

function forumTopic(User $author, array $attributes = []): ForumTopic
{
    return ForumTopic::factory()->for($author)->create($attributes + ['forum_id' => Forum::query()->forKey('f1')->value('id'), 'views_count' => 0]);
}

function openTopic(User $author, array $payload = []): TestResponse
{
    return F::as($author)->postJson('/api/v1/forums/topics', $payload + [
        'forum' => 'f3', 'title' => 'Règles douloureuses', 'body' => 'Que faire quand la douleur empêche d’aller en cours ?',
    ]);
}

describe('forums', function (): void {
    it('liste les 5 forums du prototype dans l’ordre, avec compteurs, icône et adresse filtrée', function (): void {
        $response = F::as($this->awa)->getJson('/api/v1/forums')->assertOk();

        expect(array_column($response->json('data'), 'key'))->toBe(['f1', 'f2', 'f3', 'f4', 'f5'])
            ->and($response->json('data.2'))->toMatchArray([
                'name' => 'Santé & bien-être', 'slug' => 'sante-bien-etre', 'icon' => 'heart-pulse',
                'topics_count' => 0, 'posts_count' => 0, 'url' => url('/forums?forum=f3'),
            ]);
    });

    it('refuse les forums sans accès au réseau (social.access)', function (): void {
        F::as(User::factory()->create())->getJson('/api/v1/forums')->assertForbidden();
        F::as(User::factory()->create())->getJson('/api/v1/forums/topics')->assertForbidden();
    });
});

describe('ouvrir un sujet', function (): void {
    it('publie un sujet : 201 + Location, forum compté, titre et message nettoyés', function (): void {
        $response = openTopic($this->awa, ['title' => '  Règles douloureuses  '])->assertCreated()
            ->assertJsonPath('data.title', 'Règles douloureuses')
            ->assertJsonPath('data.body', 'Que faire quand la douleur empêche d’aller en cours ?')
            ->assertJsonPath('data.forum.key', 'f3')
            ->assertJsonPath('data.author.id', $this->awa->id)
            ->assertJsonPath('data.author.pseudo', $this->awa->pseudo)
            ->assertJsonPath('data.is_mine', true)
            ->assertJsonPath('data.replies_count', 0)
            ->assertJsonPath('data.views_count', 0)
            ->assertJsonPath('data.can.reply', true)
            ->assertJsonPath('data.can.delete', true)
            ->assertJsonPath('data.can.report', false);

        $topic = ForumTopic::query()->sole();
        expect($response->json('data.id'))->toBe($topic->ulid)
            ->and($response->headers->get('Location'))->toBe(url('/forums/'.$topic->ulid))
            ->and($topic->user_id)->toBe($this->awa->id)
            ->and($topic->status)->toBe(PostStatus::Published)
            ->and($this->forum->refresh()->topics_count)->toBe(1);
    });

    it('publie de façon anonyme : « Membre anonyme », aucun identifiant, auteur·rice gardé·e en base', function (): void {
        $response = openTopic($this->awa, ['is_anonymous' => true])->assertCreated()
            ->assertJsonPath('data.author.id', null)
            ->assertJsonPath('data.author.pseudo', 'Membre anonyme')
            ->assertJsonPath('data.author.role', null)
            ->assertJsonPath('data.author.badge', null)
            ->assertJsonPath('data.is_anonymous', true)
            ->assertJsonPath('data.is_mine', true);

        expect($response->getContent())->not->toContain($this->awa->pseudo)
            ->and(ForumTopic::query()->sole()->user_id)->toBe($this->awa->id);
    });

    it('refuse un titre < 5, un message < 10, un forum inconnu ou fermé (422 par champ)', function (): void {
        openTopic($this->awa, ['title' => 'Aide'])->assertUnprocessable()
            ->assertJsonValidationErrors(['title' => 'Le titre doit contenir au moins 5 caractères.']);
        openTopic($this->awa, ['body' => ' Trop bref '])->assertUnprocessable()
            ->assertJsonValidationErrors(['body' => 'Ton message doit contenir au moins 10 caractères.']);
        openTopic($this->awa, ['forum' => 'f9'])->assertUnprocessable()->assertJsonValidationErrors('forum');
        openTopic($this->awa, ['forum' => null])->assertUnprocessable()->assertJsonValidationErrors(['forum' => 'Choisis un forum.']);

        $this->forum->forceFill(['is_active' => false])->save();
        openTopic($this->awa)->assertUnprocessable()->assertJsonValidationErrors('forum');

        expect(ForumTopic::query()->count())->toBe(0);
    });

    it('exige un e-mail confirmé et la permission posts.create', function (): void {
        openTopic(F::member(verified: false))->assertForbidden()->assertJsonPath('code', 'email_unverified');
        openTopic(User::factory()->create())->assertForbidden();
        expect(ForumTopic::query()->count())->toBe(0);
    });

    it('applique le filtre de mots du fil : terme bloquant refusé (422 neutre), terme à surveiller signalé', function (): void {
        F::badWord('insultex');
        F::badWord('surveillex', BadWordSeverity::Flag);

        openTopic($this->awa, ['title' => 'Un insultex ici'])->assertUnprocessable()
            ->assertJsonPath('code', 'content_blocked')->assertJsonValidationErrors('title');
        expect(ForumTopic::query()->count())->toBe(0);

        openTopic($this->awa, ['body' => 'Un message avec surveillex dedans.'])->assertCreated();
        $report = ModerationReport::query()->sole();
        expect($report->kind)->toBe(ModerationKind::Topic)
            ->and($report->reporter_user_id)->toBeNull()
            ->and(ForumTopic::query()->sole()->status)->toBe(PostStatus::Published);
    });
});

describe('sujets récents', function (): void {
    it('filtre par forum, cherche dans le titre et le message, pagine par 6 avec épinglés d’abord', function (): void {
        $f1 = Forum::query()->forKey('f1')->firstOrFail();
        $old = ForumTopic::factory()->for($this->awa)->create(['forum_id' => $f1->id, 'title' => 'Ancien sujet', 'created_at' => now()->subDays(3)]);
        $pinned = ForumTopic::factory()->for($this->awa)->pinned()->create(['forum_id' => $f1->id, 'title' => 'Charte du forum', 'created_at' => now()->subDays(9)]);
        ForumTopic::factory()->count(5)->for($this->bea)->create(['forum_id' => $f1->id, 'created_at' => now()->subDay()]);
        $health = ForumTopic::factory()->for($this->bea)->create(['forum_id' => $this->forum->id, 'title' => 'Dépistage gratuit', 'body' => 'Où se faire dépister à Parakou ?']);
        ForumTopic::factory()->for($this->bea)->hidden()->create(['forum_id' => $this->forum->id]);
        $closed = Forum::factory()->inactive()->create();
        ForumTopic::factory()->for($this->bea)->create(['forum_id' => $closed->id]);

        $page = F::as($this->awa)->getJson('/api/v1/forums/topics')->assertOk()
            ->assertJsonPath('meta.total', 8)->assertJsonPath('meta.per_page', 6)->assertJsonPath('meta.last_page', 2);
        expect($page->json('data.0.id'))->toBe($pinned->ulid)
            ->and($page->json('data.0.is_pinned'))->toBeTrue()
            ->and($page->json('data.1.id'))->toBe($health->ulid)
            ->and($page->json('data.0'))->not->toHaveKey('body');

        $next = F::as($this->awa)->getJson('/api/v1/forums/topics?page=2')->assertOk();
        expect(array_column($next->json('data'), 'id'))->toContain($old->ulid);

        expect(array_column(F::as($this->awa)->getJson('/api/v1/forums/topics?forum=f3')->assertOk()->json('data'), 'id'))->toBe([$health->ulid])
            ->and(array_column(F::as($this->awa)->getJson('/api/v1/forums/topics?q=parakou')->assertOk()->json('data'), 'id'))->toBe([$health->ulid]);

        F::as($this->awa)->getJson('/api/v1/forums/topics?forum=zz')->assertUnprocessable()->assertJsonValidationErrors('forum');
        F::as($this->awa)->getJson('/api/v1/forums/topics?q='.str_repeat('a', 61))->assertUnprocessable()->assertJsonValidationErrors('q');
    });

    it('sert la liste sans requête par sujet (PERFORMANCE.md §5 : blocages, droits, auteur·rices préchargés)', function (): void {
        foreach (range(1, 6) as $i) {
            forumTopic(F::member($i % 2 === 0 ? 'pair' : 'membre'), ['is_anonymous' => $i === 3]);
        }

        $queries = function (int $perPage): int {
            $request = F::as($this->awa);
            DB::flushQueryLog();
            DB::enableQueryLog();
            $request->getJson('/api/v1/forums/topics?per_page='.$perPage)->assertOk()->assertJsonCount($perPage, 'data')
                ->assertJsonPath('data.0.can.reply', true);
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };

        $queries(1);
        expect($queries(6))->toBe($queries(1));
    });

    it('borne per_page entre 1 et xomiton.api.max_per_page', function (): void {
        config(['xomiton.api.max_per_page' => 3]);
        foreach (range(1, 4) as $i) {
            forumTopic($this->bea);
        }

        F::as($this->awa)->getJson('/api/v1/forums/topics?per_page=500')->assertOk()->assertJsonPath('meta.per_page', 3)->assertJsonCount(3, 'data');
        F::as($this->awa)->getJson('/api/v1/forums/topics?per_page=0')->assertOk()->assertJsonPath('meta.per_page', 1)->assertJsonCount(1, 'data');
    });
});

describe('fiche et vues', function (): void {
    it('sert le message et compte une vue par compte dans la fenêtre (jeton Bearer), jamais pour l’auteur·rice', function (): void {
        $topic = forumTopic($this->awa);

        F::as($this->bea)->getJson('/api/v1/forums/topics/'.$topic->ulid)->assertOk()
            ->assertJsonPath('data.views_count', 1)->assertJsonPath('data.body', $topic->body)->assertJsonPath('data.can.report', true);
        F::as($this->bea)->getJson('/api/v1/forums/topics/'.$topic->ulid)->assertOk()->assertJsonPath('data.views_count', 1);
        F::as($this->awa)->getJson('/api/v1/forums/topics/'.$topic->ulid)->assertOk()->assertJsonPath('data.views_count', 1);
        F::as(F::member())->getJson('/api/v1/forums/topics/'.$topic->ulid)->assertOk()->assertJsonPath('data.views_count', 2);

        $this->travel((int) config('xomiton.forums.view_window_minutes') + 1)->minutes();
        F::as($this->bea)->getJson('/api/v1/forums/topics/'.$topic->ulid)->assertOk()->assertJsonPath('data.views_count', 3);

        F::as($this->bea)->withHeader('Sec-Purpose', 'prefetch')->getJson('/api/v1/forums/topics/'.$topic->ulid)->assertOk();
        expect($topic->refresh()->views_count)->toBe(3);
    });

    it('rend 404 pour un sujet masqué, supprimé ou d’un forum fermé ; l’auteur·rice voit le sien avec la mention', function (): void {
        $hidden = forumTopic($this->awa, ['status' => PostStatus::Hidden]);
        $deleted = forumTopic($this->awa);
        $deleted->delete();
        $closedForum = Forum::factory()->inactive()->create();
        $closed = ForumTopic::factory()->for($this->awa)->create(['forum_id' => $closedForum->id]);

        F::as($this->bea)->getJson('/api/v1/forums/topics/'.$hidden->ulid)->assertNotFound();
        F::as($this->bea)->getJson('/api/v1/forums/topics/'.$deleted->ulid)->assertNotFound();
        F::as($this->bea)->getJson('/api/v1/forums/topics/'.$closed->ulid)->assertNotFound();
        F::as($this->bea)->getJson('/api/v1/forums/topics/01JC0000000000000000000000')->assertNotFound();
        F::as($this->awa)->getJson('/api/v1/forums/topics/'.$hidden->ulid)->assertOk()->assertJsonPath('data.moderated', true);
        F::as(F::member('moderateur'))->getJson('/api/v1/forums/topics/'.$hidden->ulid)->assertOk()->assertJsonPath('data.moderated', false);
    });
});

describe('répondre', function (): void {
    it('publie une réponse, tient les compteurs du sujet et du forum et prévient l’auteur·rice du sujet', function (): void {
        $topic = forumTopic($this->awa, ['forum_id' => $this->forum->id]);

        $response = F::as($this->bea)->postJson('/api/v1/forums/topics/'.$topic->ulid.'/replies', ['body' => '  Courage, tu n’es pas seule.  '])
            ->assertCreated()
            ->assertJsonPath('data.body', 'Courage, tu n’es pas seule.')
            ->assertJsonPath('data.author.id', $this->bea->id)
            ->assertJsonPath('data.is_mine', true)
            ->assertJsonPath('meta.replies_count', 1);

        $topic->refresh();
        expect($response->json('data.id'))->toBe(ForumReply::query()->sole()->id)
            ->and($topic->replies_count)->toBe(1)
            ->and($topic->last_reply_at)->not->toBeNull()
            ->and($this->forum->refresh()->posts_count)->toBe(1)
            ->and($this->forum->topics_count)->toBe(1);

        $notification = DatabaseNotification::query()->where('type', ForumReplyPosted::class)->sole();
        expect($notification->notifiable_id)->toBe($this->awa->id)
            ->and($notification->category)->toBe('communaute')
            ->and($notification->data['title'])->toBe('Nouvelle réponse')
            ->and($notification->data['body'])->toBe(__('notifications.forum_reply.body', ['name' => $this->bea->pseudo, 'title' => $topic->title]))
            ->and($notification->data['url'])->toBe(url('/forums/'.$topic->ulid).'#reponses');

        F::as(F::member())->postJson('/api/v1/forums/topics/'.$topic->ulid.'/replies', ['body' => 'Moi aussi.'])->assertCreated();
        expect(DatabaseNotification::query()->where('type', ForumReplyPosted::class)->whereNull('read_at')->count())->toBe(1);

        F::as($this->awa)->postJson('/api/v1/forums/topics/'.$topic->ulid.'/replies', ['body' => 'Merci à vous.'])->assertCreated();
        expect(DatabaseNotification::query()->where('type', ForumReplyPosted::class)->count())->toBe(1)
            ->and($topic->refresh()->replies_count)->toBe(3);
    });

    it('répond de façon anonyme : « Membre anonyme » dans la réponse et dans la cloche', function (): void {
        $topic = forumTopic($this->awa);

        $response = F::as($this->bea)->postJson('/api/v1/forums/topics/'.$topic->ulid.'/replies', ['body' => 'Je te crois.', 'is_anonymous' => true])
            ->assertCreated()->assertJsonPath('data.author.id', null)->assertJsonPath('data.author.pseudo', 'Membre anonyme');

        $notification = DatabaseNotification::query()->where('type', ForumReplyPosted::class)->sole();
        expect($response->getContent())->not->toContain($this->bea->pseudo)
            ->and($notification->data['body'])->toBe(__('notifications.forum_reply.body', ['name' => 'Membre anonyme', 'title' => $topic->title]))
            ->and(json_encode($notification->data))->not->toContain($this->bea->pseudo)
            ->and(ForumReply::query()->sole()->user_id)->toBe($this->bea->id);
    });

    it('refuse une réponse < 2 caractères, sur un sujet fermé (422 topic_locked) ou sans e-mail confirmé', function (): void {
        $topic = forumTopic($this->awa);
        $locked = forumTopic($this->awa, ['is_locked' => true]);

        F::as($this->bea)->postJson('/api/v1/forums/topics/'.$topic->ulid.'/replies', ['body' => ' a '])->assertUnprocessable()
            ->assertJsonValidationErrors(['body' => 'Ta réponse doit contenir au moins 2 caractères.']);
        F::as($this->bea)->postJson('/api/v1/forums/topics/'.$locked->ulid.'/replies', ['body' => 'Bonjour à toutes.'])->assertUnprocessable()
            ->assertJsonPath('code', 'topic_locked')->assertJsonPath('message', __('community.forums.errors.locked'));
        F::as(F::member(verified: false))->postJson('/api/v1/forums/topics/'.$topic->ulid.'/replies', ['body' => 'Bonjour.'])->assertForbidden();
        F::as($this->bea)->getJson('/api/v1/forums/topics/'.$locked->ulid)->assertOk()->assertJsonPath('data.can.reply', false);

        expect(ForumReply::query()->count())->toBe(0);
    });

    it('refuse une réponse dans un forum fermé, même à l’auteur·rice du sujet et à la modération qui le lisent', function (): void {
        $closed = Forum::factory()->inactive()->create(['posts_count' => 0]);
        $topic = ForumTopic::factory()->for($this->awa)->create(['forum_id' => $closed->id]);
        $uri = '/api/v1/forums/topics/'.$topic->ulid.'/replies';

        F::as($this->bea)->postJson($uri, ['body' => 'Bonjour à toutes.'])->assertNotFound();
        F::as($this->awa)->postJson($uri, ['body' => 'Bonjour à toutes.'])->assertForbidden();
        F::as(F::member('moderateur'))->postJson($uri, ['body' => 'Bonjour à toutes.'])->assertForbidden();
        F::as(F::member('superadmin'))->postJson($uri, ['body' => 'Bonjour à toutes.'])->assertUnprocessable()->assertJsonPath('code', 'topic_locked');
        F::as($this->awa)->getJson('/api/v1/forums/topics/'.$topic->ulid)->assertOk()->assertJsonPath('data.can.reply', false);

        expect(ForumReply::query()->count())->toBe(0)
            ->and($closed->refresh()->posts_count)->toBe(0)
            ->and($topic->refresh()->replies_count)->toBe(0);
    });

    it('rend 404 pour les réponses d’un sujet masqué ou supprimé ; l’auteur·rice lit celles du sien masqué', function (): void {
        $hidden = forumTopic($this->awa, ['status' => PostStatus::Hidden]);
        ForumReply::factory()->forTopic($hidden)->for($this->bea)->create();
        $deleted = forumTopic($this->awa);
        $deleted->delete();

        F::as($this->bea)->getJson('/api/v1/forums/topics/'.$hidden->ulid.'/replies')->assertNotFound();
        F::as($this->bea)->getJson('/api/v1/forums/topics/'.$deleted->ulid.'/replies')->assertNotFound();
        F::as($this->awa)->getJson('/api/v1/forums/topics/'.$hidden->ulid.'/replies')->assertOk()->assertJsonCount(1, 'data');
    });

    it('liste les réponses de la plus ancienne à la plus récente, sans les réponses masquées', function (): void {
        $topic = forumTopic($this->awa);
        $first = ForumReply::factory()->forTopic($topic)->for($this->bea)->create(['created_at' => now()->subHour()]);
        $second = ForumReply::factory()->forTopic($topic)->for($this->awa)->anonymous()->create();
        ForumReply::factory()->forTopic($topic)->for($this->bea)->hidden()->create();

        $response = F::as($this->bea)->getJson('/api/v1/forums/topics/'.$topic->ulid.'/replies')->assertOk();
        expect(array_column($response->json('data'), 'id'))->toBe([$first->id, $second->id])
            ->and($response->json('data.1.author.id'))->toBeNull()
            ->and($response->json('data.1.can.report'))->toBeTrue();
    });

    it('affiche le badge du prototype (pair, admin, superadmin ; pas la modération), jamais pour une réponse anonyme', function (): void {
        $topic = forumTopic($this->awa);
        $peer = F::member('pair');
        ForumReply::factory()->forTopic($topic)->for(F::member('moderateur'))->create(['created_at' => now()->subMinutes(3)]);
        ForumReply::factory()->forTopic($topic)->for(F::member('admin'))->create(['created_at' => now()->subMinutes(2)]);
        ForumReply::factory()->forTopic($topic)->for($peer)->create(['created_at' => now()->subMinute()]);
        ForumReply::factory()->forTopic($topic)->for($peer)->anonymous()->create();

        $data = F::as($this->bea)->getJson('/api/v1/forums/topics/'.$topic->ulid.'/replies')->assertOk()->json('data');
        expect(array_column(array_column($data, 'author'), 'badge'))->toBe([null, 'Équipe Xomiton', 'Pair animateur·rice', null])
            ->and($data[3]['author']['role'])->toBeNull();
    });
});

describe('suppression', function (): void {
    it('laisse l’auteur·rice supprimer son sujet, la modération tout supprimer (journalisé), pas les autres', function (): void {
        $topic = forumTopic($this->awa, ['forum_id' => $this->forum->id]);
        $this->forum->forceFill(['topics_count' => 1])->save();

        F::as($this->bea)->deleteJson('/api/v1/forums/topics/'.$topic->ulid)->assertForbidden();
        F::as($this->awa)->deleteJson('/api/v1/forums/topics/'.$topic->ulid)->assertNoContent();

        expect(ForumTopic::withTrashed()->sole()->status)->toBe(PostStatus::Deleted)
            ->and($this->forum->refresh()->topics_count)->toBe(0);
        F::as($this->bea)->getJson('/api/v1/forums/topics/'.$topic->ulid)->assertNotFound();

        $other = forumTopic($this->bea);
        F::as(F::member('moderateur'))->deleteJson('/api/v1/forums/topics/'.$other->ulid)->assertNoContent();
        $this->assertDatabaseHas('audit_logs', ['action' => 'forum.topic_deleted_by_moderation', 'subject_id' => $other->id]);
    });

    it('supprime une réponse et recalcule les compteurs', function (): void {
        $topic = forumTopic($this->awa);
        $reply = ForumReply::factory()->forTopic($topic)->for($this->bea)->create();
        $topic->forceFill(['replies_count' => 1])->save();

        F::as($this->awa)->deleteJson('/api/v1/forums/replies/'.$reply->id)->assertForbidden();
        F::as($this->bea)->deleteJson('/api/v1/forums/replies/'.$reply->id)->assertNoContent();
        F::as($this->bea)->deleteJson('/api/v1/forums/replies/'.$reply->id)->assertNotFound();

        expect($topic->refresh()->replies_count)->toBe(0)->and($topic->last_reply_at)->toBeNull();
    });

    it('laisse la modération supprimer une réponse (journal forum.reply_deleted_by_moderation)', function (): void {
        $topic = forumTopic($this->awa);
        $mine = ForumReply::factory()->forTopic($topic)->for($this->bea)->create();
        $reply = ForumReply::factory()->forTopic($topic)->for($this->bea)->create();
        $moderator = F::member('moderateur');

        F::as($this->bea)->deleteJson('/api/v1/forums/replies/'.$mine->id)->assertNoContent();
        F::as($moderator)->deleteJson('/api/v1/forums/replies/'.$reply->id)->assertNoContent();

        $this->assertDatabaseHas('audit_logs', ['action' => 'forum.reply_deleted_by_moderation', 'subject_id' => $reply->id, 'user_id' => $moderator->id]);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'forum.reply_deleted_by_moderation', 'subject_id' => $mine->id]);
        expect(ForumReply::withTrashed()->find($reply->id)?->status)->toBe(PostStatus::Deleted)
            ->and($topic->refresh()->replies_count)->toBe(0);
    });
});

describe('signalement', function (): void {
    it('signale un sujet (201, puis 200), prévient la modération, jamais son propre sujet', function (): void {
        $moderator = F::member('moderateur');
        Notification::fake();
        $topic = forumTopic($this->awa, ['title' => 'Un titre clair', 'body' => 'Un message à relire.']);
        $uri = '/api/v1/forums/topics/'.$topic->ulid.'/report';

        F::as($this->bea)->postJson($uri, ['reason' => 'harcelement'])->assertCreated()->assertJsonPath('data', ['reported' => true, 'reason' => 'harcelement']);
        F::as($this->bea)->postJson($uri, ['reason' => 'harcelement'])->assertOk();
        F::as($this->awa)->postJson($uri, ['reason' => 'harcelement'])->assertForbidden();
        F::as($this->bea)->postJson($uri, ['reason' => 'automatique'])->assertUnprocessable();

        $report = ModerationReport::query()->sole();
        expect($report->kind)->toBe(ModerationKind::Topic)
            ->and($report->subject_id)->toBe($topic->id)
            ->and($report->reported_user_id)->toBe($this->awa->id)
            ->and($report->reason)->toBe(ContentReportReason::from('harcelement')->label())
            ->and($report->excerpt)->toBe('Un titre clair — Un message à relire.');
        Notification::assertSentTo($moderator, ContentReportedNotification::class);
    });

    it('masque un sujet au 3e signalement de personnes distinctes (RG-16) et recalcule le forum', function (): void {
        $topic = forumTopic($this->awa, ['forum_id' => $this->forum->id]);
        $this->forum->forceFill(['topics_count' => 1])->save();

        foreach (range(1, 3) as $n) {
            F::as(F::member())->postJson('/api/v1/forums/topics/'.$topic->ulid.'/report', ['reason' => 'discrimination'])->assertCreated();
        }

        expect($topic->refresh()->status)->toBe(PostStatus::Hidden)->and($this->forum->refresh()->topics_count)->toBe(0);
        $this->assertDatabaseHas('audit_logs', ['action' => 'moderation.auto_hidden', 'subject_id' => $topic->id]);
        F::as($this->bea)->getJson('/api/v1/forums/topics/'.$topic->ulid)->assertNotFound();
        expect(F::as($this->bea)->getJson('/api/v1/forums/topics')->json('data'))->toBe([]);
    });

    it('signale une réponse (nature « reponse ») et la masque au seuil en recomptant le sujet', function (): void {
        config(['xomiton.feed.auto_hide_after_reports' => 2]);
        $topic = forumTopic($this->awa);
        $reply = ForumReply::factory()->forTopic($topic)->for($this->bea)->create();
        $topic->forceFill(['replies_count' => 1])->save();

        F::as($this->bea)->postJson('/api/v1/forums/replies/'.$reply->id.'/report', ['reason' => 'discrimination'])->assertForbidden();
        F::as($this->awa)->postJson('/api/v1/forums/replies/'.$reply->id.'/report', ['reason' => 'discrimination'])->assertCreated();
        F::as(F::member())->postJson('/api/v1/forums/replies/'.$reply->id.'/report', ['reason' => 'discrimination'])->assertCreated();

        expect(ModerationReport::query()->first()->kind)->toBe(ModerationKind::Reply)
            ->and($reply->refresh()->status)->toBe(PostStatus::Hidden)
            ->and($topic->refresh()->replies_count)->toBe(0);
        F::as(F::member())->postJson('/api/v1/forums/replies/'.$reply->id.'/report', ['reason' => 'discrimination'])->assertNotFound();
    });
});
