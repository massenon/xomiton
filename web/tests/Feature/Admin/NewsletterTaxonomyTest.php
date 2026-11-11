<?php

declare(strict_types=1);

use App\Enums\CampaignStatus;
use App\Livewire\Admin\Newsletter\Index as NewsletterScreen;
use App\Livewire\Admin\Newsletter\Taxonomy;
use App\Models\AuditLog;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterInterest;
use App\Models\NewsletterSubscriber;
use App\Models\NewsletterTag;
use App\Models\Profile;
use App\Models\User;
use Database\Seeders\NewsletterTaxonomySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

/*
 * Onglet « Tags et centres d'intérêt » de /admin/newsletter : CRUD des tags de segmentation et des centres d'intérêt
 * (colonnes réelles : `slug` et `label`), slug unique dérivé du libellé et figé, suppression REFUSÉE tant que l'élément
 * est utilisé (abonné·es, profils, campagnes non terminées), journal, permission `newsletter.manage_subscribers` rejouée.
 */

beforeEach(function (): void {
    $this->seed([RolesAndPermissionsSeeder::class, NewsletterTaxonomySeeder::class]);
});

function taxUser(string $role = 'admin'): User
{
    $user = User::factory()->create();
    $user->syncRoles([$role]);

    return $user;
}

function taxScreen(?User $user = null)
{
    return Livewire::actingAs($user ?? taxUser())->test(Taxonomy::class);
}

it('affiche l’onglet dans la Newsletter et y montre tags et centres d’intérêt avec leur usage', function (): void {
    NewsletterSubscriber::factory()->create()->syncTags(['presse']);

    $html = Livewire::actingAs(taxUser())->test(NewsletterScreen::class, ['tab' => 'taxonomie'])->assertSee('Tags et centres d’intérêt')->html();

    expect($html)->toContain('Presse')->toContain('Sécurité numérique')->toContain('1 abonné·es');
});

it('masque l’onglet à qui n’a pas la permission', function (): void {
    $user = taxUser('moderateur');
    $user->givePermissionTo('newsletter.manage_campaigns');

    Livewire::actingAs($user)->test(NewsletterScreen::class)->assertDontSee('Tags et centres d’intérêt');
    Livewire::actingAs(taxUser('moderateur'))->test(Taxonomy::class)->assertForbidden();
});

it('crée un tag et un centre d’intérêt : slug dérivé, unique, 40 caractères au plus, journal', function (): void {
    $admin = taxUser();

    taxScreen($admin)->call('openCreate', 'tag')->set('label', 'Élu·es locaux')->call('save')->assertHasNoErrors();
    taxScreen($admin)->call('openCreate', 'interest')->set('label', 'Sport & bien-être')->call('save')->assertHasNoErrors();

    $tag = NewsletterTag::query()->where('label', 'Élu·es locaux')->firstOrFail();
    expect($tag->slug)->toBe('elues-locaux')->and(NewsletterInterest::query()->where('slug', 'sport-bien-etre')->exists())->toBeTrue();

    $log = AuditLog::query()->where('action', 'newsletter_taxonomy.created')->latest('id')->first();
    expect($log->user_id)->toBe($admin->id)->and($log->detail)->toContain('type=interest')->toContain('slug=sport-bien-etre');
});

it('ajoute un suffixe quand le slug existe déjà et borne le slug à 40 caractères', function (): void {
    NewsletterTag::query()->create(['slug' => 'presse-locale', 'label' => 'Autre']);

    taxScreen()->call('openCreate', 'tag')->set('label', 'Presse locale')->call('save')->assertHasNoErrors();
    taxScreen()->call('openCreate', 'tag')->set('label', str_repeat('abcdefghi ', 6))->call('save')->assertHasNoErrors();

    expect(NewsletterTag::query()->where('label', 'Presse locale')->value('slug'))->toBe('presse-locale-2');

    foreach (NewsletterTag::query()->pluck('slug') as $slug) {
        expect(strlen($slug))->toBeLessThanOrEqual(40);
    }
});

it('refuse un libellé vide, trop court, trop long, sans lettre ni chiffre, ou déjà pris (sans tenir compte de la casse)', function (string $label): void {
    taxScreen()->call('openCreate', 'tag')->set('label', $label)->call('save')->assertHasErrors(['label']);

    expect(NewsletterTag::query()->count())->toBe(5);
})->with([
    'vide' => [''],
    'trop court' => ['a'],
    'trop long' => [str_repeat('a', 61)],
    'sans lettre' => ['!!!'],
    'déjà pris' => ['PRESSE'],
]);

it('renomme sans changer le slug, refuse un libellé déjà pris par un autre, journalise', function (): void {
    $tag = NewsletterTag::query()->where('slug', 'presse')->firstOrFail();
    $subscriber = NewsletterSubscriber::factory()->create();
    $subscriber->syncTags(['presse']);

    $screen = taxScreen()->call('openEdit', 'tag', $tag->id)->assertSet('label', 'Presse');
    $screen->set('label', 'Médias')->call('save')->assertHasNoErrors();

    expect($tag->fresh()->label)->toBe('Médias')->and($tag->fresh()->slug)->toBe('presse')
        ->and($subscriber->fresh()->tags)->toBe(['presse']);

    taxScreen()->call('openEdit', 'tag', $tag->id)->set('label', 'membre')->call('save')->assertHasErrors(['label']);
    taxScreen()->call('openEdit', 'tag', $tag->id)->set('label', 'Médias')->call('save')->assertHasNoErrors();

    expect(AuditLog::query()->where('action', 'newsletter_taxonomy.updated')->latest('id')->value('detail'))->toContain('ancien libellé=Médias');
});

it('supprime un élément inutilisé après confirmation et journalise', function (): void {
    $admin = taxUser();
    $tag = NewsletterTag::query()->where('slug', 'bailleur')->firstOrFail();

    $screen = taxScreen($admin)->call('confirmDelete', 'tag', $tag->id)->assertSet('itemId', $tag->id);
    expect(NewsletterTag::query()->whereKey($tag->id)->exists())->toBeTrue();

    $screen->call('delete');

    expect(NewsletterTag::query()->whereKey($tag->id)->exists())->toBeFalse();
    $log = AuditLog::query()->where('action', 'newsletter_taxonomy.deleted')->firstOrFail();
    expect($log->user_id)->toBe($admin->id)->and($log->detail)->toContain('slug=bailleur');
});

it('REFUSE la suppression d’un tag porté par un·e abonné·e et d’un centre d’intérêt choisi par un profil', function (): void {
    $tag = NewsletterTag::query()->where('slug', 'presse')->firstOrFail();
    NewsletterSubscriber::factory()->create()->syncTags(['presse']);
    $interest = NewsletterInterest::query()->where('slug', 'sante')->firstOrFail();
    $profile = Profile::factory()->create(['interests' => ['sante']]);

    $screen = taxScreen()->call('confirmDelete', 'tag', $tag->id);
    expect($screen->html())->toContain('ne peut pas être supprimé');
    $screen->call('delete');
    taxScreen()->call('confirmDelete', 'interest', $interest->id)->call('delete');

    expect(NewsletterTag::query()->whereKey($tag->id)->exists())->toBeTrue()
        ->and(NewsletterInterest::query()->whereKey($interest->id)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'newsletter_taxonomy.deleted')->exists())->toBeFalse()
        ->and($profile->fresh()->interests)->toBe(['sante']);
});

it('refuse la suppression tant qu’une campagne non terminée cible l’élément, l’autorise une fois la campagne envoyée', function (): void {
    $tag = NewsletterTag::query()->where('slug', 'partenaire')->firstOrFail();
    $campaign = NewsletterCampaign::factory()->create(['status' => CampaignStatus::Draft, 'segment' => ['tags' => ['partenaire']]]);

    taxScreen()->call('confirmDelete', 'tag', $tag->id)->call('delete');
    expect(NewsletterTag::query()->whereKey($tag->id)->exists())->toBeTrue();

    $campaign->update(['status' => CampaignStatus::Sent]);
    taxScreen()->call('confirmDelete', 'tag', $tag->id)->call('delete');
    expect(NewsletterTag::query()->whereKey($tag->id)->exists())->toBeFalse();
});

it('gère un élément déjà supprimé', function (): void {
    $tag = NewsletterTag::query()->where('slug', 'bailleur')->firstOrFail();
    $screen = taxScreen()->call('confirmDelete', 'tag', $tag->id);
    $tag->delete();

    $screen->call('delete');
    taxScreen()->call('openEdit', 'tag', 999999)->assertSet('itemId', 0);
    taxScreen()->call('confirmDelete', 'interest', 999999)->assertSet('itemId', 0);
    expect(AuditLog::query()->where('action', 'newsletter_taxonomy.deleted')->exists())->toBeFalse();
});

it('verrouille la famille et l’identifiant, et ignore une famille inconnue', function (): void {
    $screen = taxScreen();

    expect(fn () => $screen->set('kind', 'interest'))->toThrow(CannotUpdateLockedPropertyException::class);
    expect(fn () => $screen->set('itemId', 3))->toThrow(CannotUpdateLockedPropertyException::class);

    $screen->call('openCreate', 'role')->assertSet('kind', 'tag');
    expect(NewsletterTag::query()->count())->toBe(5);
});

it('rejoue la permission à chaque action : 403 après le retrait du rôle', function (string $action, array $arguments): void {
    $admin = taxUser();
    $tag = NewsletterTag::query()->where('slug', 'bailleur')->firstOrFail();
    $screen = taxScreen($admin);
    $screen->call('confirmDelete', 'tag', $tag->id);

    $admin->syncRoles(['moderateur']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $screen->call($action, ...array_map(static fn ($v) => $v === '@id' ? $tag->id : $v, $arguments))->assertForbidden();

    expect(NewsletterTag::query()->count())->toBe(5)->and(AuditLog::query()->where('action', 'like', 'newsletter_taxonomy.%')->count())->toBe(0);
})->with([
    'ouvrir la création' => ['openCreate', ['tag']],
    'ouvrir le renommage' => ['openEdit', ['tag', '@id']],
    'enregistrer' => ['save', []],
    'confirmer la suppression' => ['confirmDelete', ['tag', '@id']],
    'supprimer' => ['delete', []],
]);

it('le cas d’usage lui-même refuse un compte sans permission', function (): void {
    $user = taxUser('moderateur');

    expect(fn () => app(App\Application\Admin\Newsletter\ManageNewsletterTaxonomy::class)->create($user, 'tag', 'Test'))
        ->toThrow(Illuminate\Auth\Access\AuthorizationException::class);
});

it('les nouveaux tags et intérêts sont lus par les filtres, segments et l’import du reste de l’écran', function (): void {
    taxScreen()->call('openCreate', 'tag')->set('label', 'Élues')->call('save');
    taxScreen()->call('openCreate', 'interest')->set('label', 'Santé mentale')->call('save');

    $screen = Livewire::actingAs(taxUser())->test(NewsletterScreen::class, ['tab' => 'abonnes']);

    expect($screen->instance()->tags())->toHaveKey('elues')
        ->and($screen->instance()->interests())->toHaveKey('sante-mentale');
});
