<?php

declare(strict_types=1);

use App\Application\Moderation\ReportContent;
use App\Domain\Moderation\Events\ContentReported;
use App\Enums\ContentReportReason;
use App\Enums\ModerationKind;
use App\Enums\ModerationStatus;
use App\Enums\PostStatus;
use App\Models\Comment;
use App\Models\ModerationReport;
use App\Models\Post;
use App\Notifications\ContentReported as ContentReportedNotification;
use App\Support\Settings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\Support\FeedFixtures;

/*
 * P7-02, FR-SOC-06, RG-16 : POST /api/v1/posts/{ulid}/report et comments/{id}/report — un signalement par personne,
 * événement ContentReported (cloche de la modération), masquage automatique au seuil paramétrable (config, Settings,
 * 0 = désactivé), compteur `reports_count`, 403 sur son propre contenu, 422 motif inconnu.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->author = FeedFixtures::member();
    $this->post = Post::factory()->for($this->author)->create(['body' => 'Contenu à relire par la modération.']);
});

function reportPost(Post $post, ?App\Models\User $reporter = null, string $reason = 'harcelement'): Illuminate\Testing\TestResponse
{
    return FeedFixtures::as($reporter ?? FeedFixtures::member())->postJson('/api/v1/posts/'.$post->ulid.'/report', ['reason' => $reason]);
}

it('crée un signalement (201), prévient la modération et ne compte jamais deux fois la même personne (200)', function (): void {
    $moderator = FeedFixtures::member('moderateur');
    Notification::fake();
    $reporter = FeedFixtures::member();

    FeedFixtures::as($reporter)->postJson('/api/v1/posts/'.$this->post->ulid.'/report', ['reason' => 'menaces', 'details' => 'Message reçu hier.'])
        ->assertCreated()->assertJsonPath('data', ['reported' => true, 'reason' => 'menaces']);
    reportPost($this->post, $reporter)->assertOk();

    $report = ModerationReport::query()->sole();
    expect($report->kind)->toBe(ModerationKind::Post)
        ->and($report->status)->toBe(ModerationStatus::Pending)
        ->and($report->reason)->toBe(ContentReportReason::Threats->label())
        ->and($report->reporter_user_id)->toBe($reporter->id)
        ->and($report->reported_user_id)->toBe($this->author->id)
        ->and($report->excerpt)->toBe('Contenu à relire par la modération.')
        ->and($this->post->refresh()->reports_count)->toBe(1);
    Notification::assertSentTo($moderator, ContentReportedNotification::class);
});

it('masque la publication au 3e signalement de personnes distinctes (RG-16), pas avant', function (): void {
    reportPost($this->post)->assertCreated();
    reportPost($this->post)->assertCreated();
    expect($this->post->refresh()->status)->toBe(PostStatus::Published);

    reportPost($this->post)->assertCreated()->assertJsonMissingPath('data.hidden');

    $this->post->refresh();
    expect($this->post->status)->toBe(PostStatus::Hidden)
        ->and($this->post->hidden_reason)->toBe(__('feed.moderation.auto_hidden_reason', ['count' => 3]))
        ->and($this->post->reports_count)->toBe(3);
    $this->assertDatabaseHas('audit_logs', ['action' => 'moderation.auto_hidden', 'subject_id' => $this->post->id, 'actor' => 'système']);

    $reader = FeedFixtures::member();
    FeedFixtures::as($reader)->getJson('/api/v1/posts/'.$this->post->ulid)->assertNotFound();
    expect(FeedFixtures::as($reader)->getJson('/api/v1/feed')->json('data'))->toBe([]);
});

it('lit le seuil dans la configuration puis dans les paramètres, et le désactive à 0', function (): void {
    config(['xomiton.feed.auto_hide_after_reports' => 2]);
    expect(ReportContent::autoHideRule()->threshold)->toBe(2);

    Settings::set(ReportContent::SETTING_THRESHOLD, 0);
    expect(ReportContent::autoHideRule()->isEnabled())->toBeFalse();

    foreach (range(1, 4) as $ignored) {
        reportPost($this->post)->assertCreated();
    }
    expect($this->post->refresh()->status)->toBe(PostStatus::Published)->and($this->post->reports_count)->toBe(4);

    Settings::set(ReportContent::SETTING_THRESHOLD, 5);
    reportPost($this->post)->assertCreated();
    expect($this->post->refresh()->status)->toBe(PostStatus::Hidden);
});

it('ne compte que les signalements en attente : après un rejet, il faut de nouveaux signalements', function (): void {
    config(['xomiton.feed.auto_hide_after_reports' => 2]);
    reportPost($this->post)->assertCreated();
    ModerationReport::query()->update(['status' => ModerationStatus::Rejected]);

    reportPost($this->post)->assertCreated();
    expect($this->post->refresh()->status)->toBe(PostStatus::Published);
    reportPost($this->post)->assertCreated();
    expect($this->post->refresh()->status)->toBe(PostStatus::Hidden);
});

it('masque un commentaire signalé au seuil et recalcule le compteur de la publication', function (): void {
    Event::fake([ContentReported::class]);
    $comment = Comment::factory()->for($this->post)->create();
    $this->post->update(['comments_count' => 1]);
    config(['xomiton.feed.auto_hide_after_reports' => 2]);

    foreach (range(1, 2) as $ignored) {
        FeedFixtures::as(FeedFixtures::member())->postJson('/api/v1/comments/'.$comment->id.'/report', ['reason' => 'spam'])->assertCreated();
    }

    expect($comment->refresh()->status)->toBe(PostStatus::Hidden)
        ->and($this->post->refresh()->comments_count)->toBe(0)
        ->and(ModerationReport::query()->where('kind', ModerationKind::Comment)->count())->toBe(2);
    Event::assertDispatchedTimes(ContentReported::class, 2);
});

it('refuse de signaler son propre contenu (403) et un motif inconnu (422)', function (): void {
    FeedFixtures::as($this->author)->postJson('/api/v1/posts/'.$this->post->ulid.'/report', ['reason' => 'spam'])->assertForbidden();
    reportPost($this->post, reason: 'filtre_automatique')->assertUnprocessable()->assertJsonValidationErrors('reason');
    reportPost($this->post, reason: 'inconnu')->assertUnprocessable()->assertJsonValidationErrors('reason');
    expect(ModerationReport::query()->count())->toBe(0);
});

it('limite les signalements par compte (throttle:content_report)', function (): void {
    config(['xomiton.rate_limits.content_report_per_hour' => 1]);
    $reporter = FeedFixtures::member();
    $other = Post::factory()->create();

    reportPost($this->post, $reporter)->assertCreated();
    reportPost($other, $reporter)->assertTooManyRequests();
});
