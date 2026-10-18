<?php

declare(strict_types=1);

use App\Domain\Moderation\Events\ContentReported;
use App\Enums\BadWordSeverity;
use App\Enums\ContentReportReason;
use App\Enums\PostStatus;
use App\Enums\PostTag;
use App\Enums\PostVisibility;
use App\Models\ModerationReport;
use App\Models\Poll;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FeedFixtures;

/*
 * P7-02 : POST / GET / PATCH / DELETE /api/v1/posts — 201 + Location, ULID public, 422 (texte, sondage, filtre de mots),
 * 403 (e-mail non confirmé, sans permission, pas l'auteur·rice), 404 (publication invisible), image ré-encodée sans EXIF
 * sur le disque privé et servie en lien signé, signalement automatique d'un terme « à surveiller ».
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->author = FeedFixtures::member();
});

it('répond 401 sans jeton', function (string $method, string $uri): void {
    $this->json($method, $uri)->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
})->with([
    ['GET', '/api/v1/feed'], ['POST', '/api/v1/posts'], ['GET', '/api/v1/posts/01JABCDEFGHJKMNPQRSTVWXYZ0'],
]);

it('publie une publication (201, Location, ULID) avec les valeurs par défaut', function (): void {
    $response = FeedFixtures::as($this->author)->postJson('/api/v1/posts', ['body' => 'Merci à toutes pour votre soutien.'])
        ->assertCreated()
        ->assertJsonPath('data.body', 'Merci à toutes pour votre soutien.')
        ->assertJsonPath('data.tag', PostTag::Testimony->value)
        ->assertJsonPath('data.visibility', PostVisibility::Public->value)
        ->assertJsonPath('data.is_mine', true)
        ->assertJsonPath('data.author.pseudo', $this->author->pseudo)
        ->assertJsonPath('data.likes_count', 0)
        ->assertJsonPath('data.poll', null);

    $post = Post::query()->sole();
    expect($response->json('data.id'))->toBe($post->ulid)
        ->and($response->headers->get('Location'))->toEndWith('/api/v1/posts/'.$post->ulid)
        ->and($response->json('data.share_url'))->toEndWith('/p/'.$post->ulid)
        ->and($response->json('data.url'))->toEndWith('/fil/'.$post->ulid)
        ->and($response->json())->not->toHaveKey('data.status');
});

it('publie sous pseudonyme sans jamais exposer l’auteur·rice (RG-02)', function (): void {
    FeedFixtures::as($this->author)->postJson('/api/v1/posts', ['body' => 'Témoignage anonyme.', 'is_anonymous' => true])->assertCreated();
    $post = Post::query()->sole();
    $reader = FeedFixtures::member();

    $response = FeedFixtures::as($reader)->getJson('/api/v1/posts/'.$post->ulid)->assertOk()
        ->assertJsonPath('data.author.id', null)
        ->assertJsonPath('data.author.anonymous', true)
        ->assertJsonPath('data.author.pseudo', 'Membre anonyme')
        ->assertJsonPath('data.is_mine', false);

    expect(json_encode($response->json()))->not->toContain($this->author->pseudo);
});

it('refuse un texte vide, trop long ou une étiquette « sondage » sans sondage (422)', function (array $payload, string $field): void {
    FeedFixtures::as($this->author)->postJson('/api/v1/posts', $payload)
        ->assertUnprocessable()->assertJsonPath('code', 'validation_failed')->assertJsonValidationErrors($field);
    expect(Post::query()->count())->toBe(0);
})->with([
    'texte vide' => [['body' => ''], 'body'],
    'texte trop long' => [['body' => str_repeat('a', 3001)], 'body'],
    'étiquette sondage' => [['body' => 'Question ?', 'tag' => 'sondage'], 'tag'],
    'visibilité inconnue' => [['body' => 'Bonjour', 'visibility' => 'friends'], 'visibility'],
    'sondage sans kind' => [['body' => 'Bonjour', 'poll' => ['question' => 'Quel thème ?']], 'poll'],
]);

it('refuse la publication tant que l’e-mail n’est pas confirmé (403 email_unverified)', function (): void {
    $unverified = FeedFixtures::member(verified: false);

    FeedFixtures::as($unverified)->postJson('/api/v1/posts', ['body' => 'Bonjour'])
        ->assertForbidden()->assertJsonPath('code', 'email_unverified');
});

it('refuse la publication sans permission posts.create (403 forbidden)', function (): void {
    $user = User::factory()->create();

    FeedFixtures::as($user)->postJson('/api/v1/posts', ['body' => 'Bonjour'])
        ->assertForbidden()->assertJsonPath('code', 'forbidden');
});

it('bloque un terme interdit avec un message neutre, sans rien créer (422 content_blocked)', function (): void {
    FeedFixtures::badWord('tarée');

    $response = FeedFixtures::as($this->author)->postJson('/api/v1/posts', ['body' => 'Tu es TAREE'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'content_blocked')
        ->assertJsonPath('errors.body.0', __('feed.api.content_blocked'));

    expect(Post::query()->count())->toBe(0)->and(json_encode($response->json()))->not->toContain('tar');
});

it('publie un texte « à surveiller » et ouvre un signalement automatique pour la modération', function (): void {
    Event::fake([ContentReported::class]);
    FeedFixtures::badWord('arnaque', BadWordSeverity::Flag);

    FeedFixtures::as($this->author)->postJson('/api/v1/posts', ['body' => 'Méfiez-vous de cette arnaque'])->assertCreated();

    $report = ModerationReport::query()->sole();
    expect(Post::query()->sole()->status)->toBe(PostStatus::Published)
        ->and($report->reporter_user_id)->toBeNull()
        ->and($report->reason)->toBe(ContentReportReason::Automatic->label())
        ->and($report->details)->toContain('arnaque')
        ->and(Post::query()->sole()->reports_count)->toBe(0);
    Event::assertDispatched(ContentReported::class, fn (ContentReported $event): bool => $event->reportId === $report->id && $event->reporterUserId === null);
});

it('publie un sondage (2 à 6 réponses, durée, réglages) et rend ses totaux', function (): void {
    $response = FeedFixtures::as($this->author)->postJson('/api/v1/posts', [
        'kind' => 'poll',
        'body' => 'Aidez-nous à choisir.',
        'poll' => [
            'question' => 'Quel thème pour la causerie ?',
            'options' => ['Santé', 'Droits', '', 'Bien-être'],
            'multiple' => true,
            'duration_days' => 7,
        ],
    ])->assertCreated()
        ->assertJsonPath('data.tag', 'sondage')
        ->assertJsonPath('data.poll.question', 'Quel thème pour la causerie ?')
        ->assertJsonPath('data.poll.multiple', true)
        ->assertJsonPath('data.poll.voters_count', 0)
        ->assertJsonPath('data.poll.results_visible', false)
        ->assertJsonPath('data.poll.options.0.votes_count', null)
        ->assertJsonCount(3, 'data.poll.options');

    $poll = Poll::query()->sole();
    expect($poll->ends_at?->isSameDay(now()->addDays(7)))->toBeTrue()
        ->and($response->json('data.poll.options.*.label'))->toBe(['Santé', 'Droits', 'Bien-être']);
});

it('publie un sondage sans fin avec une durée de 0 jour', function (): void {
    FeedFixtures::as($this->author)->postJson('/api/v1/posts', [
        'kind' => 'poll', 'poll' => ['question' => 'On se retrouve en ligne ?', 'options' => ['Oui', 'Non'], 'duration_days' => 0],
    ])->assertCreated()->assertJsonPath('data.poll.ends_at', null);
});

it('refuse un sondage invalide (422)', function (array $poll, string $field): void {
    FeedFixtures::as($this->author)->postJson('/api/v1/posts', ['kind' => 'poll', 'poll' => $poll])
        ->assertUnprocessable()->assertJsonValidationErrors($field);
    expect(Poll::query()->count())->toBe(0);
})->with([
    'question trop courte' => [['question' => 'Oui', 'options' => ['A', 'B']], 'poll.question'],
    'une seule réponse' => [['question' => 'Quel thème ?', 'options' => ['Santé', '']], 'poll.options'],
    'sept réponses' => [['question' => 'Quel thème ?', 'options' => ['1', '2', '3', '4', '5', '6', '7']], 'poll.options'],
    'réponses identiques (casse, accents)' => [['question' => 'Quel thème ?', 'options' => ['Santé', 'sante']], 'poll.options'],
    'durée inconnue' => [['question' => 'Quel thème ?', 'options' => ['A', 'B'], 'duration_days' => 5], 'poll.duration_days'],
]);

it('ré-encode l’image (EXIF retiré), la garde sur le disque privé et la sert en lien signé', function (): void {
    Storage::fake('local');
    Storage::fake('public');
    $file = UploadedFile::fake()->createWithContent('photo.jpg', FeedFixtures::jpegWithExif());

    $response = FeedFixtures::as($this->author)->post('/api/v1/posts', [
        'body' => 'Notre atelier de samedi.', 'image' => $file, 'image_alt' => 'Atelier en plein air',
    ], ['Accept' => 'application/json'])->assertCreated();

    $media = PostMedia::query()->sole();
    $stored = Storage::disk('local')->get($media->path);
    expect($stored)->not->toBeNull()
        ->and(str_contains((string) $stored, 'Exif'))->toBeFalse()
        ->and(str_contains((string) $stored, 'Xomiton Test'))->toBeFalse()
        ->and(Storage::disk('public')->allFiles())->toBe([])
        ->and($media->alt)->toBe('Atelier en plein air')
        ->and($response->json('data.media.0.url'))->toContain('signature=')
        ->and($response->json('data.media.0.kind'))->toBe('image');
});

it('refuse une image d’un format non autorisé (422)', function (): void {
    FeedFixtures::as($this->author)->post('/api/v1/posts', [
        'body' => 'Document', 'image' => UploadedFile::fake()->createWithContent('doc.jpg', "%PDF-1.4\n%%EOF\n"),
    ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('image');
});

it('lit une publication visible et répond 404 pour une publication invisible ou inconnue', function (): void {
    $reader = FeedFixtures::member();
    $public = Post::factory()->for($this->author)->create();
    $private = Post::factory()->for($this->author)->visibility(PostVisibility::Me)->create();
    $hidden = Post::factory()->for($this->author)->hidden()->create();

    FeedFixtures::as($reader)->getJson('/api/v1/posts/'.$public->ulid)->assertOk()->assertJsonPath('data.id', $public->ulid);
    FeedFixtures::as($reader)->getJson('/api/v1/posts/'.$private->ulid)->assertNotFound()->assertJsonPath('code', 'not_found');
    FeedFixtures::as($reader)->getJson('/api/v1/posts/'.$hidden->ulid)->assertNotFound();
    FeedFixtures::as($reader)->getJson('/api/v1/posts/01JABCDEFGHJKMNPQRSTVWXYZ0')->assertNotFound();
    // L'auteur·rice voit sa publication masquée, avec la seule mention « en attente de relecture ».
    FeedFixtures::as($this->author)->getJson('/api/v1/posts/'.$hidden->ulid)->assertOk()->assertJsonPath('data.moderated', true);
});

it('laisse l’auteur·rice modifier sa publication, jamais une autre personne (403)', function (): void {
    $post = Post::factory()->for($this->author)->tagged(PostTag::Question)->create(['body' => 'Avant']);
    $other = FeedFixtures::member();

    FeedFixtures::as($other)->patchJson('/api/v1/posts/'.$post->ulid, ['body' => 'Piraté'])
        ->assertForbidden()->assertJsonPath('code', 'forbidden');

    FeedFixtures::as($this->author)->patchJson('/api/v1/posts/'.$post->ulid, ['body' => 'Après', 'visibility' => 'followers', 'tag' => 'ressource'])
        ->assertOk()->assertJsonPath('data.body', 'Après')->assertJsonPath('data.visibility', 'followers')->assertJsonPath('data.tag', 'ressource');

    FeedFixtures::as($this->author)->patchJson('/api/v1/posts/'.$post->ulid, ['tag' => 'sondage'])->assertUnprocessable()->assertJsonValidationErrors('tag');
    FeedFixtures::as($this->author)->patchJson('/api/v1/posts/'.$post->ulid, ['body' => ''])->assertUnprocessable()->assertJsonValidationErrors('body');
    expect($post->refresh()->body)->toBe('Après');
});

it('supprime : l’auteur·rice ou la modération (204), jamais une autre personne (403)', function (): void {
    $post = Post::factory()->for($this->author)->create();
    $other = FeedFixtures::member();
    $moderator = FeedFixtures::member('moderateur');

    FeedFixtures::as($other)->deleteJson('/api/v1/posts/'.$post->ulid)->assertForbidden();
    FeedFixtures::as($this->author)->deleteJson('/api/v1/posts/'.$post->ulid)->assertNoContent();

    $trashed = Post::withTrashed()->find($post->id);
    expect($trashed?->trashed())->toBeTrue()->and($trashed?->status)->toBe(PostStatus::Deleted);
    FeedFixtures::as($this->author)->getJson('/api/v1/posts/'.$post->ulid)->assertNotFound();

    $second = Post::factory()->for($other)->create();
    FeedFixtures::as($moderator)->deleteJson('/api/v1/posts/'.$second->ulid)->assertNoContent();
    $this->assertDatabaseHas('audit_logs', ['action' => 'post.deleted_by_moderation', 'subject_id' => $second->id]);
});

it('limite les publications par compte (throttle:feed_write)', function (): void {
    config(['xomiton.rate_limits.feed_write_per_minute' => 2]);

    FeedFixtures::as($this->author)->postJson('/api/v1/posts', ['body' => 'Un'])->assertCreated();
    FeedFixtures::as($this->author)->postJson('/api/v1/posts', ['body' => 'Deux'])->assertCreated();
    FeedFixtures::as($this->author)->postJson('/api/v1/posts', ['body' => 'Trois'])->assertTooManyRequests()->assertJsonPath('code', 'too_many_requests');
});

it('cache la publication d’un groupe privé aux non-membres (404) et la montre à ses membres', function (): void {
    $group = App\Models\Group::factory()->private()->create();
    $post = Post::factory()->for($this->author)->inGroup($group)->create();
    $outsider = FeedFixtures::member();
    $member = FeedFixtures::member();
    App\Models\GroupMember::factory()->create(['group_id' => $group->id, 'user_id' => $member->id]);

    FeedFixtures::as($outsider)->getJson('/api/v1/posts/'.$post->ulid)->assertNotFound();
    FeedFixtures::as($member)->getJson('/api/v1/posts/'.$post->ulid)->assertOk()->assertJsonPath('data.id', $post->ulid);
});
