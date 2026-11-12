<?php

declare(strict_types=1);

use App\Application\Admin\Users\AccountBan;
use App\Application\Moderation\ReportContent;
use App\Domain\Account\Exceptions\AccountActionRefused;
use App\Domain\Moderation\ReportPriority;
use App\Enums\ContentReportReason;
use App\Enums\ModerationKind;
use App\Enums\ModerationStatus;
use App\Enums\PostStatus;
use App\Enums\UserStatus;
use App\Livewire\Admin\Journal\Index as JournalScreen;
use App\Livewire\Admin\Moderation\Index as ModerationScreen;
use App\Models\AuditLog;
use App\Models\Comment;
use App\Models\Forum;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\ModerationReport;
use App\Models\Post;
use App\Models\User;
use App\Notifications\ModerationNotice;
use Database\Seeders\EmailTemplatesSeeder;
use Database\Seeders\ForumsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/*
 * P18-04 à P18-07 : score de priorité (formule, recalcul, tri par défaut, badge), contexte (fil, historique de
 * l'auteur·rice ; rien de plus pour un message privé), « Approuver » distinct d'« Ignorer », suspension temporaire et
 * levée planifiée, bannissement (motif obligatoire, connexion refusée web et API avec message neutre, accès révoqués,
 * levée par le superadmin seul), filtre « par modérateur·rice » du journal.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function p18User(string $role = 'membre', array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    $user->syncRoles([$role]);

    return $user;
}

function p18Report(Post|Comment|ForumTopic|ForumReply $subject, ContentReportReason $reason, ?User $reporter = null): ModerationReport
{
    return app(ReportContent::class)->byMember($reporter ?? p18User(), $subject, $reason)->report->refresh();
}

describe('score de priorité', function (): void {
    it('applique la formule : gravité + nature + personnes (plafond 30) + récidive (plafond 20)', function (): void {
        expect((new ReportPriority([ContentReportReason::Threats], 1, ModerationKind::Post, 0))->score())->toBe(60)
            ->and((new ReportPriority([ContentReportReason::Spam], 1, ModerationKind::Comment, 0))->score())->toBe(10)
            ->and((new ReportPriority([ContentReportReason::Spam, ContentReportReason::Harassment], 2, ModerationKind::Reply, 0))->score())->toBe(55)
            ->and((new ReportPriority([ContentReportReason::Other], 9, ModerationKind::Post, 0))->score())->toBe(15 + 10 + 30)
            ->and((new ReportPriority([ContentReportReason::Other], 1, ModerationKind::Post, 10))->score())->toBe(15 + 10 + 20)
            ->and((new ReportPriority([], 0, ModerationKind::Message, 0))->score())->toBe(25)
            ->and(ReportPriority::level(60))->toBe('haute')
            ->and(ReportPriority::level(59))->toBe('moyenne')
            ->and(ReportPriority::level(35))->toBe('moyenne')
            ->and(ReportPriority::level(34))->toBe('basse');
    });

    it('reconnaît le motif rangé en libellé, en valeur ou en libellé importé', function (): void {
        expect(ContentReportReason::fromStored('Harcèlement ou insultes'))->toBe(ContentReportReason::Harassment)
            ->and(ContentReportReason::fromStored('menaces'))->toBe(ContentReportReason::Threats)
            ->and(ContentReportReason::fromStored('SPAM'))->toBe(ContentReportReason::Spam)
            ->and(ContentReportReason::fromStored('Libellé inconnu'))->toBeNull();
    });

    it('note chaque nouveau signalement et renote tous les signalements en attente du contenu', function (): void {
        $post = Post::factory()->create(['user_id' => p18User()->id]);

        $first = p18Report($post, ContentReportReason::Harassment);
        expect($first->priority)->toBe(50)->and($first->priorityLevel())->toBe('moyenne');

        p18Report($post, ContentReportReason::Spam);
        expect($first->refresh()->priority)->toBe(60)
            ->and(ModerationReport::query()->forSubject($post)->pluck('priority')->unique()->all())->toBe([60]);
    });

    it('tient compte de la récidive : une sanction sur un autre contenu fait monter les contenus en attente', function (): void {
        $author = p18User();
        $old = Post::factory()->create(['user_id' => $author->id]);
        $new = Post::factory()->create(['user_id' => $author->id]);
        $oldReport = p18Report($old, ContentReportReason::Spam);
        $newReport = p18Report($new, ContentReportReason::Spam);
        expect($newReport->priority)->toBe(15);

        $this->actingAs(p18User('moderateur'));
        Livewire::test(ModerationScreen::class)->call('openDecision', $oldReport->id, 'hide')->call('decide');

        expect($newReport->refresh()->priority)->toBe(20);
    });

    it('trie la file par score décroissant par défaut et affiche le badge de priorité', function (): void {
        $low = p18Report(Post::factory()->create(['user_id' => p18User()->id, 'body' => 'Contenu peu grave']), ContentReportReason::Spam);
        $high = p18Report(Post::factory()->create(['user_id' => p18User()->id, 'body' => 'Contenu menaçant']), ContentReportReason::Threats);
        expect($high->priority)->toBeGreaterThan($low->priority);

        $this->actingAs(p18User('moderateur'));
        Livewire::test(ModerationScreen::class)
            ->assertViewHas('sortKey', 'priorite')
            ->assertViewHas('rows', fn ($rows): bool => $rows->getCollection()->pluck('id')->all() === [$high->id, $low->id])
            ->assertSee('Haute')
            ->assertSee('Basse')
            ->assertSeeInOrder(['Contenu menaçant', 'Contenu peu grave'])
            ->set('sort', 'date')->set('direction', 'asc')
            ->assertViewHas('rows', fn ($rows): bool => $rows->getCollection()->pluck('id')->all() === [$low->id, $high->id]);
    });

    it('rattrape les signalements existants par la commande moderation:priorities', function (): void {
        $post = Post::factory()->create();
        ModerationReport::factory()->forSubject($post)->create(['reason' => 'Menaces', 'priority' => 0, 'status' => ModerationStatus::Pending]);

        $this->artisan('moderation:priorities')->expectsOutputToContain('1 contenu noté')->assertSuccessful();

        expect(ModerationReport::query()->value('priority'))->toBe(60);
    });

    it('trie aussi la file de l’API admin mobile et expose le score', function (): void {
        $low = p18Report(Post::factory()->create(['user_id' => p18User()->id]), ContentReportReason::Spam);
        $high = p18Report(Post::factory()->create(['user_id' => p18User()->id]), ContentReportReason::Threats);
        $admin = p18User('admin');

        $this->withToken($admin->createToken('Pixel 7')->plainTextToken)->getJson('/api/v1/admin/moderation/reports')
            ->assertOk()
            ->assertJsonPath('data.0.id', $high->id)
            ->assertJsonPath('data.0.priority_level', 'haute')
            ->assertJsonPath('data.1.id', $low->id);
    })->skip(fn (): bool => ! app('router')->has('api.admin.moderation.index') && ! str_contains((string) json_encode(app('router')->getRoutes()->getRoutesByName()), 'moderation/reports'), 'API admin mobile absente');
});

describe('contexte de la décision', function (): void {
    it('montre la publication commentée et l’historique de l’auteur·e, sans extrait des autres contenus', function (): void {
        $author = p18User();
        $post = Post::factory()->create(['body' => 'Publication de départ sur la santé']);
        $comment = Comment::factory()->create(['post_id' => $post->id, 'user_id' => $author->id, 'body' => 'Commentaire signalé']);
        $previous = Post::factory()->create(['user_id' => $author->id, 'body' => 'Ancien contenu secret']);
        ModerationReport::factory()->forSubject($previous)->create(['reported_user_id' => $author->id, 'status' => ModerationStatus::Handled, 'action' => 'masque', 'handled_at' => now()->subDay(), 'excerpt' => 'Ancien contenu secret']);
        $report = p18Report($comment, ContentReportReason::Harassment);

        $this->actingAs(p18User('moderateur'));
        Livewire::test(ModerationScreen::class)
            ->call('openDecision', $report->id, 'hide')
            ->assertSee('Contexte')
            ->assertSee('Publication de départ sur la santé')
            ->assertSee('Historique de modération de l’auteur·e')
            ->assertSee('Masqué × 1');

        $context = app(App\Application\Moderation\ReportContext::class)->for($report);
        expect($context['parent'])->toMatchArray(['type' => 'post', 'excerpt' => 'Publication de départ sur la santé'])
            ->and($context['history']['sanctions'])->toBe(['masque' => 1])
            ->and(json_encode($context['history']))->not->toContain('Ancien contenu secret');
    });

    it('montre le sujet d’une réponse de forum et aucun contexte pour un message privé', function (): void {
        $this->seed(ForumsSeeder::class);
        $topic = ForumTopic::factory()->create(['forum_id' => Forum::query()->forKey('f2')->value('id'), 'title' => 'Porter plainte à Cotonou']);
        $reply = ForumReply::factory()->create(['topic_id' => $topic->id]);
        $report = p18Report($reply, ContentReportReason::Misinformation);

        expect(app(App\Application\Moderation\ReportContext::class)->for($report)['parent'])
            ->toMatchArray(['type' => 'topic', 'title' => 'Porter plainte à Cotonou']);

        $message = ModerationReport::factory()->create(['kind' => ModerationKind::Message, 'reported_user_id' => p18User()->id]);
        expect(app(App\Application\Moderation\ReportContext::class)->for($message)['parent'])->toBeNull();
    });
});

describe('actions', function (): void {
    it('« Approuver » rend visible un contenu masqué par décision et rejette les signalements avec l’action approuve', function (): void {
        $post = Post::factory()->create(['user_id' => p18User()->id, 'status' => PostStatus::Hidden]);
        ModerationReport::factory()->forSubject($post)->create(['status' => ModerationStatus::Handled, 'action' => 'masque', 'handled_at' => now()]);
        $report = p18Report($post, ContentReportReason::Other);

        $this->actingAs(p18User('moderateur'));
        Livewire::test(ModerationScreen::class)->call('openDecision', $report->id, 'approve')->call('decide')->assertHasNoErrors();

        expect($post->refresh()->status)->toBe(PostStatus::Published)
            ->and($report->refresh()->status)->toBe(ModerationStatus::Rejected)
            ->and($report->action)->toBe('approuve')
            ->and(AuditLog::query()->where('action', 'moderation.approved')->exists())->toBeTrue();
    });

    it('« Ignorer » reste distinct : un contenu masqué par décision n’est pas rendu visible', function (): void {
        $post = Post::factory()->create(['user_id' => p18User()->id, 'status' => PostStatus::Hidden]);
        ModerationReport::factory()->forSubject($post)->create(['status' => ModerationStatus::Handled, 'action' => 'masque', 'handled_at' => now()]);
        $report = p18Report($post, ContentReportReason::Other);

        $this->actingAs(p18User('moderateur'));
        Livewire::test(ModerationScreen::class)->call('openDecision', $report->id, 'dismiss')->call('decide');

        expect($post->refresh()->status)->toBe(PostStatus::Hidden)
            ->and($report->refresh()->action)->toBe('aucune')
            ->and(AuditLog::query()->where('action', 'moderation.dismissed')->exists())->toBeTrue();
    });

    it('suspend temporairement avec une date de fin, puis la commande planifiée réactive le compte', function (): void {
        Notification::fake();
        config(['xomiton.display_timezone' => 'Africa/Porto-Novo']);
        $author = p18User();
        $report = p18Report(Post::factory()->create(['user_id' => $author->id]), ContentReportReason::Harassment);

        $this->actingAs(p18User('moderateur'));
        $screen = Livewire::test(ModerationScreen::class)->call('openDecision', $report->id, 'suspend')->set('reason', 'Harcèlement répété');
        $screen->set('suspendUntil', now('Africa/Porto-Novo')->toDateString())->call('decide')->assertHasErrors(['suspendUntil']);
        $end = now('Africa/Porto-Novo')->addDays(3)->toDateString();
        $screen->set('suspendUntil', $end)->call('decide')->assertHasNoErrors();

        $author->refresh();
        expect($author->status)->toBe(UserStatus::Suspended)
            ->and($author->suspended_until?->copy()->setTimezone('Africa/Porto-Novo')->format('Y-m-d H:i'))->toBe($end.' 23:59');
        Notification::assertSentTo($author, ModerationNotice::class, fn (ModerationNotice $n): bool => $n->kind === ModerationNotice::SUSPENDED_UNTIL && $n->until !== null);

        $this->artisan('accounts:lift-suspensions')->expectsOutputToContain('0 compte réactivé')->assertSuccessful();
        expect($author->refresh()->status)->toBe(UserStatus::Suspended);

        $this->travelTo($author->suspended_until->copy()->addMinute());
        $this->artisan('accounts:lift-suspensions')->expectsOutputToContain('1 compte réactivé')->assertSuccessful();

        expect($author->refresh()->status)->toBe(UserStatus::Active)
            ->and($author->suspended_until)->toBeNull()
            ->and(AuditLog::query()->where('action', 'users.suspension_lifted')->where('actor', AuditLog::ACTOR_SYSTEM)->exists())->toBeTrue();
        Notification::assertSentTo($author, ModerationNotice::class, fn (ModerationNotice $n): bool => $n->kind === ModerationNotice::REACTIVATED);
    });

    it('ne lève jamais une suspension sans date de fin', function (): void {
        $author = p18User('membre', ['status' => UserStatus::Suspended, 'suspended_at' => now()->subYear()]);

        $this->artisan('accounts:lift-suspensions')->assertSuccessful();

        expect($author->refresh()->status)->toBe(UserStatus::Suspended);
    });

    it('bannit depuis la modération (admin) : motif obligatoire, accès révoqués, notification, journal', function (): void {
        Notification::fake();
        $author = p18User();
        $author->createToken('mobile');
        DB::table('sessions')->insert(['id' => 'sess-ban', 'user_id' => $author->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'test', 'payload' => '', 'last_activity' => time()]);
        $report = p18Report(Post::factory()->create(['user_id' => $author->id]), ContentReportReason::Threats);

        $this->actingAs(p18User('admin'));
        $screen = Livewire::test(ModerationScreen::class)->call('openDecision', $report->id, 'ban')->call('decide')->assertHasErrors(['reason' => 'required']);
        $screen->set('reason', 'Menaces graves et répétées')->call('decide')->assertHasNoErrors();

        $author->refresh();
        expect($author->getRawOriginal('status'))->toBe('banni')
            ->and($author->isBanned())->toBeTrue()
            ->and($author->ban_reason)->toBe('Menaces graves et répétées')
            ->and($author->tokens()->count())->toBe(0)
            ->and(DB::table('sessions')->where('user_id', $author->id)->count())->toBe(0)
            ->and($report->refresh()->action)->toBe('bannissement')
            ->and(AuditLog::query()->where('action', 'users.banned')->exists())->toBeTrue()
            ->and(AuditLog::query()->where('action', 'moderation.banned')->exists())->toBeTrue();
        Notification::assertSentTo($author, ModerationNotice::class, fn (ModerationNotice $n): bool => $n->kind === ModerationNotice::BANNED);
    });

    it('refuse le bannissement au modérateur et ne bannit jamais un compte d’administration', function (): void {
        $author = p18User();
        $report = p18Report(Post::factory()->create(['user_id' => $author->id]), ContentReportReason::Threats);

        $this->actingAs(p18User('moderateur'));
        Livewire::test(ModerationScreen::class)
            ->assertDontSee('Bannir')
            ->call('openDecision', $report->id, 'ban')
            ->assertDispatched('toast', type: 'error', message: 'Vous n’avez pas le droit de bannir ce compte.')
            ->assertSet('reportId', null);

        $staff = p18User('moderateur');
        $staffReport = p18Report(Post::factory()->create(['user_id' => $staff->id]), ContentReportReason::Threats);
        $this->actingAs(p18User('superadmin'));
        Livewire::test(ModerationScreen::class)->call('openDecision', $staffReport->id, 'ban')->set('reason', 'Essai')->call('decide')
            ->assertDispatched('toast', type: 'error');

        expect($author->refresh()->isBanned())->toBeFalse()->and($staff->refresh()->isBanned())->toBeFalse();
    });

    it('refuse la connexion d’un compte banni sur le web et l’API avec un message neutre, après le mot de passe', function (): void {
        $this->seed([EmailTemplatesSeeder::class, SettingsSeeder::class]);
        Mail::fake();
        config(['mail.default' => 'array']);
        $user = p18User('membre', ['email' => 'banni@exemple.bj', 'password' => 'Xomiton2026!', 'status' => 'banni', 'banned_at' => now(), 'ban_reason' => 'Motif interne']);

        $this->postJson('/api/v1/auth/login', ['login' => 'banni@exemple.bj', 'password' => 'mauvais', 'device_name' => 'Pixel 7'])
            ->assertUnprocessable()->assertJsonPath('code', 'invalid_credentials');

        $this->postJson('/api/v1/auth/login', ['login' => 'banni@exemple.bj', 'password' => 'Xomiton2026!', 'device_name' => 'Pixel 7'])
            ->assertForbidden()
            ->assertJsonPath('code', 'account_unavailable')
            ->assertJsonPath('message', fn (string $m): bool => str_contains($m, 'Connexion impossible') && ! str_contains($m, 'banni') && ! str_contains($m, 'Motif interne'));

        $this->post('/connexion', ['identifier' => 'banni@exemple.bj', 'password' => 'Xomiton2026!'])
            ->assertSessionHasErrors('identifier');
        $this->assertGuest();

        expect(AuditLog::query()->where('action', 'auth.refused_banned')->count())->toBe(2)
            ->and($user->tokens()->count())->toBe(0);
    });

    it('ne laisse lever un bannissement qu’au superadmin', function (): void {
        $target = p18User('membre', ['status' => 'banni', 'banned_at' => now(), 'ban_reason' => 'Test']);

        expect(fn () => app(AccountBan::class)->unban($target, p18User('admin')))->toThrow(AuthorizationException::class);

        app(AccountBan::class)->unban($target, p18User('superadmin'));

        expect($target->refresh()->status)->toBe(UserStatus::Active)
            ->and($target->ban_reason)->toBeNull()
            ->and(AuditLog::query()->where('action', 'users.unbanned')->exists())->toBeTrue();
        expect(fn () => app(AccountBan::class)->unban($target, p18User('superadmin')))->toThrow(AccountActionRefused::class);
    });

    it('retire un compte banni des contacts de messagerie comme un compte suspendu', function (): void {
        $banned = p18User('membre', ['status' => 'banni']);

        expect(app(App\Application\Messaging\MessagingContacts::class)->reachable($banned))->toBeFalse();
    });
});

describe('journal par modérateur·rice', function (): void {
    it('filtre le journal d’audit par compte de l’équipe de modération', function (): void {
        $alice = p18User('moderateur', ['pseudo' => 'Alice_mod']);
        $bob = p18User('admin', ['pseudo' => 'Bob_admin']);
        AuditLog::query()->create(['user_id' => $alice->id, 'actor' => 'Alice_mod', 'action' => 'moderation.hidden', 'detail' => 'rapport=1']);
        AuditLog::query()->create(['user_id' => $bob->id, 'actor' => 'Bob_admin', 'action' => 'moderation.warned', 'detail' => 'rapport=2']);

        $this->actingAs($bob);
        Livewire::test(JournalScreen::class)
            ->assertSee('Alice_mod')
            ->set('moderator', (string) $alice->id)
            ->assertViewHas('rows', fn ($rows): bool => $rows->total() === 1 && $rows->first()->action === 'moderation.hidden')
            ->set('moderator', 'abc')
            ->assertViewHas('rows', fn ($rows): bool => $rows->total() >= 2);

        expect(collect(Livewire::test(JournalScreen::class)->instance()->moderators())->pluck('pseudo')->all())->toContain('Alice_mod', 'Bob_admin');
    });
});
