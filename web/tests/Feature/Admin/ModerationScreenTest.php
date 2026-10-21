<?php

declare(strict_types=1);

use App\Application\Moderation\ReportContent;
use App\Enums\ContentReportReason;
use App\Enums\ModerationKind;
use App\Enums\ModerationStatus;
use App\Enums\PostStatus;
use App\Enums\UserStatus;
use App\Livewire\Admin\Moderation\Index as ModerationScreen;
use App\Models\AuditLog;
use App\Models\BadWord;
use App\Models\Comment;
use App\Models\Conversation;
use App\Models\Forum;
use App\Models\ForumTopic;
use App\Models\Message;
use App\Models\ModerationReport;
use App\Models\Post;
use App\Models\User;
use App\Models\UserWarning;
use App\Notifications\ModerationNotice;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/*
 * P12-06 : Modération /admin/moderation (Livewire) — `moderation.manage` (admin, modérateur, superadmin ; 403 pair et
 * membre), une ligne par contenu signalé, RG-16 (masquage après trois signalements, confirmé ou rétabli par la décision),
 * décisions ignorer · masquer · avertir (compteur user_warnings) · suspendre (jamais un compte d'administration) ·
 * rouvrir · rendre visible, lecture du seul message signalé (Q74), mots interdits ; chaque décision est journalisée et
 * notifiée à l'auteur·e.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function modUser(string $role): User
{
    $user = User::factory()->create();
    $user->syncRoles([$role]);

    return $user;
}

function modScreen(string $role = 'moderateur', ?User $user = null)
{
    test()->actingAs($user ?? modUser($role));

    return Livewire::test(ModerationScreen::class);
}

/** @return array{0: Post, 1: User, 2: ModerationReport} */
function modPostReport(array $report = [], array $post = []): array
{
    $author = User::factory()->create();
    $author->syncRoles(['membre']);
    $post = Post::factory()->create($post + ['user_id' => $author->id, 'body' => 'Un propos à relire avec soin']);
    $report = ModerationReport::factory()->forSubject($post)->create($report + ['reported_user_id' => $author->id, 'reason' => 'Insultes', 'excerpt' => 'Un propos à relire avec soin']);

    return [$post, $author, $report];
}

it('sert l’écran à l’admin, au modérateur et au superadmin, le refuse au pair et au membre', function (): void {
    foreach (['admin', 'moderateur', 'superadmin'] as $role) {
        $html = $this->actingAs(modUser($role))->get(route('admin.moderation'))
            ->assertOk()
            ->assertSeeLivewire(ModerationScreen::class)
            ->assertSee('File de modération')
            ->assertSee('Charte de bienveillance')
            ->getContent();
        expect($html)->toMatch('/data-xm-nav="moderation"\s+aria-current="page"/');
    }

    foreach (['pair', 'membre'] as $role) {
        $this->actingAs(modUser($role))->get(route('admin.moderation'))->assertForbidden();
    }
});

it('liste un contenu signalé par ligne, avec le nombre de personnes, et filtre par statut, nature et recherche', function (): void {
    [$post] = modPostReport();
    foreach (['a', 'b'] as $i) {
        ModerationReport::factory()->forSubject($post)->create(['reason' => 'Spam', 'reporter_user_id' => User::factory()->create()->id]);
    }
    $comment = Comment::factory()->create(['body' => 'Commentaire douteux']);
    ModerationReport::factory()->forSubject($comment)->create(['excerpt' => 'Commentaire douteux', 'reason' => 'Harcèlement']);
    ModerationReport::factory()->forSubject(Post::factory()->create())->create(['status' => ModerationStatus::Handled, 'action' => 'masque', 'excerpt' => 'Déjà traité', 'handled_at' => now()]);

    modScreen()
        ->assertViewHas('rows', fn ($rows): bool => $rows->total() === 2)
        ->assertSee('Un propos à relire avec soin')
        ->assertSee('Commentaire douteux')
        ->assertDontSee('Déjà traité')
        ->set('kind', 'commentaire')
        ->assertSee('Commentaire douteux')
        ->assertDontSee('Un propos à relire avec soin')
        ->set('kind', '')
        ->set('search', 'douteux')
        ->assertViewHas('rows', fn ($rows): bool => $rows->total() === 1)
        ->set('search', '')
        ->set('filter', 'handled')
        ->assertSee('Déjà traité')
        ->assertDontSee('Commentaire douteux')
        ->set('filter', 'all')
        ->assertViewHas('rows', fn ($rows): bool => $rows->total() === 5);
});

it('RG-16 : trois signalements masquent le contenu ; ignorer le rétablit, masquer le confirme', function (): void {
    Notification::fake();
    $author = User::factory()->create();
    $author->syncRoles(['membre']);
    $post = Post::factory()->create(['user_id' => $author->id, 'body' => 'Contenu très signalé']);
    $reports = app(ReportContent::class);

    foreach (range(1, 3) as $i) {
        $reporter = User::factory()->create();
        $reporter->syncRoles(['membre']);
        $reports->byMember($reporter, $post, ContentReportReason::Harassment);
    }

    expect($post->refresh()->status)->toBe(PostStatus::Hidden)
        ->and(ModerationReport::query()->pending()->count())->toBe(3);

    $report = ModerationReport::query()->pending()->orderBy('id')->firstOrFail();

    // La file ne montre qu’une ligne, marquée « Masqué », signalée par trois personnes.
    modScreen()
        ->assertViewHas('rows', fn ($rows): bool => $rows->total() === 1)
        ->assertSee('Masqué')
        ->assertSee('3 personnes')
        ->call('openDecision', $report->id, 'dismiss')
        ->call('decide')
        ->assertHasNoErrors();

    expect($post->refresh()->status)->toBe(PostStatus::Published)
        ->and(ModerationReport::query()->pending()->count())->toBe(0)
        ->and(ModerationReport::query()->where('status', ModerationStatus::Rejected)->where('action', 'aucune')->count())->toBe(3)
        ->and(AuditLog::query()->where('action', 'moderation.dismissed')->exists())->toBeTrue();

    // Après « ignorer », il faut de nouveaux signalements : trois de plus remasquent le contenu.
    foreach (range(1, 3) as $i) {
        $reporter = User::factory()->create();
        $reporter->syncRoles(['membre']);
        $reports->byMember($reporter, $post, ContentReportReason::Harassment);
    }
    expect($post->refresh()->status)->toBe(PostStatus::Hidden);

    $again = ModerationReport::query()->pending()->orderBy('id')->firstOrFail();
    modScreen()->call('openDecision', $again->id, 'hide')->call('decide');

    expect($post->refresh()->status)->toBe(PostStatus::Hidden)
        ->and(ModerationReport::query()->where('action', 'masque')->where('status', ModerationStatus::Handled)->count())->toBe(3);
    Notification::assertSentTo($author, ModerationNotice::class, fn (ModerationNotice $n): bool => $n->kind === ModerationNotice::CONTENT_HIDDEN);
});

it('masque une publication, un commentaire et un sujet de forum, compteurs recalculés, et prévient l’auteur·e', function (): void {
    Notification::fake();
    $post = Post::factory()->create(['comments_count' => 1]);
    $comment = Comment::factory()->create(['post_id' => $post->id]);
    $forum = Forum::factory()->create(['topics_count' => 1]);
    $topic = ForumTopic::factory()->create(['forum_id' => $forum->id]);
    $forum->forceFill(['topics_count' => 1])->save();

    $onComment = ModerationReport::factory()->forSubject($comment)->create();
    $onTopic = ModerationReport::factory()->forSubject($topic)->create();
    $onPost = ModerationReport::factory()->forSubject($post)->create();

    foreach ([$onComment, $onTopic, $onPost] as $report) {
        modScreen()->call('openDecision', $report->id, 'hide')->call('decide')->assertHasNoErrors();
    }

    expect($comment->refresh()->status)->toBe(PostStatus::Hidden)
        ->and($post->refresh()->status)->toBe(PostStatus::Hidden)
        ->and($post->comments_count)->toBe(0)
        ->and($topic->refresh()->status)->toBe(PostStatus::Hidden)
        ->and($forum->refresh()->topics_count)->toBe(0)
        ->and(AuditLog::query()->where('action', 'moderation.hidden')->count())->toBe(3);
    Notification::assertSentTo($post->user, ModerationNotice::class);
});

it('avertit l’auteur·e : motif obligatoire, ligne user_warnings, compteur, notification et journal', function (): void {
    Notification::fake();
    [, $author, $report] = modPostReport();
    $moderator = modUser('moderateur');

    $screen = modScreen(user: $moderator)->call('openDecision', $report->id, 'warn')->call('decide')->assertHasErrors(['reason' => 'required']);
    expect(UserWarning::query()->count())->toBe(0);

    $screen->set('reason', 'Ton message est insultant')->call('decide')->assertHasNoErrors();

    $warning = UserWarning::query()->firstOrFail();
    expect($warning->user_id)->toBe($author->id)
        ->and($warning->issued_by)->toBe($moderator->id)
        ->and($warning->moderation_report_id)->toBe($report->id)
        ->and($warning->reason)->toBe('Ton message est insultant')
        ->and($report->refresh()->status)->toBe(ModerationStatus::Handled)
        ->and($report->action)->toBe('avertissement')
        ->and($report->handled_by)->toBe($moderator->id)
        ->and(AuditLog::query()->where('action', 'moderation.warned')->where('user_id', $moderator->id)->exists())->toBeTrue();
    Notification::assertSentTo($author, ModerationNotice::class, fn (ModerationNotice $n): bool => $n->kind === ModerationNotice::WARNING && $n->warningCount === 1 && $n->reason === 'Ton message est insultant');

    // Deuxième avertissement sur un autre signalement : le compteur monte à 2 (affiché dans la file des traités).
    [, , $second] = modPostReport(['reported_user_id' => $author->id]);
    ModerationReport::query()->whereKey($second->id)->update(['reported_user_id' => $author->id]);
    modScreen(user: $moderator)->call('openDecision', $second->id, 'warn')->set('reason', 'Récidive')->call('decide');
    expect(UserWarning::query()->forUser($author)->count())->toBe(2);
    Notification::assertSentTo($author, ModerationNotice::class, fn (ModerationNotice $n): bool => $n->warningCount === 2);
    modScreen(user: $moderator)->set('filter', 'handled')->assertSee('2 avertissements');
});

it('suspend l’auteur·e (motif obligatoire), ferme ses accès et clôt tous les signalements du contenu', function (): void {
    Notification::fake();
    [$post, $author, $report] = modPostReport();
    ModerationReport::factory()->forSubject($post)->create(['reported_user_id' => $author->id]);
    $author->createToken('mobile');
    DB::table('sessions')->insert(['id' => 'sess-a', 'user_id' => $author->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'test', 'payload' => '', 'last_activity' => time()]);

    $screen = modScreen()->call('openDecision', $report->id, 'suspend')->call('decide')->assertHasErrors(['reason' => 'required']);
    $screen->set('reason', 'Harcèlement répété')->call('decide')->assertHasNoErrors();

    expect($author->refresh()->status)->toBe(UserStatus::Suspended)
        ->and($author->suspension_reason)->toBe('Harcèlement répété')
        ->and($author->tokens()->count())->toBe(0)
        ->and(DB::table('sessions')->where('user_id', $author->id)->count())->toBe(0)
        ->and(ModerationReport::query()->pending()->count())->toBe(0)
        ->and(ModerationReport::query()->where('action', 'suspension')->count())->toBe(2)
        ->and(AuditLog::query()->where('action', 'moderation.suspended')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'users.suspended')->exists())->toBeTrue();
    Notification::assertSentTo($author, ModerationNotice::class, fn (ModerationNotice $n): bool => $n->kind === ModerationNotice::SUSPENDED);
});

it('ne suspend jamais un compte d’administration depuis la modération, même pour un superadmin', function (): void {
    foreach (['moderateur', 'admin', 'superadmin'] as $role) {
        $author = modUser($role);
        $post = Post::factory()->create(['user_id' => $author->id]);
        $report = ModerationReport::factory()->forSubject($post)->create(['reported_user_id' => $author->id]);

        foreach (['moderateur', 'superadmin'] as $actorRole) {
            modScreen($actorRole)
                ->call('openDecision', $report->id, 'suspend')
                ->set('reason', 'Test')
                ->call('decide')
                ->assertDispatched('toast', type: 'error', message: 'Un compte d’administration ne peut pas être suspendu depuis la modération.');
        }

        expect($author->refresh()->status)->toBe(UserStatus::Active)->and($report->refresh()->isPending())->toBeTrue();
    }

    // Le bouton est désactivé dans la file.
    [, $admin] = [null, modUser('admin')];
    $post = Post::factory()->create(['user_id' => $admin->id]);
    ModerationReport::factory()->forSubject($post)->create(['reported_user_id' => $admin->id]);
    modScreen()->assertSee('aria-disabled="true"', false);
});

it('refuse une décision sur un signalement déjà traité et sur un auteur introuvable', function (): void {
    [, , $report] = modPostReport();
    $screen = modScreen()->call('openDecision', $report->id, 'dismiss');
    ModerationReport::query()->whereKey($report->id)->update(['status' => ModerationStatus::Handled, 'action' => 'masque']);
    $screen->call('decide')->assertDispatched('toast', type: 'error', message: 'Ce signalement a déjà été traité.');

    $orphan = ModerationReport::factory()->forSubject(Post::factory()->create())->create(['reported_user_id' => null]);
    ModerationReport::query()->whereKey($orphan->id)->update(['reported_user_id' => null]);
    modScreen()->call('openDecision', $orphan->id, 'warn')->set('reason', 'Test')->call('decide')
        ->assertDispatched('toast', type: 'error', message: 'L’auteur·e de ce contenu est introuvable.');
});

it('ne propose pas de masquer un compte signalé : seuls ignorer, avertir et suspendre', function (): void {
    $user = User::factory()->create();
    $user->syncRoles(['membre']);
    $report = ModerationReport::factory()->forSubject($user)->create(['kind' => ModerationKind::User, 'reported_user_id' => $user->id, 'excerpt' => null]);

    modScreen()->call('openDecision', $report->id, 'hide')->call('decide')
        ->assertDispatched('toast', type: 'error', message: 'Ce signalement ne vise pas un contenu que l’on peut masquer.');

    modScreen()->call('openDecision', $report->id, 'warn')->set('reason', 'Messages non sollicités')->call('decide')->assertHasNoErrors();
    expect(UserWarning::query()->forUser($user)->count())->toBe(1);
});

it('rouvre un signalement traité sans défaire l’avertissement, et rend un contenu masqué visible', function (): void {
    Notification::fake();
    [$post, $author, $report] = modPostReport();
    modScreen()->call('openDecision', $report->id, 'hide')->call('decide');
    expect($post->refresh()->status)->toBe(PostStatus::Hidden);

    modScreen()->set('filter', 'handled')->assertSee('Rendre visible')->call('unhide', $report->id);
    expect($post->refresh()->status)->toBe(PostStatus::Published)
        ->and(AuditLog::query()->where('action', 'moderation.unhidden')->exists())->toBeTrue();

    modScreen()->call('reopen', $report->id);
    expect($report->refresh()->isPending())->toBeTrue()
        ->and($report->action)->toBeNull()
        ->and($report->handled_by)->toBeNull()
        ->and(AuditLog::query()->where('action', 'moderation.reopened')->exists())->toBeTrue();

    // Rouvrir un signalement en attente est refusé ; l’avertissement déjà décidé reste.
    modScreen()->call('reopen', $report->id)->assertDispatched('toast', type: 'error', message: 'Ce signalement est déjà en attente.');

    [, $other, $warned] = modPostReport();
    modScreen()->call('openDecision', $warned->id, 'warn')->set('reason', 'Propos déplacés')->call('decide');
    modScreen()->call('reopen', $warned->id);
    expect(UserWarning::query()->forUser($other)->count())->toBe(1)->and($warned->refresh()->isPending())->toBeTrue();
});

it('lit le seul message privé signalé, journalise la lecture sans le texte, et la refuse une fois le signalement traité', function (): void {
    Notification::fake();
    $author = User::factory()->create(['pseudo' => 'koffi_m']);
    $author->syncRoles(['membre']);
    $conversation = Conversation::factory()->create();
    $other = Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $author->id, 'body' => 'Un autre message de la même conversation']);
    $message = Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $author->id, 'body' => 'Texte secret du message signalé']);
    $report = ModerationReport::factory()->forSubject($message)->create(['kind' => ModerationKind::Message, 'excerpt' => null, 'reported_user_id' => $author->id, 'reason' => 'Harcèlement']);
    $moderator = modUser('moderateur');

    modScreen(user: $moderator)
        ->assertSee('Message privé')
        ->assertDontSee('Texte secret du message signalé')
        ->call('openRead', $report->id)
        ->assertSee('Texte secret du message signalé')
        ->assertSee('koffi_m')
        ->assertDontSee('Un autre message de la même conversation')
        ->call('closeRead')
        ->assertDontSee('Texte secret du message signalé');

    $log = AuditLog::query()->where('action', 'moderation.message_read')->firstOrFail();
    expect($log->user_id)->toBe($moderator->id)
        ->and($log->detail)->toContain('rapport='.$report->id)
        ->and($log->detail)->toContain('message='.$message->id)
        ->and($log->detail)->not->toContain('secret');

    // Masquer le message le retire pour tout le monde ; le signalement traité n’ouvre plus la lecture.
    modScreen(user: $moderator)->call('openDecision', $report->id, 'hide')->call('decide');
    expect($message->refresh()->deleted_for_everyone_at)->not->toBeNull()->and($other->refresh()->deleted_for_everyone_at)->toBeNull();

    modScreen(user: $moderator)->call('openRead', $report->id)->assertDispatched('toast', type: 'error', message: 'Ce message ne peut pas être lu : le signalement est déjà traité ou le message n’existe plus.');
    expect(AuditLog::query()->where('action', 'moderation.message_read')->count())->toBe(1);
});

it('ne laisse lire un message signalé qu’à la modération', function (): void {
    $message = Message::factory()->create(['body' => 'Texte secret']);
    $report = ModerationReport::factory()->forSubject($message)->create(['kind' => ModerationKind::Message, 'excerpt' => null]);

    $this->actingAs(modUser('membre'));
    Livewire::test(ModerationScreen::class)->assertForbidden();

    expect(AuditLog::query()->where('action', 'moderation.message_read')->count())->toBe(0);

    // Un signalement de publication n’ouvre pas la lecture d’un message.
    [, , $postReport] = modPostReport();
    modScreen()->call('openRead', $postReport->id)->assertDispatched('toast', type: 'error');
    expect($report->refresh()->isPending())->toBeTrue();
});

it('gère les mots interdits : ajout normalisé, doublon, gravité, retrait et journal', function (): void {
    modScreen()
        ->set('wordText', 'x')
        ->call('addWord')
        ->assertHasErrors(['wordText'])
        ->set('wordText', '  Argent   FACILE ')
        ->set('wordSeverity', 'bloquer')
        ->call('addWord')
        ->assertHasNoErrors()
        ->assertSee('argent facile')
        ->set('wordText', 'argent facile')
        ->call('addWord')
        ->assertDispatched('toast', type: 'warning', message: 'Ce mot est déjà dans la liste.')
        ->set('wordText', 'tarée')
        ->set('wordSeverity', 'inconnue')
        ->call('addWord')
        ->assertHasErrors(['wordSeverity']);

    $word = BadWord::query()->where('word', 'argent facile')->firstOrFail();
    expect($word->severity->value)->toBe('bloquer')->and(AuditLog::query()->where('action', 'moderation.word_added')->exists())->toBeTrue();

    modScreen()->call('removeWord', $word->id);
    expect(BadWord::query()->count())->toBe(0)->and(AuditLog::query()->where('action', 'moderation.word_removed')->exists())->toBeTrue();
});

it('affiche les suspensions récentes et un état vide', function (): void {
    modScreen()->assertSee('Rien à modérer ici')->assertSee('Aucun compte suspendu.');

    User::factory()->suspended('Spam répété')->create(['pseudo' => 'zoe_sus']);

    modScreen()->assertSee('zoe_sus')->assertSee('Spam répété');
});
