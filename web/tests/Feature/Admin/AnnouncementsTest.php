<?php

declare(strict_types=1);

use App\Application\Platform\Announcements\Announcements;
use App\Livewire\Admin\Announcements\Index as AnnouncementsScreen;
use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;
use Tests\Support\FeedFixtures as F;

/*
 * P18-13 (Q97) : annonce globale — écran `/admin/annonces` (`announcements.manage`), période, public (membres / pair·es),
 * lien interne seulement, bannière de l'espace membre (prop Inertia `announcement`) et de l'application
 * (`GET announcements/active`), fermeture mémorisée par compte, **jamais** sur le site public ni pour un·e visiteur·se,
 * distincte du bandeau de maintenance.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    config(['xomiton.display_timezone' => 'Africa/Porto-Novo']);
    $this->travelTo(now()->setTimezone('UTC')->setDate(2026, 11, 12)->setTime(12, 0));
    $this->admin = User::factory()->create();
    $this->admin->syncRoles(['admin']);
    $this->member = F::member();
});

function p18Announcement(array $attributes = []): Announcement
{
    Announcements::flush();

    return Announcement::query()->create($attributes + [
        'title' => 'Atelier ce samedi',
        'message' => 'Rejoins-nous pour un atelier sur la sécurité numérique.',
        'link_path' => '/evenements',
        'level' => 'info',
        'audience' => 'membres',
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addDay(),
    ]);
}

it('crée une annonce depuis l’admin (heure du Bénin), refuse un lien externe et une fin avant le début', function (): void {
    $this->actingAs($this->admin);

    Livewire::test(AnnouncementsScreen::class)
        ->call('create')
        ->set('form.title', 'Nouvelle charte')
        ->set('form.message', 'La charte de la communauté évolue, lis-la.')
        ->set('form.link_path', 'https://exemple.org')
        ->set('form.starts_at', '2026-11-12T14:00')
        ->set('form.ends_at', '2026-11-12T13:00')
        ->call('save')
        ->assertHasErrors(['form.link_path', 'form.ends_at']);

    Livewire::test(AnnouncementsScreen::class)
        ->call('create')
        ->set('form.title', 'Nouvelle charte')
        ->set('form.message', 'La charte de la communauté évolue, lis-la.')
        ->set('form.link_path', '/aide')
        ->set('form.level', 'important')
        ->set('form.starts_at', '2026-11-12T14:00')
        ->call('save')
        ->assertHasNoErrors();

    $announcement = Announcement::query()->sole();
    expect($announcement->getRawOriginal('starts_at'))->toBe('2026-11-12 13:00:00')
        ->and($announcement->level()->value)->toBe('important')
        ->and($announcement->getRawOriginal('ends_at'))->toBeNull();
    $log = AuditLog::query()->where('action', 'announcements.created')->sole();
    expect((string) $log->detail)->not->toContain('charte');
});

it('réserve l’écran à announcements.manage et verrouille l’annonce ouverte', function (): void {
    $moderator = User::factory()->create();
    $moderator->syncRoles(['moderateur']);
    $this->actingAs($moderator)->get('/admin/annonces')->assertForbidden();

    $this->actingAs($this->admin)->get('/admin/annonces')->assertOk();
    expect(fn () => Livewire::test(AnnouncementsScreen::class)->set('announcementId', 3))->toThrow(Exception::class);
});

it('sert l’annonce en cours dans l’API et la prop Inertia de l’espace membre, pas avant le début ni après la fin', function (): void {
    p18Announcement(['title' => 'Programmée', 'starts_at' => now()->addHour()]);
    p18Announcement(['title' => 'Finie', 'starts_at' => now()->subDays(2), 'ends_at' => now()->subDay()]);
    F::as($this->member)->getJson('/api/v1/announcements/active')->assertOk()->assertJsonPath('data', null);

    p18Announcement();
    F::as($this->member)->getJson('/api/v1/announcements/active')->assertOk()
        ->assertJsonPath('data.title', 'Atelier ce samedi')
        ->assertJsonPath('data.link', '/evenements')
        ->assertJsonPath('data.level', 'info');

    $this->actingAs($this->member)->get('/fil')->assertOk()
        ->assertInertia(fn ($page) => $page->where('announcement.title', 'Atelier ce samedi'));
});

it('n’affiche jamais l’annonce sur le site public ni pour un·e visiteur·se', function (): void {
    p18Announcement();

    $this->getJson('/api/v1/announcements/active')->assertUnauthorized();
    $this->get('/')->assertOk()->assertDontSee('Atelier ce samedi');
    $this->get('/ressources')->assertOk()->assertDontSee('Atelier ce samedi');
    $this->actingAs($this->member)->get('/ressources')->assertOk()->assertDontSee('Atelier ce samedi');
});

it('cible les pair·es seulement quand le public est « pairs »', function (): void {
    p18Announcement(['title' => 'Réunion des pair·es', 'audience' => 'pairs']);
    $peer = F::member('pair');

    F::as($this->member)->getJson('/api/v1/announcements/active')->assertOk()->assertJsonPath('data', null);
    F::as($peer)->getJson('/api/v1/announcements/active')->assertOk()->assertJsonPath('data.title', 'Réunion des pair·es');
});

it('mémorise la fermeture par compte et par annonce (web et application partagent l’état)', function (): void {
    $announcement = p18Announcement();
    $other = F::member();

    F::as($this->member)->postJson('/api/v1/announcements/'.$announcement->id.'/dismiss')->assertNoContent();
    F::as($this->member)->postJson('/api/v1/announcements/'.$announcement->id.'/dismiss')->assertNoContent();
    F::as($this->member)->getJson('/api/v1/announcements/active')->assertJsonPath('data', null);
    $this->actingAs($this->member)->get('/fil')->assertInertia(fn ($page) => $page->where('announcement', null));

    F::as($other)->getJson('/api/v1/announcements/active')->assertJsonPath('data.id', $announcement->id);

    $next = p18Announcement(['title' => 'Une autre annonce']);
    F::as($this->member)->getJson('/api/v1/announcements/active')->assertJsonPath('data.id', $next->id);
});

it('termine une annonce depuis l’admin : elle disparaît aussitôt', function (): void {
    $announcement = p18Announcement();
    $this->actingAs($this->admin);

    Livewire::test(AnnouncementsScreen::class)->call('end', $announcement->id);

    F::as($this->member)->getJson('/api/v1/announcements/active')->assertJsonPath('data', null);
    F::as($this->member)->postJson('/api/v1/announcements/'.$announcement->id.'/dismiss')->assertNotFound();
    expect(AuditLog::query()->where('action', 'announcements.ended')->count())->toBe(1);
});
