<?php

declare(strict_types=1);

use App\Enums\ConsentKind;
use App\Enums\InvitationStatus;
use App\Enums\ProfileVisibility;
use App\Enums\RsvpStatus;
use App\Models\AuditLog;
use App\Models\Consent;
use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\Invitation;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\User;
use App\Models\UserBlock;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FeedFixtures as F;

/*
 * Profil d'un·e membre (P8-07, lot profil ; docs/API.md §18) : GET members/{pseudo} (en-tête, À propos, compteurs),
 * connexions, médias, événements, GET me/activity, avatar et couverture (POST · DELETE me/profile/{kind}). RG-02 : nom
 * réel, e-mail, téléphone, tranche d'âge et contact de confiance jamais servis ; champ sensible absent sans choix
 * explicite ; profil privé, réservé aux abonné·es, bloqué ou suspendu → 404.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->awa = F::member();
    $this->bea = F::member();
});

function profileConnect(User $a, User $b): Invitation
{
    return Invitation::query()->create(['from_user_id' => $a->id, 'to_user_id' => $b->id, 'status' => InvitationStatus::Accepted, 'responded_at' => now()]);
}

function profileConsent(User $user, bool $granted = true): void
{
    Consent::query()->create(['user_id' => $user->id, 'kind' => ConsentKind::SensitiveData, 'version' => '1', 'granted' => $granted]);
}

it('sert l’en-tête d’un profil de la communauté sans jamais le nom réel, l’e-mail, le téléphone ni l’âge (RG-02)', function (): void {
    $this->bea->profile->forceFill(['pronouns' => 'elle', 'bio' => 'Militante.', 'age_range' => '18-24', 'trusted_contact_name' => 'Maman', 'interests' => ['sante']])->save();
    Post::factory()->count(2)->create(['user_id' => $this->bea->id]);
    Post::factory()->anonymous()->create(['user_id' => $this->bea->id]);

    $response = F::as($this->awa)->getJson('/api/v1/members/'.$this->bea->pseudo)
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('data.pseudo', $this->bea->pseudo)
        ->assertJsonPath('data.is_self', false)
        ->assertJsonPath('data.pronouns', 'elle')
        ->assertJsonPath('data.stats.posts', 2)
        ->assertJsonPath('data.relationship.following', false)
        ->assertJsonPath('data.sensitive', [])
        ->assertJsonMissingPath('data.completion')
        ->assertJsonMissingPath('data.age_range');

    $body = $response->getContent();
    expect($body)->not->toContain($this->bea->name)
        ->and($body)->not->toContain((string) $this->bea->email)
        ->and($body)->not->toContain('Maman')
        ->and($body)->not->toContain('18-24');
});

it('ne montre un champ sensible qu’avec un choix explicite et un consentement en cours', function (): void {
    $this->bea->profile->forceFill(['gender_identity' => 'femme_trans', 'orientation' => 'lesbienne', 'ally_status' => 'ressource'])->save();
    profileConsent($this->bea);

    F::as($this->awa)->getJson('/api/v1/members/'.$this->bea->pseudo)->assertJsonPath('data.sensitive', []);

    $this->bea->profile->forceFill(['shared_fields' => ['orientation', 'ally_status']])->save();
    $shared = F::as($this->awa)->getJson('/api/v1/members/'.$this->bea->pseudo)->assertOk();
    expect(array_column($shared->json('data.sensitive'), 'field'))->toBe(['orientation', 'ally_status'])
        ->and($shared->getContent())->not->toContain('femme_trans');

    profileConsent($this->bea, false);
    $withdrawn = F::as($this->awa)->getJson('/api/v1/members/'.$this->bea->pseudo);
    expect(array_column($withdrawn->json('data.sensitive'), 'field'))->toBe(['ally_status']);
});

it('répond 404 pour un profil privé, réservé aux abonné·es non suivi·e, bloqué, suspendu ou inconnu', function (): void {
    $this->bea->profile->forceFill(['visibility' => ProfileVisibility::Followers])->save();
    F::as($this->awa)->getJson('/api/v1/members/'.$this->bea->pseudo)->assertNotFound();
    F::follow($this->awa, $this->bea);
    F::as($this->awa)->getJson('/api/v1/members/'.$this->bea->pseudo)->assertOk();

    $this->bea->profile->forceFill(['visibility' => ProfileVisibility::Private])->save();
    F::as($this->awa)->getJson('/api/v1/members/'.$this->bea->pseudo)->assertNotFound();
    F::as($this->bea)->getJson('/api/v1/members/'.$this->bea->pseudo)->assertOk()->assertJsonPath('data.is_self', true)->assertJsonPath('data.visibility', 'private');

    $cyd = F::member();
    UserBlock::query()->create(['user_id' => $cyd->id, 'blocked_user_id' => $this->awa->id]);
    F::as($this->awa)->getJson('/api/v1/members/'.$cyd->pseudo)->assertNotFound();
    F::as($this->awa)->getJson('/api/v1/members/'.$cyd->pseudo.'/connections')->assertNotFound();

    $dan = User::factory()->withProfile()->suspended()->create();
    F::as($this->awa)->getJson('/api/v1/members/'.$dan->pseudo)->assertNotFound();
    F::as($this->awa)->getJson('/api/v1/members/personne-inconnue')->assertNotFound();
});

it('liste les connexions acceptées, sans blocage ni profil privé d’une tierce personne, avec recherche', function (): void {
    $cyd = F::member();
    $dan = F::member();
    $eve = F::member();
    profileConnect($this->bea, $cyd);
    profileConnect($dan, $this->bea);
    profileConnect($this->bea, $eve);
    Invitation::query()->create(['from_user_id' => $this->bea->id, 'to_user_id' => F::member()->id, 'status' => InvitationStatus::Pending]);
    $eve->profile->forceFill(['visibility' => ProfileVisibility::Private])->save();
    UserBlock::query()->create(['user_id' => $this->awa->id, 'blocked_user_id' => $dan->id]);

    $page = F::as($this->awa)->getJson('/api/v1/members/'.$this->bea->pseudo.'/connections')->assertOk();
    expect(array_column($page->json('data'), 'pseudo'))->toBe([$cyd->pseudo])
        ->and($page->json('meta.total'))->toBe(1)
        ->and($page->json('data.0.url'))->toBe(route('membres.show', ['pseudo' => $cyd->pseudo]));

    // Son propre profil : toutes ses connexions joignables, même au profil privé.
    $mine = F::as($this->bea)->getJson('/api/v1/members/'.$this->bea->pseudo.'/connections?q='.urlencode(mb_substr($eve->pseudo, 0, 6)))->assertOk();
    expect(array_column($mine->json('data'), 'pseudo'))->toContain($eve->pseudo);
    F::as($this->bea)->getJson('/api/v1/members/'.$this->bea->pseudo.'/connections?q='.str_repeat('a', 61))->assertUnprocessable();
});

it('sert les images des publications visibles, jamais celles d’une publication anonyme', function (): void {
    $visible = Post::factory()->create(['user_id' => $this->bea->id]);
    PostMedia::factory()->create(['post_id' => $visible->id, 'path' => 'assets/images/xomiton/profile-cover.jpg']);
    $anonymous = Post::factory()->anonymous()->create(['user_id' => $this->bea->id]);
    PostMedia::factory()->create(['post_id' => $anonymous->id, 'path' => 'assets/images/xomiton/avatar1.png']);

    $media = F::as($this->awa)->getJson('/api/v1/members/'.$this->bea->pseudo.'/media')->assertOk()->json('data');

    expect($media)->toHaveCount(1)
        ->and($media[0]['url'])->toContain('profile-cover.jpg')
        ->and($media[0]['href'])->toContain($visible->ulid);
});

it('liste les événements organisés par une autre personne, jamais sa participation', function (): void {
    $organized = Event::factory()->create(['organizer_id' => $this->bea->id, 'title' => 'Causerie organisée']);
    $attended = Event::factory()->create(['title' => 'Atelier fréquenté']);
    EventRsvp::query()->create(['event_id' => $attended->id, 'user_id' => $this->bea->id, 'status' => RsvpStatus::Going, 'reminder_opt_in' => true]);

    $other = F::as($this->awa)->getJson('/api/v1/members/'.$this->bea->pseudo.'/events')->assertOk();
    expect(array_column($other->json('data'), 'title'))->toBe(['Causerie organisée'])
        ->and($other->json('suggestions'))->toBe([]);

    $mine = F::as($this->bea)->getJson('/api/v1/members/'.$this->bea->pseudo.'/events')->assertOk();
    expect(array_column($mine->json('data'), 'title'))->toEqualCanonicalizing(['Causerie organisée', 'Atelier fréquenté'])
        ->and($organized->ulid)->toBeString();
});

it('sert le journal d’activité de la personne seule, sans contribution anonyme ni IP', function (): void {
    Post::factory()->create(['user_id' => $this->awa->id, 'body' => 'Mon premier message public']);
    Post::factory()->anonymous()->create(['user_id' => $this->awa->id, 'body' => 'Témoignage anonyme']);
    AuditLog::query()->create(['user_id' => $this->awa->id, 'actor' => $this->awa->pseudo, 'action' => 'auth.authenticated', 'ip' => '203.0.113.9']);
    AuditLog::query()->create(['user_id' => $this->awa->id, 'actor' => $this->awa->pseudo, 'action' => 'alerts.created']);

    $response = F::as($this->awa)->getJson('/api/v1/me/activity')->assertOk()->assertHeader('Cache-Control', 'no-store, private');

    expect(array_column($response->json('data'), 'type'))->toEqualCanonicalizing(['post', 'login'])
        ->and($response->getContent())->not->toContain('Témoignage anonyme')
        ->and($response->getContent())->not->toContain('203.0.113.9');
});

it('enregistre l’avatar et la couverture ré-encodés sans EXIF, supprime l’ancien fichier et recalcule la complétion', function (): void {
    Storage::fake('public');
    $this->awa->profile->forceFill(['avatar_path' => null])->save();
    $file = UploadedFile::fake()->createWithContent('photo.jpg', F::jpegWithExif());

    $first = F::as($this->awa)->post('/api/v1/me/profile/avatar', ['image' => $file], ['Accept' => 'application/json'])->assertOk();
    $path = $this->awa->profile->fresh()->avatar_path;

    expect($path)->toEndWith('.webp')
        ->and(Storage::disk('public')->exists($path))->toBeTrue()
        ->and(Storage::disk('public')->get($path))->not->toContain('Xomiton Test')
        ->and($first->json('data.completion'))->toBe($this->awa->profile->fresh()->completion);

    F::as($this->awa)->post('/api/v1/me/profile/avatar', ['image' => UploadedFile::fake()->createWithContent('b.jpg', F::jpegWithExif(400, 300))], ['Accept' => 'application/json'])->assertOk();
    expect(Storage::disk('public')->exists($path))->toBeFalse();

    F::as($this->awa)->post('/api/v1/me/profile/cover', ['image' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonValidationErrors('image');

    F::as($this->awa)->deleteJson('/api/v1/me/profile/avatar')->assertOk();
    expect($this->awa->profile->fresh()->avatar_path)->toBeNull();
});

// --- Revue du lot profil ------------------------------------------------------------------------------------------------

/** Envoie un avatar ou une couverture (JPEG avec EXIF) pour `$user`. */
function profileUpload(User $user, string $kind = 'avatar', int $width = 800, int $height = 600): Illuminate\Testing\TestResponse
{
    return F::as($user)->post('/api/v1/me/profile/'.$kind, ['image' => UploadedFile::fake()->createWithContent('photo.jpg', F::jpegWithExif($width, $height))], ['Accept' => 'application/json']);
}

it('retire repli et variantes au remplacement et au retrait, jamais un visuel importé assets/', function (): void {
    Storage::fake('public');
    $this->awa->profile->forceFill(['avatar_path' => null, 'cover_path' => 'assets/images/xomiton/profile-cover.jpg'])->save();
    Storage::disk('public')->put('assets/images/xomiton/profile-cover.jpg', 'visuel du prototype');

    profileUpload($this->awa)->assertOk()->assertJsonPath('data.has_avatar', true);
    $first = array_values(array_filter(Storage::disk('public')->allFiles(), static fn (string $file): bool => ! str_starts_with($file, 'assets/')));
    expect(count($first))->toBeGreaterThan(1);

    profileUpload($this->awa, 'avatar', 400, 300)->assertOk();
    expect(array_intersect(Storage::disk('public')->allFiles(), $first))->toBe([]);

    profileUpload($this->awa, 'cover')->assertOk()->assertJsonPath('data.has_cover', true);
    expect(Storage::disk('public')->exists('assets/images/xomiton/profile-cover.jpg'))->toBeTrue();

    F::as($this->awa)->deleteJson('/api/v1/me/profile/avatar')->assertOk()->assertJsonPath('data.has_avatar', false);
    F::as($this->awa)->deleteJson('/api/v1/me/profile/cover')->assertOk()->assertJsonPath('data.has_cover', false);
    expect(Storage::disk('public')->allFiles())->toBe(['assets/images/xomiton/profile-cover.jpg']);
});

it('efface avatar, couverture, repli et variantes à l’anonymisation du compte (RGPD)', function (): void {
    Storage::fake('public');
    profileUpload($this->awa)->assertOk();
    profileUpload($this->awa, 'cover')->assertOk();
    expect(count(Storage::disk('public')->allFiles()))->toBeGreaterThan(2);

    $this->awa->delete();
    app(App\Application\Retention\AnonymizeDeletedAccounts::class)->anonymize($this->awa->fresh() ?? User::withTrashed()->findOrFail($this->awa->id));

    expect(Storage::disk('public')->allFiles())->toBe([]);
});

it('exige une adresse e-mail confirmée pour changer une image (403), sans rien enregistrer', function (): void {
    Storage::fake('public');
    $unverified = F::member(verified: false);

    profileUpload($unverified)->assertForbidden();
    expect(Storage::disk('public')->allFiles())->toBe([])
        ->and((string) $unverified->profile->fresh()->avatar_path)->not->toEndWith('.webp');

    F::as($unverified)->getJson('/api/v1/members/'.$unverified->pseudo)->assertOk()->assertJsonPath('data.can_change_images', false);
    F::as($this->awa)->getJson('/api/v1/members/'.$this->awa->pseudo)->assertOk()->assertJsonPath('data.can_change_images', true);
});

it('limite envois et retraits d’images (throttle:profile_image, 429)', function (): void {
    config(['xomiton.rate_limits.profile_image_per_hour' => 1]);

    F::as($this->awa)->post('/api/v1/me/profile/cover', ['image' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])
        ->assertUnprocessable();
    F::as($this->awa)->deleteJson('/api/v1/me/profile/avatar')->assertTooManyRequests();
});

it('ne sert les images d’album d’autrui que selon leur visibilité (abonné·es, jamais privé)', function (): void {
    $albums = [];
    foreach ([ProfileVisibility::Community, ProfileVisibility::Followers, ProfileVisibility::Private] as $visibility) {
        $album = App\Models\Album::factory()->visibility($visibility)->create(['user_id' => $this->bea->id]);
        $albums[$visibility->value] = App\Models\AlbumMedia::factory()->create(['album_id' => $album->id, 'path' => 'assets/images/xomiton/profile-cover.jpg']);
    }
    $ids = static fn (array $media): array => array_column($media, 'id');

    $stranger = F::as($this->awa)->getJson('/api/v1/members/'.$this->bea->pseudo.'/media')->assertOk()->json('data');
    expect($ids($stranger))->toBe(['album-'.$albums['community']->id]);

    App\Models\Follow::query()->create(['follower_id' => $this->awa->id, 'followed_id' => $this->bea->id]);
    $follower = F::as($this->awa)->getJson('/api/v1/members/'.$this->bea->pseudo.'/media')->assertOk()->json('data');
    expect($ids($follower))->toEqualCanonicalizing(['album-'.$albums['community']->id, 'album-'.$albums['followers']->id]);

    $own = F::as($this->bea)->getJson('/api/v1/members/'.$this->bea->pseudo.'/media')->assertOk()->json('data');
    expect($ids($own))->toContain('album-'.$albums['private']->id);
});

it('ne montre pas sur le profil d’autrui l’événement d’un groupe privé dont on n’est pas membre', function (): void {
    $group = App\Models\Group::factory()->private()->create();
    Event::factory()->forGroup($group)->create(['organizer_id' => $this->bea->id, 'title' => 'Cercle privé']);
    Event::factory()->create(['organizer_id' => $this->bea->id, 'title' => 'Causerie ouverte']);

    $titles = array_column(F::as($this->awa)->getJson('/api/v1/members/'.$this->bea->pseudo.'/events')->assertOk()->json('data'), 'title');
    expect($titles)->toBe(['Causerie ouverte']);
});

it('répond 404 au profil d’autrui pour un compte sans social.access, pas au sien', function (): void {
    $this->awa->syncRoles([]);
    app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    F::as($this->awa->fresh())->getJson('/api/v1/members/'.$this->bea->pseudo)->assertNotFound();
    F::as($this->awa->fresh())->getJson('/api/v1/members/'.$this->awa->pseudo)->assertOk();
});

it('ne montre aux autres que le département, et « pair » seulement une fois validé (RG-03)', function (): void {
    $commune = App\Models\Commune::factory()->create(['name' => 'Abomey-Calavi']);
    $this->bea->forceFill(['zone_id' => $commune->zone_id, 'commune_id' => $commune->id, 'status' => App\Enums\UserStatus::Pending])->save();
    $this->bea->syncRoles(['pair']);
    $this->bea->profile->forceFill(['member_type' => App\Enums\MemberType::Peer, 'show_zone' => true])->save();
    $zone = (string) $commune->zone->name;

    $other = F::as($this->awa)->getJson('/api/v1/members/'.$this->bea->pseudo)->assertOk()
        ->assertJsonPath('data.zone_label', $zone)
        ->assertJsonPath('data.member_type', 'membre')
        ->assertJsonPath('data.role_label', __('profile.roles.membre'))
        ->assertJsonPath('data.verified', false);
    expect($other->getContent())->not->toContain('Abomey-Calavi');

    F::as($this->bea)->getJson('/api/v1/members/'.$this->bea->pseudo)->assertOk()
        ->assertJsonPath('data.zone_label', 'Abomey-Calavi, '.$zone)
        ->assertJsonPath('data.member_type', 'pair');

    $this->bea->forceFill(['status' => App\Enums\UserStatus::Active])->save();
    F::as($this->awa)->getJson('/api/v1/members/'.$this->bea->pseudo)->assertOk()
        ->assertJsonPath('data.member_type', 'pair')
        ->assertJsonPath('data.role_label', __('profile.roles.pair'));

    $this->bea->profile->forceFill(['member_type' => App\Enums\MemberType::Ally])->save();
    F::as($this->awa)->getJson('/api/v1/members/'.$this->bea->pseudo)->assertOk()->assertJsonPath('data.member_type', 'membre');
});

it('ne compte parmi abonné·es et abonnements ni compte suspendu, ni anonymisé, ni bloqué', function (): void {
    $active = F::member();
    $suspended = F::member();
    $suspended->forceFill(['status' => App\Enums\UserStatus::Suspended])->save();
    $blocked = F::member();
    UserBlock::query()->create(['user_id' => $this->bea->id, 'blocked_user_id' => $blocked->id]);
    $gone = F::member();
    $gone->forceFill(['email' => User::anonymizedEmail($gone->id)])->save();

    foreach ([$active, $suspended, $blocked, $gone] as $follower) {
        App\Models\Follow::query()->create(['follower_id' => $follower->id, 'followed_id' => $this->bea->id]);
        App\Models\Follow::query()->create(['follower_id' => $this->bea->id, 'followed_id' => $follower->id]);
    }

    F::as($this->awa)->getJson('/api/v1/members/'.$this->bea->pseudo)->assertOk()
        ->assertJsonPath('data.stats.followers', 1)
        ->assertJsonPath('data.stats.following', 1);
});
