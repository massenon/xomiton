<?php

declare(strict_types=1);

use App\Application\Shared\MediaUploader;
use App\Enums\AlertStatus;
use App\Enums\DirectoryRole;
use App\Enums\PostTag;
use App\Enums\PostVisibility;
use App\Enums\ProfileVisibility;
use App\Enums\UserStatus;
use App\Models\Alert;
use App\Models\CaseRecord;
use App\Models\Category;
use App\Models\DatabaseNotification;
use App\Models\DirectoryContact;
use App\Models\Event;
use App\Models\Group;
use App\Models\Post;
use App\Models\PostHide;
use App\Models\PostMedia;
use App\Models\Resource;
use App\Models\Thematique;
use App\Models\User;
use App\Models\UserBlock;
use App\Support\LocaleFormatter;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;
use Tests\Support\FeedFixtures;

/*
 * API de lecture de l'application mobile (P6-08, docs/API.md §11) : tableau de bord selon le rôle, thématiques et
 * ressources publiques (mêmes requêtes que le site), lien de téléchargement signé, publications d'un profil. Chaque route
 * est vérifiée en 200, 304 (ETag + If-None-Match) et 404 ; le poids des fichiers est présent.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/** Rejoue la même requête avec l'ETag reçu : 304 sans corps. */
function mobileRevalidates(TestResponse $first, string $uri, ?User $as = null): void
{
    $etag = (string) $first->headers->get('ETag');
    expect($etag)->not->toBe('');

    $request = $as === null ? test() : FeedFixtures::as($as);
    $request->withHeaders(['If-None-Match' => $etag])->get($uri, ['Accept' => 'application/json'])
        ->assertStatus(304)
        ->assertContent('');
}

function mobileCategory(string $slug, array $attributes = []): Category
{
    return Category::factory()->ofKind(Category::KIND_THEMATIQUE)->create(['slug' => $slug, 'label' => ucfirst($slug), ...$attributes]);
}

// --- GET dashboard ------------------------------------------------------------------------------------------------

it('répond 401 au tableau de bord sans jeton', function (): void {
    $this->getJson('/api/v1/dashboard')->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
});

it('rend le tableau de bord d’un·e membre : tuiles, compteurs non lus, complétion, à la une, prochain événement', function (): void {
    $member = FeedFixtures::member();
    $member->profile->forceFill(['completion' => 43])->save();
    DatabaseNotification::factory()->forUser($member)->inCategory('communaute')->count(2)->create();
    DatabaseNotification::factory()->forUser($member)->inCategory('communaute')->read()->create();

    $active = mobileCategory('droits');
    $inactive = mobileCategory('ancienne', ['is_active' => false]);
    $old = Thematique::factory()->for($active)->create(['published_at' => now()->subDays(10)]);
    $recent = Thematique::factory()->for($inactive)->create(['published_at' => now()->subDay()]);
    Thematique::factory()->draft()->create();
    config(['xomiton.api.dashboard_featured' => 2]);

    Event::factory()->past()->create();
    Event::factory()->forGroup(Group::factory()->create(['privacy' => 'prive']))->create(['starts_at' => now()->addHour(), 'ends_at' => now()->addHours(2)]);
    $next = Event::factory()->online()->create(['starts_at' => now()->addDays(2), 'ends_at' => now()->addDays(2)->addHour()]);
    Event::factory()->create(['starts_at' => now()->addDays(5), 'ends_at' => now()->addDays(5)->addHour()]);

    $response = FeedFixtures::as($member)->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-cache, private')
        ->assertJsonPath('data.user.id', $member->getKey())
        ->assertJsonPath('data.user.pseudo', $member->pseudo)
        ->assertJsonPath('data.user.role', 'membre')
        ->assertJsonPath('data.tiles.*.key', ['report', 'emergency', 'assistant', 'learn'])
        ->assertJsonPath('data.tiles.3.route', '/thematiques')
        ->assertJsonPath('data.tiles.3.count', 2)
        ->assertJsonPath('data.counters.notifications', 2)
        ->assertJsonPath('data.counters.messages', 0)
        ->assertJsonPath('data.profile', ['completion' => 43, 'complete' => false])
        ->assertJsonPath('data.featured.total', 2)
        ->assertJsonPath('data.featured.items.*.slug', [$recent->slug, $old->slug])
        ->assertJsonPath('data.featured.items.0.category', null)
        ->assertJsonPath('data.featured.items.1.category.slug', 'droits')
        ->assertJsonPath('data.next_event.id', $next->ulid)
        ->assertJsonPath('data.next_event.is_online', true)
        ->assertJsonPath('data.peer', null);

    expect($response->json('data.next_event'))->not->toHaveKey('place');

    mobileRevalidates($response, '/api/v1/dashboard', $member);
});

it('ajoute à un·e pair ses 4 KPI et les alertes qui lui sont routées, jamais celles des autres', function (): void {
    $peer = FeedFixtures::member('pair');
    $other = FeedFixtures::member('pair');
    $contact = DirectoryContact::factory()->role(DirectoryRole::PairAnimateur)->linkedTo($peer)->create();
    $otherContact = DirectoryContact::factory()->linkedTo($other)->create();

    CaseRecord::factory()->collectedBy($peer)->create(['created_at' => now()->startOfMonth()->subDays(3)]);
    CaseRecord::factory()->collectedBy($peer)->create();
    CaseRecord::factory()->collectedBy($peer)->inProgress()->create();
    CaseRecord::factory()->collectedBy($peer)->closed()->create();
    CaseRecord::factory()->collectedBy($peer)->archived()->create();
    foreach (range(1, 3) as $ignored) {
        CaseRecord::factory()->collectedBy($other)->create();
    }

    $new = Alert::factory()->routedTo($contact)->create(['created_at' => now()->subMinutes(5), 'description' => 'Récit confidentiel']);
    $taken = Alert::factory()->routedTo($contact)->acknowledged()->withStatus(AlertStatus::InProgress)->create(['created_at' => now()->subMinutes(10)]);
    Alert::factory()->routedTo($contact)->withStatus(AlertStatus::Closed)->create();
    Alert::factory()->routedTo($otherContact)->create();

    $response = FeedFixtures::as($peer)->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonPath('data.user.role', 'pair')
        ->assertJsonPath('data.peer.kpi', ['collected' => 4, 'this_month' => 3, 'in_progress' => 3, 'closed' => 1])
        ->assertJsonPath('data.peer.routed_alerts.open', 2)
        ->assertJsonPath('data.peer.routed_alerts.new', 1)
        ->assertJsonPath('data.peer.routed_alerts.items.*.id', [$new->ulid, $taken->ulid])
        ->assertJsonPath('data.peer.routed_alerts.items.0.acknowledged', false)
        ->assertJsonPath('data.peer.tasks', [
            ['key' => 'alerts_to_take', 'count' => 1],
            ['key' => 'cases_to_contact', 'count' => 2],
        ]);

    $item = $response->json('data.peer.routed_alerts.items.0');
    expect($item)->toHaveKeys(['type', 'status', 'priority', 'place', 'created_at'])
        ->not->toHaveKeys(['description', 'lat', 'lng', 'contact', 'user', 'tracking_code']);
    expect((string) $response->getContent())->not->toContain('Récit confidentiel');

    // Résumés d'alertes d'autres personnes : jamais gardés dans le cache de l'appareil (Q73).
    expect((string) $response->headers->get('Cache-Control'))->toContain('no-store');
    expect($response->headers->has('ETag'))->toBeFalse();
});

it('vide les alertes routées d’un compte pair sans alerts.view_routed', function (): void {
    Role::findByName('pair', 'web')->revokePermissionTo('alerts.view_routed');
    $peer = FeedFixtures::member('pair');
    $contact = DirectoryContact::factory()->linkedTo($peer)->create();
    Alert::factory()->routedTo($contact)->create();
    CaseRecord::factory()->collectedBy($peer)->create();

    FeedFixtures::as($peer)->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonPath('data.peer.kpi.collected', 1)
        ->assertJsonPath('data.peer.routed_alerts', ['open' => 0, 'new' => 0, 'items' => []])
        ->assertJsonPath('data.peer.tasks', [['key' => 'cases_to_contact', 'count' => 1]]);
});

it('ne montre à un compte admin que les alertes routées vers ses propres fiches, jamais toutes', function (): void {
    $admin = FeedFixtures::member('admin');
    $mine = Alert::factory()->routedTo(DirectoryContact::factory()->linkedTo($admin)->create())->create();
    Alert::factory()->routedTo(DirectoryContact::factory()->linkedTo(FeedFixtures::member('pair'))->create())->create();
    Alert::factory()->create();

    FeedFixtures::as($admin)->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonPath('data.user.role', 'admin')
        ->assertJsonPath('data.peer.kpi.collected', 0)
        ->assertJsonPath('data.peer.routed_alerts.open', 1)
        ->assertJsonPath('data.peer.routed_alerts.items.*.id', [$mine->ulid]);
});

it('n’ouvre pas la section pair à un compte pair en attente de validation (RG-03)', function (): void {
    $pending = FeedFixtures::member('pair');
    $pending->forceFill(['status' => UserStatus::Pending])->save();

    FeedFixtures::as($pending)->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonPath('data.user.pending_validation', true)
        ->assertJsonPath('data.peer', null);
});

// --- GET thematiques ----------------------------------------------------------------------------------------------

it('liste les thématiques publiées sans compte, filtre par catégorie et répond 304 puis 404', function (): void {
    $droits = mobileCategory('droits', ['position' => 1]);
    $sante = mobileCategory('sante', ['position' => 2]);
    $a = Thematique::factory()->for($droits)->create(['published_at' => now()->subDays(2)]);
    $b = Thematique::factory()->for($sante)->create(['published_at' => now()->subDay()]);
    Thematique::factory()->for($droits)->draft()->create();
    Thematique::factory()->for($droits)->scheduled()->create();

    $response = $this->getJson('/api/v1/thematiques')
        ->assertOk()
        ->assertHeader('ETag')
        ->assertJsonPath('data.*.slug', [$b->slug, $a->slug])
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('meta.has_more', false)
        ->assertJsonPath('meta.categories.*.slug', ['droits', 'sante'])
        ->assertJsonPath('meta.categories.0.count', 1)
        ->assertJsonStructure(['data' => [['id', 'slug', 'title', 'summary', 'cover_url', 'reading_minutes', 'category', 'published_at', 'url']]]);

    expect($response->json('data.0'))->not->toHaveKeys(['body', 'views_count']);
    mobileRevalidates($response, '/api/v1/thematiques');

    $this->getJson('/api/v1/thematiques?categorie=droits')
        ->assertOk()
        ->assertJsonPath('data.*.slug', [$a->slug])
        ->assertJsonPath('meta.categorie', 'droits');

    $this->getJson('/api/v1/thematiques?categorie=inconnue')->assertNotFound()->assertJsonPath('code', 'not_found');
    $this->getJson('/api/v1/thematiques?categorie=Pas%20un%20slug')->assertUnprocessable()->assertJsonPath('code', 'validation_failed');
});

it('cherche les thématiques par titre ou résumé (?q=), comme le site', function (): void {
    $category = mobileCategory('droits');
    $byTitle = Thematique::factory()->for($category)->create(['title' => 'Consentement et respect', 'published_at' => now()->subDay()]);
    $bySummary = Thematique::factory()->for($category)->create(['summary' => 'Comprendre le consentement.', 'published_at' => now()->subDays(2)]);
    Thematique::factory()->for($category)->create(['title' => 'Santé mentale', 'summary' => 'Prendre soin de soi.']);

    $this->getJson('/api/v1/thematiques?q=consentement')
        ->assertOk()
        ->assertJsonPath('data.*.slug', [$byTitle->slug, $bySummary->slug])
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('meta.q', 'consentement');

    $this->getJson('/api/v1/thematiques?q='.str_repeat('a', 101))->assertUnprocessable()->assertJsonPath('code', 'validation_failed');
});

it('rend les fiches liées en cartes complètes : résumé et catégorie listée seulement', function (): void {
    $vbg = mobileCategory('vbg');
    $inactive = mobileCategory('ancienne', ['is_active' => false]);
    $listed = Thematique::factory()->for($vbg)->create(['summary' => 'Résumé lié.', 'published_at' => now()->subDay()]);
    $unlisted = Thematique::factory()->for($inactive)->create(['summary' => 'Autre résumé.', 'published_at' => now()->subDays(2)]);
    $thematique = Thematique::factory()->for($vbg)->create(['related_ids' => [$listed->getKey(), $unlisted->getKey()]]);

    $this->getJson('/api/v1/thematiques/'.$thematique->slug)
        ->assertOk()
        ->assertJsonPath('data.related.0.slug', $listed->slug)
        ->assertJsonPath('data.related.0.summary', 'Résumé lié.')
        ->assertJsonPath('data.related.0.category.slug', 'vbg')
        ->assertJsonPath('data.related.1.slug', $unlisted->slug)
        ->assertJsonPath('data.related.1.summary', 'Autre résumé.')
        ->assertJsonPath('data.related.1.category', null);
});

it('rend une fiche : sections, avertissement, contacts actifs et publics seulement ; 304 ; 404 si non publiée', function (): void {
    $category = mobileCategory('vbg');
    $thematique = Thematique::factory()->for($category)->create([
        'content_warning' => 'Cette fiche aborde des violences.',
        'related_contact_roles' => [DirectoryRole::Juriste->value],
        'views_count' => 0,
    ]);
    $public = DirectoryContact::factory()->role(DirectoryRole::Juriste)->create(['name' => 'Maître Ada']);
    DirectoryContact::factory()->role(DirectoryRole::Juriste)->hidden()->create(['name' => 'Fiche privée']);
    DirectoryContact::factory()->role(DirectoryRole::Juriste)->inactive()->create(['name' => 'Fiche inactive']);
    Resource::factory()->forThematique($thematique)->pdf()->create(['file_size' => 1_258_291]);

    $uri = '/api/v1/thematiques/'.$thematique->slug;
    $response = $this->getJson($uri)
        ->assertOk()
        ->assertJsonPath('data.slug', $thematique->slug)
        ->assertJsonPath('data.content_warning', 'Cette fiche aborde des violences.')
        ->assertJsonPath('data.category.slug', 'vbg')
        ->assertJsonPath('data.sections.0.title', 'À retenir')
        ->assertJsonPath('data.contacts.*.name', ['Maître Ada'])
        ->assertJsonPath('data.contacts.0.id', $public->getKey())
        ->assertJsonPath('data.primary_resource.file_size', 1_258_291)
        ->assertJsonPath('data.primary_resource.file_size_label', LocaleFormatter::make()->fileSize(1_258_291));

    expect($response->json('data.contacts.0'))->not->toHaveKeys(['email', 'notes', 'user_id']);
    expect($thematique->fresh()->views_count)->toBe(1);

    // Revalidation par l'appareil : 304, et ce n'est pas une nouvelle lecture.
    mobileRevalidates($response, $uri);
    expect($thematique->fresh()->views_count)->toBe(1);

    $draft = Thematique::factory()->draft()->create();
    $this->getJson('/api/v1/thematiques/'.$draft->slug)->assertNotFound();
    $this->getJson('/api/v1/thematiques/inconnue')->assertNotFound()->assertJsonPath('code', 'not_found');
});

// --- GET ressources -----------------------------------------------------------------------------------------------

it('pagine le catalogue des ressources avec le poids des fichiers, filtre par type et répond 304 puis 404', function (): void {
    Storage::fake(MediaUploader::privateDisk());
    $category = mobileCategory('droits');
    $pdf = Resource::factory()->pdf()->withFile()->for($category)->create(['published_at' => now()->subDay()]);
    $link = Resource::factory()->link()->create(['category_id' => null, 'published_at' => now()->subDays(2), 'external_url' => 'https://exemple.bj/guide']);
    $sheet = Resource::factory()->create(['category_id' => null, 'published_at' => now()->subDays(3)]);
    Resource::factory()->article()->create(['category_id' => null]);
    Resource::factory()->draft()->create(['category_id' => null]);

    $response = $this->getJson('/api/v1/ressources?per_page=2')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$pdf->getKey(), $link->getKey()])
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonPath('meta.categories.*.slug', ['droits'])
        ->assertJsonPath('data.0.kind', 'pdf')
        ->assertJsonPath('data.0.downloadable', true)
        ->assertJsonPath('data.0.file_size', (int) $pdf->file_size)
        ->assertJsonPath('data.0.file_size_label', LocaleFormatter::make()->fileSize((int) $pdf->file_size))
        ->assertJsonPath('data.0.download_url', route('api.ressources.download', ['resource' => $pdf->getKey()]))
        ->assertJsonPath('data.1.downloadable', false)
        ->assertJsonPath('data.1.download_url', null)
        ->assertJsonPath('data.1.external_url', 'https://exemple.bj/guide');

    expect($response->json('data.0'))->not->toHaveKey('file_path');
    mobileRevalidates($response, '/api/v1/ressources?per_page=2');

    $this->getJson('/api/v1/ressources?page=2&per_page=2')->assertOk()->assertJsonPath('data.*.id', [$sheet->getKey()]);
    $this->getJson('/api/v1/ressources?type=pdf')->assertOk()->assertJsonPath('data.*.id', [$pdf->getKey()])->assertJsonPath('meta.type', 'pdf');
    $this->getJson('/api/v1/ressources?type=article')->assertUnprocessable()->assertJsonPath('code', 'validation_failed');
    $this->getJson('/api/v1/ressources?categorie=inconnue')->assertNotFound()->assertJsonPath('code', 'not_found');

    $this->getJson('/api/v1/ressources?q='.urlencode((string) $link->title))
        ->assertOk()
        ->assertJsonPath('data.*.id', [$link->getKey()])
        ->assertJsonPath('meta.q', (string) $link->title);
});

it('délivre un lien signé de téléchargement compté et jamais gardé ; 404 sans fichier ou hors catalogue', function (): void {
    Storage::fake(MediaUploader::privateDisk());
    $pdf = Resource::factory()->pdf()->withFile()->create(['downloads_count' => 0]);

    $response = $this->withHeaders(['User-Agent' => 'Dart/3.5 (dart:io)'])
        ->getJson('/api/v1/ressources/'.$pdf->getKey().'/download')
        ->assertOk()
        ->assertJsonPath('data.id', $pdf->getKey())
        ->assertJsonPath('data.kind', 'pdf')
        ->assertJsonPath('data.file_size', (int) $pdf->file_size)
        ->assertJsonPath('data.filename', $pdf->slug.'.pdf');

    expect((string) $response->headers->get('Cache-Control'))->toContain('no-store');
    expect($response->headers->has('ETag'))->toBeFalse();
    expect($pdf->fresh()->downloads_count)->toBe(1);

    // `expires_at` = l'échéance portée par la signature, à la seconde.
    parse_str((string) parse_url((string) $response->json('data.url'), PHP_URL_QUERY), $query);
    expect(Carbon\CarbonImmutable::parse((string) $response->json('data.expires_at'))->getTimestamp())->toBe((int) $query['expires']);

    $this->withHeaders(['User-Agent' => 'Dart/3.5 (dart:io)'])->get((string) $response->json('data.url'))->assertOk();

    $link = Resource::factory()->link()->create();
    $draft = Resource::factory()->pdf()->withFile()->draft()->create();
    $article = Resource::factory()->article()->create(['file_path' => 'resources/a.pdf']);

    foreach ([$link, $draft, $article] as $resource) {
        $this->getJson('/api/v1/ressources/'.$resource->getKey().'/download')->assertNotFound();
    }
    $this->getJson('/api/v1/ressources/999999/download')->assertNotFound()->assertJsonPath('code', 'not_found');
});

it('ne compte pas le lien délivré à un robot, à un HEAD ni à un préchargement ; le redemander compte', function (): void {
    Storage::fake(MediaUploader::privateDisk());
    $pdf = Resource::factory()->pdf()->withFile()->create(['downloads_count' => 0]);
    $uri = '/api/v1/ressources/'.$pdf->getKey().'/download';

    $app = ['User-Agent' => 'Dart/3.5 (dart:io)'];

    $this->getJson($uri, ['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1)'])->assertOk();
    $this->getJson($uri, $app + ['Purpose' => 'prefetch'])->assertOk();
    $this->getJson($uri, $app + ['Sec-Purpose' => 'prefetch'])->assertOk();
    $this->call('HEAD', $uri, server: ['HTTP_USER_AGENT' => $app['User-Agent'], 'HTTP_ACCEPT' => 'application/json'])->assertOk();
    expect($pdf->fresh()->downloads_count)->toBe(0);

    $this->getJson($uri, $app)->assertOk();
    $this->getJson($uri, $app)->assertOk();
    expect($pdf->fresh()->downloads_count)->toBe(2);
});

it('répond 429 au-delà du limiteur resource_download, par ressource', function (): void {
    Storage::fake(MediaUploader::privateDisk());
    config(['xomiton.rate_limits.resource_download_per_minute' => 2]);
    $pdf = Resource::factory()->pdf()->withFile()->create();
    $other = Resource::factory()->pdf()->withFile()->create();
    $uri = '/api/v1/ressources/'.$pdf->getKey().'/download';

    $this->getJson($uri)->assertOk();
    $this->getJson($uri)->assertOk();
    $this->getJson($uri)->assertStatus(429)->assertHeader('Retry-After');

    $this->getJson('/api/v1/ressources/'.$other->getKey().'/download')->assertOk();
});

// --- GET users/{id}/posts -----------------------------------------------------------------------------------------

it('liste les publications visibles d’un profil, jamais les anonymes ; 304 ; curseur', function (): void {
    $reader = FeedFixtures::member();
    $author = FeedFixtures::member();
    $p1 = Post::factory()->for($author)->create(['published_at' => now()->subMinutes(1)]);
    $p2 = Post::factory()->for($author)->create(['published_at' => now()->subMinutes(2)]);
    $p3 = Post::factory()->for($author)->create(['published_at' => now()->subMinutes(3)]);
    Post::factory()->for($author)->anonymous()->create(['published_at' => now()]);
    Post::factory()->for($author)->visibility(PostVisibility::Followers)->create();
    Post::factory()->for($author)->visibility(PostVisibility::Me)->create();
    Post::factory()->for($author)->hidden()->create();
    Post::factory()->for($author)->inGroup(Group::factory()->create())->create();
    $hiddenByReader = Post::factory()->for($author)->create(['published_at' => now()->subMinutes(4)]);
    PostHide::query()->create(['post_id' => $hiddenByReader->getKey(), 'user_id' => $reader->getKey()]);
    Post::factory()->for($reader)->create();

    $uri = '/api/v1/users/'.$author->getKey().'/posts?per_page=2';
    $response = FeedFixtures::as($reader)->getJson($uri)
        ->assertOk()
        ->assertJsonPath('data.*.id', [$p1->ulid, $p2->ulid])
        ->assertJsonPath('data.0.author.id', $author->getKey())
        ->assertJsonPath('meta.user_id', $author->getKey())
        ->assertJsonPath('meta.per_page', 2);

    mobileRevalidates($response, $uri, $reader);

    FeedFixtures::as($reader)->getJson('/api/v1/users/'.$author->getKey().'/posts?per_page=2&cursor='.$response->json('meta.next_cursor'))
        ->assertOk()
        ->assertJsonPath('data.*.id', [$p3->ulid])
        ->assertJsonPath('meta.next_cursor', null);

    // Abonné·e : la publication réservée aux abonné·es apparaît.
    FeedFixtures::follow($reader, $author);
    FeedFixtures::as($reader)->getJson('/api/v1/users/'.$author->getKey().'/posts')->assertOk()->assertJsonCount(4, 'data');

    // Soi-même : ses publications « moi seul·e » comprises, jamais l'anonyme.
    FeedFixtures::as($author)->getJson('/api/v1/users/'.$author->getKey().'/posts')->assertOk()->assertJsonCount(6, 'data');
});

it('répond 404 pour un profil inconnu, privé, réservé aux abonné·es, bloqué, suspendu, anonymisé ou supprimé', function (): void {
    $reader = FeedFixtures::member();
    $this->getJson('/api/v1/users/'.$reader->getKey().'/posts')->assertUnauthorized();

    $private = FeedFixtures::member();
    $private->profile->forceFill(['visibility' => ProfileVisibility::Private])->save();
    $followersOnly = FeedFixtures::member();
    $followersOnly->profile->forceFill(['visibility' => ProfileVisibility::Followers])->save();
    $blocker = FeedFixtures::member();
    UserBlock::query()->create(['user_id' => $blocker->getKey(), 'blocked_user_id' => $reader->getKey()]);
    $suspended = FeedFixtures::member();
    $suspended->forceFill(['status' => UserStatus::Suspended])->save();

    $anonymized = FeedFixtures::member();
    $anonymized->forceFill(['email' => User::anonymizedEmail((int) $anonymized->getKey())])->save();
    $deleted = FeedFixtures::member();
    $deleted->delete();

    foreach ([999999, $private->getKey(), $followersOnly->getKey(), $blocker->getKey(), $suspended->getKey(), $anonymized->getKey(), $deleted->getKey()] as $id) {
        FeedFixtures::as($reader)->getJson('/api/v1/users/'.$id.'/posts')->assertNotFound()->assertJsonPath('code', 'not_found');
    }

    FeedFixtures::follow($reader, $followersOnly);
    FeedFixtures::as($reader)->getJson('/api/v1/users/'.$followersOnly->getKey().'/posts')->assertOk();
    FeedFixtures::as($private)->getJson('/api/v1/users/'.$private->getKey().'/posts')->assertOk();
});

it('filtre les publications d’un profil par étiquette (?tag=)', function (): void {
    $reader = FeedFixtures::member();
    $author = FeedFixtures::member();
    $question = Post::factory()->for($author)->tagged(PostTag::Question)->create();
    Post::factory()->for($author)->tagged(PostTag::Testimony)->create();

    FeedFixtures::as($reader)->getJson('/api/v1/users/'.$author->getKey().'/posts?tag=question')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$question->ulid])
        ->assertJsonPath('meta.tag', 'question');

    FeedFixtures::as($reader)->getJson('/api/v1/users/'.$author->getKey().'/posts?tag=inconnue')->assertUnprocessable();
});

it('garde le même ETag avec une image du disque privé dans la fenêtre du lien signé (304), puis le renouvelle', function (): void {
    Storage::fake(MediaUploader::privateDisk());
    Storage::disk(MediaUploader::privateDisk())->put('posts/photo.webp', 'image');
    $this->travelTo(now()->startOfHour()->addMinute());

    $reader = FeedFixtures::member();
    $author = FeedFixtures::member();
    $post = Post::factory()->for($author)->create(['published_at' => now()->subMinute()]);
    PostMedia::factory()->for($post)->create(['path' => 'posts/photo.webp']);

    $uri = '/api/v1/users/'.$author->getKey().'/posts';
    $first = FeedFixtures::as($reader)->getJson($uri)->assertOk();
    $url = (string) $first->json('data.0.media.0.url');
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    expect($url)->toContain('/media/')
        ->and((int) $query['expires'])->toBe(now()->startOfHour()->addMinutes(15 + 60)->getTimestamp());
    $feed = FeedFixtures::as($reader)->getJson('/api/v1/feed')->assertOk();

    $this->travel(10)->minutes();
    mobileRevalidates($first, $uri, $reader);
    mobileRevalidates($feed, '/api/v1/feed', $reader);

    // Fenêtre suivante : nouveau lien (toujours valable au moins media_url_minutes), donc nouvel ETag et 200.
    $this->travel(5)->minutes();
    $next = FeedFixtures::as($reader)->withHeaders(['If-None-Match' => (string) $first->headers->get('ETag')])->getJson($uri)->assertOk();
    expect($next->headers->get('ETag'))->not->toBe($first->headers->get('ETag'))
        ->and((string) $next->json('data.0.media.0.url'))->not->toBe($url);
    $this->get((string) $next->json('data.0.media.0.url'))->assertOk();
});

it('range les routes publiques de contenu dans le groupe anonyme et le reste derrière auth:sanctum (ADR-31)', function (): void {
    $routes = collect(Illuminate\Support\Facades\Route::getRoutes()->getRoutes())->keyBy(fn ($route) => $route->getName());

    foreach (['api.thematiques.index', 'api.thematiques.show', 'api.ressources.index', 'api.ressources.download'] as $name) {
        expect($routes[$name]->gatherMiddleware())->not->toContain('auth:sanctum')->toContain('conditional');
    }

    expect($routes['api.ressources.download']->gatherMiddleware())->toContain('throttle:resource_download');

    foreach (['api.dashboard', 'api.users.posts'] as $name) {
        expect($routes[$name]->gatherMiddleware())->toContain('auth:sanctum');
    }
});
