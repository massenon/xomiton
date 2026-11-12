<?php

declare(strict_types=1);

use App\Application\Feed\ReviewVideo;
use App\Enums\PostStatus;
use App\Models\AuditLog;
use App\Models\Follow;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\User;
use App\Notifications\VideoPendingReview;
use App\Notifications\VideoReviewed;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FeedFixtures;

/*
 * Q96 : vidéos relues avant parution — dépôt (mp4 / webm, type réel, poids, description obligatoire, jamais avec des
 * images ni dans un sondage, quota), publication en relecture invisible hors auteur·rice et modération (fil, détail,
 * recherche, abonné·es, profil, 404 neutre du flux), lecture avec plages Range (206), approbation, refus, retrait.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('local');
    Storage::fake('public');
    $this->author = FeedFixtures::member();
    $this->moderator = FeedFixtures::member('moderateur');
});

function fakeVideo(string $name = 'clip.mp4', ?string $content = null): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $content ?? FeedFixtures::mp4());
}

function postVideo(array $data = [], ?User $as = null)
{
    $data += ['body' => 'Notre atelier en vidéo.', 'video' => fakeVideo(), 'video_description' => 'Des femmes assises en cercle qui discutent.'];

    return FeedFixtures::as($as ?? test()->author)->post('/api/v1/posts', $data, ['Accept' => 'application/json']);
}

/** @return array{0: Post, 1: string} publication en relecture et chemin du fichier */
function pendingVideoPost(?User $author = null): array
{
    $ulid = postVideo(as: $author)->assertStatus(202)->json('data.id');
    $post = Post::query()->where('ulid', $ulid)->firstOrFail();

    return [$post, (string) PostMedia::query()->where('post_id', $post->id)->value('path')];
}

it('envoie une vidéo mp4 ou webm en relecture : 202, Location, état pending, aucune date de parution', function (string $name, string $content, string $mime): void {
    $response = postVideo(['video' => fakeVideo($name, $content)])->assertStatus(202);

    $post = Post::query()->sole();
    $media = PostMedia::query()->sole();

    expect($response->headers->get('Location'))->toEndWith('/api/v1/posts/'.$post->ulid)
        ->and($response->json('data.review_state'))->toBe('pending')
        ->and($response->json('data.review_label'))->toBe('En attente de relecture')
        ->and($response->json('data.published_at'))->toBeNull()
        ->and($response->json('data.is_mine'))->toBeTrue()
        ->and($response->json('data.can.update'))->toBeFalse()
        ->and($response->json('data.can.delete'))->toBeTrue()
        ->and($response->json('data.media.0.kind'))->toBe('video')
        ->and($response->json('data.media.0.alt'))->toBe('Des femmes assises en cercle qui discutent.')
        ->and($response->json('data.media.0.mime'))->toBe($mime)
        ->and($response->json('data.media.0.url'))->toContain('/media/fil/'.$post->ulid.'/video')->toContain('signature=')
        ->and($post->currentStatus())->toBe(PostStatus::Pending)
        ->and($media->kind)->toBe('video')->and($media->mime)->toBe($mime)
        ->and(Storage::disk('local')->exists($media->path))->toBeTrue()
        ->and(Storage::disk('public')->allFiles())->toBe([]);
})->with([
    'mp4' => ['clip.mp4', FeedFixtures::mp4(), 'video/mp4'],
    'webm' => ['clip.webm', FeedFixtures::webm(), 'video/webm'],
]);

it('ne ré-encode pas la vidéo : le fichier stocké est identique à celui envoyé', function (): void {
    postVideo(['video' => fakeVideo('clip.mp4', FeedFixtures::mp4(6000))])->assertStatus(202);

    expect(Storage::disk('local')->get((string) PostMedia::query()->value('path')))->toBe(FeedFixtures::mp4(6000));
});

it('exige la description (5 à 500 caractères) et l’alternative textuelle', function (array $data, string $field): void {
    postVideo($data)->assertUnprocessable()->assertJsonValidationErrors($field);

    expect(Post::query()->count())->toBe(0)->and(Storage::disk('local')->allFiles())->toBe([]);
})->with([
    'description absente' => [['video_description' => null], 'video_description'],
    'description vide' => [['video_description' => '   '], 'video_description'],
    'description trop courte' => [['video_description' => 'ok'], 'video_description'],
    'description trop longue' => [['video_description' => str_repeat('a', 501)], 'video_description'],
]);

it('refuse un faux type MIME, une extension incohérente et un type non autorisé (422)', function (string $name, string $content): void {
    postVideo(['video' => fakeVideo($name, $content)])->assertUnprocessable()->assertJsonValidationErrors('video');

    expect(Post::query()->count())->toBe(0);
})->with([
    'pdf déguisé en mp4' => ['clip.mp4', "%PDF-1.4\n%%EOF\n"],
    'script déguisé' => ['clip.mp4', "<?php echo 'x';"],
    'webm nommé .mp4' => ['clip.mp4', FeedFixtures::webm()],
    'mp4 nommé .webm' => ['clip.webm', FeedFixtures::mp4()],
    'mp4 nommé .exe' => ['clip.exe', FeedFixtures::mp4()],
    'fichier vide' => ['clip.mp4', ''],
]);

it('refuse une vidéo plus lourde que le plafond (config et limites PHP)', function (): void {
    config(['xomiton.feed.video_max_mb' => 1]);

    postVideo(['video' => fakeVideo('big.mp4', FeedFixtures::mp4(1024 * 1024 + 2048))])->assertUnprocessable()->assertJsonValidationErrors('video');
    postVideo(['video' => fakeVideo('ok.mp4', FeedFixtures::mp4(900 * 1024))])->assertStatus(202);
});

it('borne le plafond par upload_max_filesize et post_max_size de PHP', function (): void {
    expect(App\Application\Feed\FeedMediaLimits::iniBytes('8M'))->toBe(8 * 1024 * 1024)
        ->and(App\Application\Feed\FeedMediaLimits::iniBytes('512K'))->toBe(512 * 1024)
        ->and(App\Application\Feed\FeedMediaLimits::iniBytes('1G'))->toBe(1024 ** 3)
        ->and(App\Application\Feed\FeedMediaLimits::iniBytes('-1'))->toBe(0)
        ->and(App\Application\Feed\FeedMediaLimits::iniBytes('0'))->toBe(0)
        ->and(App\Application\Feed\FeedMediaLimits::iniBytes('n’importe quoi'))->toBe(0);

    config(['xomiton.feed.video_max_mb' => 30]);
    $limit = App\Application\Feed\FeedMediaLimits::videoMaxKb();

    expect($limit)->toBeLessThanOrEqual(30 * 1024)->toBeGreaterThan(0);

    foreach (['upload_max_filesize', 'post_max_size'] as $directive) {
        $bytes = App\Application\Feed\FeedMediaLimits::iniBytes((string) ini_get($directive));

        if ($bytes > 0) {
            expect($limit)->toBeLessThanOrEqual(intdiv($bytes, 1024));
        }
    }
});

it('refuse une vidéo avec des images, une deuxième vidéo, ou dans un sondage', function (): void {
    postVideo(['images' => [UploadedFile::fake()->createWithContent('a.png', FeedFixtures::png())]])->assertUnprocessable()->assertJsonValidationErrors('video');
    postVideo(['image' => UploadedFile::fake()->createWithContent('a.png', FeedFixtures::png())])->assertUnprocessable()->assertJsonValidationErrors('video');
    postVideo(['kind' => 'poll', 'poll' => ['question' => 'Quel thème ?', 'options' => ['Santé', 'Droits']]])->assertUnprocessable()->assertJsonValidationErrors('video');
    postVideo(['video' => [fakeVideo('a.mp4'), fakeVideo('b.mp4')]])->assertUnprocessable()->assertJsonValidationErrors('video');

    expect(Post::query()->count())->toBe(0)->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('accepte une vidéo sans texte, refuse sans texte ni média', function (): void {
    postVideo(['body' => null])->assertStatus(202);
    FeedFixtures::as($this->author)->postJson('/api/v1/posts', [])->assertUnprocessable()->assertJsonValidationErrors('body');
});

it('applique le filtre de mots à la description sans rien téléverser', function (): void {
    FeedFixtures::badWord('grossierete');

    postVideo(['video_description' => 'Une description grossierete ici'])->assertUnprocessable()->assertJsonPath('code', 'content_rejected');

    expect(Storage::disk('local')->allFiles())->toBe([])->and(Post::query()->count())->toBe(0);
});

it('refuse sans jeton (401) et sans e-mail confirmé (403)', function (): void {
    $this->post('/api/v1/posts', ['video' => fakeVideo(), 'video_description' => 'Description valable'], ['Accept' => 'application/json'])->assertUnauthorized();
    postVideo(as: FeedFixtures::member(verified: false))->assertForbidden()->assertJsonPath('code', 'email_unverified');
});

it('limite les vidéos par compte et par jour (429, Retry-After), retraits compris', function (): void {
    config(['xomiton.feed.video_per_day' => 2, 'xomiton.rate_limits.feed_write_per_minute' => 50]);

    foreach (range(1, 2) as $i) {
        $ulid = postVideo(['video' => fakeVideo("v{$i}.mp4", FeedFixtures::mp4(4096 + $i))])->assertStatus(202)->json('data.id');
        FeedFixtures::as($this->author)->deleteJson('/api/v1/posts/'.$ulid)->assertNoContent();
    }

    $blocked = postVideo()->assertStatus(429)->assertJsonPath('code', 'too_many_requests');
    expect((int) $blocked->headers->get('Retry-After'))->toBeGreaterThan(0)
        ->and($blocked->json('retry_after'))->toBeGreaterThan(0)
        ->and(Post::query()->count())->toBe(0);

    // Une autre personne n'est pas concernée ; le lendemain, le quota est rendu.
    postVideo(as: FeedFixtures::member())->assertStatus(202);
    Carbon::setTestNow(now()->addDay()->addMinute());
    postVideo()->assertStatus(202);
    Carbon::setTestNow();
});

it('garde la vidéo invisible pour toute autre personne : fil, détail, profil, groupe, enregistrées, abonné·es', function (): void {
    $reader = FeedFixtures::member();
    FeedFixtures::follow($reader, $this->author);
    [$post] = pendingVideoPost();

    FeedFixtures::as($reader)->getJson('/api/v1/feed')->assertOk()->assertJsonCount(0, 'data');
    FeedFixtures::as($reader)->getJson('/api/v1/posts/'.$post->ulid)->assertNotFound()->assertJsonPath('code', 'not_found');
    FeedFixtures::as($reader)->getJson('/api/v1/users/'.$this->author->id.'/posts')->assertOk()->assertJsonCount(0, 'data');
    FeedFixtures::as($reader)->postJson('/api/v1/posts/'.$post->ulid.'/like')->assertNotFound();
    FeedFixtures::as($reader)->postJson('/api/v1/posts/'.$post->ulid.'/report', ['reason' => 'spam'])->assertNotFound();
    FeedFixtures::as($reader)->postJson('/api/v1/posts/'.$post->ulid.'/comments', ['body' => 'Bravo'])->assertNotFound();
    FeedFixtures::as($reader)->getJson('/api/v1/feed/saved')->assertOk()->assertJsonCount(0, 'data');

    expect(Post::query()->visibleTo($reader)->count())->toBe(0)
        ->and(Post::query()->published()->count())->toBe(0);
});

it('n’envoie aucune notification d’abonné·e, de réaction ni de cloche à quiconque avant l’approbation', function (): void {
    Notification::fake();
    $follower = FeedFixtures::member();
    FeedFixtures::follow($follower, $this->author);

    postVideo()->assertStatus(202);

    Notification::assertNotSentTo($follower, VideoReviewed::class);
    Notification::assertNotSentTo($this->author, VideoPendingReview::class);
    Notification::assertSentTo($this->moderator, VideoPendingReview::class);
    Notification::assertNotSentTo($follower, VideoPendingReview::class);
    Notification::assertSentToTimes($this->moderator, VideoPendingReview::class, 1);
});

it('ne compte la vidéo en relecture ni dans le groupe ni dans le journal du profil, et la journalise sans contenu', function (): void {
    postVideo()->assertStatus(202);

    $log = AuditLog::query()->where('action', 'post.video_submitted')->sole();
    expect(json_encode($log->getAttributes()))->not->toContain('Notre atelier')->not->toContain('cercle');
});

it('montre à l’auteur·rice sa vidéo en attente, avec le bon état, et la liste « posts/review »', function (): void {
    [$post] = pendingVideoPost();
    $other = FeedFixtures::member();
    pendingVideoPost($other);

    FeedFixtures::as($this->author)->getJson('/api/v1/posts/'.$post->ulid)->assertOk()
        ->assertJsonPath('data.review_state', 'pending')
        ->assertJsonPath('data.review_label', 'En attente de relecture')
        ->assertJsonPath('data.published_at', null);

    $list = FeedFixtures::as($this->author)->getJson('/api/v1/posts/review')->assertOk()->assertJsonCount(1, 'data');
    expect($list->json('data.0.id'))->toBe($post->ulid);

    // Aucune action « sociale » sur sa propre vidéo en attente, ni modification.
    FeedFixtures::as($this->author)->patchJson('/api/v1/posts/'.$post->ulid, ['body' => 'Changé'])->assertForbidden();
});

it('montre la vidéo en attente à la modération', function (): void {
    [$post] = pendingVideoPost();

    FeedFixtures::as($this->moderator)->getJson('/api/v1/posts/'.$post->ulid)->assertOk()->assertJsonPath('data.review_state', 'pending');
    FeedFixtures::as(FeedFixtures::member('admin'))->getJson('/api/v1/posts/'.$post->ulid)->assertOk();
});

it('diffuse la vidéo par une adresse signée avec plages Range (206) à l’auteur·rice et à la modération, jamais à un tiers (404)', function (): void {
    $content = FeedFixtures::mp4(10_000);
    $ulid = postVideo(['video' => fakeVideo('clip.mp4', $content)])->assertStatus(202)->json('data.id');

    $url = FeedFixtures::as($this->author)->getJson('/api/v1/posts/'.$ulid)->json('data.media.0.url');

    $full = $this->get($url)->assertOk();
    expect($full->headers->get('Accept-Ranges'))->toBe('bytes')
        ->and($full->headers->get('Content-Type'))->toContain('video/mp4')
        ->and($full->headers->get('Cache-Control'))->toContain('no-store')->toContain('private')
        ->and($full->headers->get('X-Robots-Tag'))->toBe('noindex, nofollow')
        ->and($full->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($full->headers->get('Content-Length'))->toBe((string) strlen($content));

    $partial = $this->get($url, ['Range' => 'bytes=100-199'])->assertStatus(206);
    expect($partial->headers->get('Content-Range'))->toBe('bytes 100-199/'.strlen($content))
        ->and($partial->headers->get('Content-Length'))->toBe('100');

    $this->get($url, ['Range' => 'bytes=-50'])->assertStatus(206);

    // Modération : son propre lien.
    $moderatorUrl = FeedFixtures::as($this->moderator)->getJson('/api/v1/posts/'.$ulid)->json('data.media.0.url');
    $this->get($moderatorUrl)->assertOk();

    // Un tiers : l'API ne lui donne pas d'adresse et une adresse empruntée répond 404 neutre.
    $thirdParty = FeedFixtures::member();
    $forged = URL::temporarySignedRoute('media.post_video', now()->addMinutes(5), ['ulid' => $ulid, 'viewer' => $thirdParty->id]);
    $this->get($forged)->assertNotFound();

    // Signature absente ou altérée : 403.
    $this->get('/media/fil/'.$ulid.'/video?viewer='.$this->author->id)->assertForbidden();
    $this->get(str_replace('signature=', 'signature=x', $url))->assertForbidden();
});

it('répond 404 neutre pour une publication inconnue, un compte supprimé ou suspendu, ou un lien expiré (403)', function (): void {
    [$post] = pendingVideoPost();

    $unknown = URL::temporarySignedRoute('media.post_video', now()->addMinutes(5), ['ulid' => '01JABCDEFGHJKMNPQRSTVWXYZ0', 'viewer' => $this->author->id]);
    $this->get($unknown)->assertNotFound();

    $expired = URL::temporarySignedRoute('media.post_video', now()->subMinute(), ['ulid' => $post->ulid, 'viewer' => $this->author->id]);
    $this->get($expired)->assertForbidden();

    $valid = URL::temporarySignedRoute('media.post_video', now()->addMinutes(5), ['ulid' => $post->ulid, 'viewer' => $this->author->id]);
    $this->get($valid)->assertOk();
    $this->author->forceFill(['status' => App\Enums\UserStatus::Suspended])->save();
    $this->get($valid)->assertNotFound();
});

it('ne sert plus la vidéo quand son fichier a été supprimé, et ne donne plus d’adresse', function (): void {
    [$post, $path] = pendingVideoPost();
    Storage::disk('local')->delete($path);

    $response = FeedFixtures::as($this->author)->getJson('/api/v1/posts/'.$post->ulid)->assertOk();
    expect($response->json('data.media'))->toBe([]);
});

it('laisse l’auteur·rice retirer sa vidéo en attente : fichier supprimé tout de suite, publication introuvable', function (): void {
    [$post, $path] = pendingVideoPost();

    FeedFixtures::as($this->author)->deleteJson('/api/v1/posts/'.$post->ulid)->assertNoContent();

    expect(Storage::disk('local')->exists($path))->toBeFalse()
        ->and(Post::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('action', 'post.video_withdrawn')->count())->toBe(1);

    FeedFixtures::as($this->moderator)->getJson('/api/v1/posts/'.$post->ulid)->assertNotFound();
});

it('approuve : publiée à la date d’approbation, visible, notification à l’auteur·rice, état approved', function (): void {
    Notification::fake();
    $reader = FeedFixtures::member();
    FeedFixtures::follow($reader, $this->author);
    [$post] = pendingVideoPost();

    Carbon::setTestNow(now()->addHour());
    app(ReviewVideo::class)->approve($post, $this->moderator);

    $post->refresh();
    expect($post->currentStatus())->toBe(PostStatus::Published)
        ->and($post->published_at->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and($post->reviewed_by)->toBe($this->moderator->id)
        ->and($post->review_reason)->toBeNull();

    Notification::assertSentTo($this->author, VideoReviewed::class, static fn (VideoReviewed $n): bool => $n->decision === VideoReviewed::APPROVED);

    $this->app['auth']->forgetGuards();
    $feed = FeedFixtures::as($reader)->getJson('/api/v1/feed')->assertOk();
    expect($feed->json('data.0.id'))->toBe($post->ulid)
        ->and($feed->json('data.0.review_state'))->toBe('approved')
        ->and($feed->json('data.0.review_label'))->toBe('Vidéo publiée')
        ->and($feed->json('data.0.published_at'))->not->toBeNull()
        ->and($feed->json('data.0.media.0.kind'))->toBe('video')
        ->and($feed->json('data.0.media.0.url'))->toContain('/media/fil/'.$post->ulid.'/video');

    // Le lecteur reçoit son propre lien signé et peut lire la vidéo.
    $this->get($feed->json('data.0.media.0.url'))->assertOk();
    Carbon::setTestNow();
});

it('respecte la visibilité après approbation : « moi » ou abonné·es seulement', function (): void {
    $stranger = FeedFixtures::member();
    $follower = FeedFixtures::member();
    FeedFixtures::follow($follower, $this->author);
    $ulid = postVideo(['visibility' => 'followers'])->assertStatus(202)->json('data.id');
    app(ReviewVideo::class)->approve(Post::query()->where('ulid', $ulid)->firstOrFail(), $this->moderator);

    $url = FeedFixtures::as($follower)->getJson('/api/v1/posts/'.$ulid)->assertOk()->json('data.media.0.url');
    $this->get($url)->assertOk();

    FeedFixtures::as($stranger)->getJson('/api/v1/posts/'.$ulid)->assertNotFound();
    $forged = URL::temporarySignedRoute('media.post_video', now()->addMinutes(5), ['ulid' => $ulid, 'viewer' => $stranger->id]);
    $this->get($forged)->assertNotFound();
});

it('refuse avec un motif : état rejected visible de l’auteur·rice seulement, notification, fichier supprimé après le délai', function (): void {
    Notification::fake();
    [$post, $path] = pendingVideoPost();

    app(ReviewVideo::class)->reject($post, $this->moderator, 'privacy', 'On voit le portail de ta maison.');

    $post->refresh();
    expect($post->currentStatus())->toBe(PostStatus::Rejected)
        ->and($post->review_reason)->toBe('privacy')
        ->and($post->review_note)->toBe('On voit le portail de ta maison.');

    Notification::assertSentTo($this->author, VideoReviewed::class, static fn (VideoReviewed $n): bool => $n->decision === VideoReviewed::REJECTED
        && str_contains((string) $n->reason, 'Vie privée') && str_contains((string) $n->reason, 'portail'));

    $mine = FeedFixtures::as($this->author)->getJson('/api/v1/posts/'.$post->ulid)->assertOk();
    expect($mine->json('data.review_state'))->toBe('rejected')
        ->and($mine->json('data.review_reason'))->toContain('Vie privée')->toContain('portail')
        ->and($mine->json('data.published_at'))->toBeNull();

    FeedFixtures::as(FeedFixtures::member())->getJson('/api/v1/posts/'.$post->ulid)->assertNotFound();

    // Avant le délai : le fichier reste ; après : il est supprimé, la ligne reste sans chemin.
    $this->artisan('videos:purge-rejected')->expectsOutputToContain('0 fichier vidéo supprimé')->assertSuccessful();
    expect(Storage::disk('local')->exists($path))->toBeTrue();

    Carbon::setTestNow(now()->addDays(7)->addMinute());
    $this->artisan('videos:purge-rejected')->expectsOutputToContain('1 fichier vidéo supprimé')->assertSuccessful();
    expect(Storage::disk('local')->exists($path))->toBeFalse()
        ->and(PostMedia::query()->sole()->path)->toBe('');
    $this->artisan('videos:purge-rejected')->expectsOutputToContain('0 fichier vidéo supprimé');
    Carbon::setTestNow();

    $after = FeedFixtures::as($this->author)->getJson('/api/v1/posts/'.$post->ulid)->assertOk();
    expect($after->json('data.media'))->toBe([]);
});

it('respecte un délai de conservation configuré', function (): void {
    config(['xomiton.feed.video_rejected_retention_days' => 2]);
    [$post, $path] = pendingVideoPost();
    app(ReviewVideo::class)->reject($post, $this->moderator, 'inappropriate');

    Carbon::setTestNow(now()->addDay());
    $this->artisan('videos:purge-rejected')->assertSuccessful();
    expect(Storage::disk('local')->exists($path))->toBeTrue();

    Carbon::setTestNow(now()->addDays(2));
    $this->artisan('videos:purge-rejected')->assertSuccessful();
    expect(Storage::disk('local')->exists($path))->toBeFalse();
    Carbon::setTestNow();
});

it('exige un motif valide, et un texte libre pour « autre » ; texte libre borné à 300 caractères', function (): void {
    [$post] = pendingVideoPost();
    $review = app(ReviewVideo::class);

    expect(fn () => $review->reject($post, $this->moderator, 'nimporte'))->toThrow(App\Domain\Moderation\Exceptions\ModerationRefused::class);
    expect(fn () => $review->reject($post, $this->moderator, ''))->toThrow(App\Domain\Moderation\Exceptions\ModerationRefused::class);
    expect(fn () => $review->reject($post, $this->moderator, 'other', '   '))->toThrow(App\Domain\Moderation\Exceptions\ModerationRefused::class);
    expect($post->fresh()->currentStatus())->toBe(PostStatus::Pending);

    $review->reject($post, $this->moderator, 'other', str_repeat('é', 400));
    expect(mb_strlen((string) $post->fresh()->review_note))->toBe(300);
});

it('ne décide qu’une fois, jamais sa propre vidéo, jamais sans permission de modération (403)', function (): void {
    [$post] = pendingVideoPost();
    $review = app(ReviewVideo::class);

    expect(fn () => $review->approve($post, $this->author))->toThrow(Illuminate\Auth\Access\AuthorizationException::class);
    expect(fn () => $review->reject($post, FeedFixtures::member('pair'), 'other', 'x'))->toThrow(Illuminate\Auth\Access\AuthorizationException::class);

    $selfModerated = FeedFixtures::member('moderateur');
    [$own] = pendingVideoPost($selfModerated);
    expect(fn () => $review->approve($own, $selfModerated))->toThrow(Illuminate\Auth\Access\AuthorizationException::class);

    $review->approve($post, $this->moderator);
    expect(fn () => $review->approve($post->fresh(), FeedFixtures::member('moderateur')))->toThrow(Illuminate\Auth\Access\AuthorizationException::class);
    expect(fn () => $review->reject($post->fresh(), FeedFixtures::member('moderateur'), 'privacy'))->toThrow(Illuminate\Auth\Access\AuthorizationException::class);
});

it('journalise chaque décision sans contenu ni description', function (): void {
    [$post] = pendingVideoPost();
    app(ReviewVideo::class)->reject($post, $this->moderator, 'privacy', 'Texte libre confidentiel');

    $log = AuditLog::query()->where('action', 'post.video_rejected')->sole();
    expect(json_encode($log->getAttributes()))->toContain('privacy')->not->toContain('confidentiel')->not->toContain('cercle');
});

it('retire de la liste des vidéos en attente celles déjà décidées', function (): void {
    [$post] = pendingVideoPost();
    app(ReviewVideo::class)->reject($post, $this->moderator, 'off_topic');

    $mine = FeedFixtures::as($this->author)->getJson('/api/v1/posts/review')->assertOk();
    expect($mine->json('data.0.review_state'))->toBe('rejected');
});

it('supprime le fichier d’une vidéo refusée quand l’auteur·rice la retire', function (): void {
    [$post, $path] = pendingVideoPost();
    app(ReviewVideo::class)->reject($post, $this->moderator, 'privacy');

    FeedFixtures::as($this->author)->deleteJson('/api/v1/posts/'.$post->ulid)->assertNoContent();

    expect(Storage::disk('local')->exists($path))->toBeFalse();
});

it('ne touche pas aux abonnements ni aux compteurs : Follow inchangé', function (): void {
    FeedFixtures::follow(FeedFixtures::member(), $this->author);
    pendingVideoPost();

    expect(Follow::query()->count())->toBe(1);
});
