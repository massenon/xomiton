<?php

declare(strict_types=1);

use App\Application\Legal\LegalTexts;
use App\Application\Seo\SitemapBuilder;
use App\Models\AccountDeletionRequest;
use App\Models\AuditLog;
use App\Models\MailOutbox;
use App\Models\User;
use Database\Seeders\EmailTemplatesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Sleep;

/*
 * Pages légales publiques (politique de confidentialité, conditions, mentions légales, cookies) et page publique de
 * suppression de compte : accessibles sans connexion (exigence Google Play), indexables, liées du pied de page et du plan
 * du site ; marqueurs « À COMPLÉTER PAR LE CLIENT » tant que l'identité n'est pas fournie ; suppression par e-mail avec
 * réponse identique que le compte existe ou non.
 */

beforeEach(function (): void {
    $this->seed([RolesAndPermissionsSeeder::class, EmailTemplatesSeeder::class, SettingsSeeder::class]);
    Sleep::fake();
});

dataset('pagesLegales', [
    'politique de confidentialité' => ['/politique-de-confidentialite', 'legal.privacy', 'Politique de confidentialité'],
    'conditions d’utilisation' => ['/conditions-utilisation', 'legal.terms', 'Conditions d’utilisation'],
    'mentions légales' => ['/mentions-legales', 'legal.notice', 'Mentions légales'],
    'cookies' => ['/cookies', 'legal.cookies', 'Cookies et stockage local'],
    'suppression de compte' => ['/supprimer-mon-compte', 'account-deletion.show', 'Supprimer mon compte et mes données'],
]);

it('sert chaque page légale sans connexion, avec titre, description et indexation', function (string $uri, string $route, string $h1): void {
    expectPublicPage($uri);

    $html = (string) $this->get($uri)->assertOk()->getContent();

    expect(route($route, [], false))->toBe($uri)
        ->and($html)->toContain('<h1 class="fw-bold text-white" id="legal-titre">'.e($h1).'</h1>')
        ->toContain('<meta name="robots" content="index, follow')
        ->toContain('<link rel="canonical"');
})->with('pagesLegales');

it('lie les pages légales depuis le pied de page de l’accueil', function (): void {
    $html = (string) $this->get('/')->assertOk()->getContent();

    foreach (['legal.privacy', 'legal.terms', 'legal.notice', 'legal.cookies', 'account-deletion.show'] as $route) {
        expect($html)->toContain('href="'.route($route).'"');
    }
});

it('les met au plan du site', function (): void {
    config(['app.url' => 'https://xomiton.example']);
    $locs = collect((new SitemapBuilder)->urls())->pluck('loc')->all();

    foreach (['/politique-de-confidentialite', '/conditions-utilisation', '/mentions-legales', '/cookies', '/supprimer-mon-compte'] as $path) {
        expect($locs)->toContain('https://xomiton.example'.$path);
    }
    expect(implode(' ', $locs))->not->toContain('/supprimer-mon-compte/');
});

it('signale en surbrillance chaque champ que le client doit encore fournir, et les affiche une fois renseignés', function (): void {
    $html = (string) $this->get('/mentions-legales')->assertOk()->getContent();

    expect($html)->toContain('<mark class="xm-todo">[À COMPLÉTER PAR LE CLIENT')
        ->and(LegalTexts::missing())->toBe(LegalTexts::FIELDS);

    config(['xomiton.legal.publisher_name' => 'Association Exemple', 'xomiton.legal.host_name' => 'Hébergeur Exemple']);
    $html = (string) $this->get('/mentions-legales')->getContent();

    expect($html)->toContain('Association Exemple')->toContain('Hébergeur Exemple')
        ->and(LegalTexts::missing())->not->toContain('publisher_name');
});

it('décrit les données réelles, les durées de la configuration et l’âge minimal dans la politique', function (): void {
    config(['xomiton.accounts.deletion_grace_days' => 45, 'xomiton.retention.audit_log_days' => 60, 'xomiton.accounts.min_age' => 15]);
    $text = strip_tags((string) $this->get('/politique-de-confidentialite')->getContent());
    $text = html_entity_decode(preg_replace('/\s+/u', ' ', $text), ENT_QUOTES);

    expect($text)->toContain('après 45 jours')->toContain('60 jours')->toContain('15 ans')
        ->toContain('Firebase Cloud Messaging')->toContain('Vonage ou Twilio')->toContain('Gemini');
});

it('annonce Gemini désactivé par défaut et la phrase change si l’équipe l’active', function (): void {
    expect(strip_tags((string) $this->get('/politique-de-confidentialite')->getContent()))
        ->toContain('branché mais désactivé');

    config(['xomiton.assistant.llm_enabled' => true]);
    $text = strip_tags((string) $this->get('/politique-de-confidentialite')->getContent());

    expect($text)->not->toContain('branché mais désactivé')->toContain('peut transmettre le texte de votre question');
});

it('nomme le prestataire SMS configuré', function (): void {
    config(['xomiton.sms.driver' => 'twilio']);

    expect(strip_tags((string) $this->get('/politique-de-confidentialite')->getContent()))
        ->toContain('fait appel au prestataire Twilio');
});

it('envoie un lien de confirmation à un compte existant et ne dit rien de plus à un inconnu', function (): void {
    $user = User::factory()->withProfile()->create(['email' => 'awa@exemple.bj', 'name' => 'Awa Test']);

    $known = $this->post('/supprimer-mon-compte', ['email' => 'Awa@Exemple.bj', 'confirm' => '1']);
    $known->assertRedirect(route('account-deletion.sent'));
    $this->post('/supprimer-mon-compte', ['email' => 'inconnu@exemple.bj', 'confirm' => '1'])
        ->assertRedirect(route('account-deletion.sent'));

    expect(MailOutbox::query()->where('template_slug', 'suppression-compte-verification')->count())->toBe(1)
        ->and(AccountDeletionRequest::query()->where('outcome', 'sent')->count())->toBe(1)
        ->and(AccountDeletionRequest::query()->where('outcome', 'no_account')->count())->toBe(1)
        ->and(AccountDeletionRequest::query()->pluck('email_hash')->implode(' '))->not->toContain('exemple')
        ->and($user->fresh()->trashed())->toBeFalse();

    $this->get('/supprimer-mon-compte/demande-envoyee')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
});

it('supprime le compte seulement après le lien puis le bouton, une seule fois', function (): void {
    $user = User::factory()->withProfile()->create(['email' => 'awa@exemple.bj']);
    $this->post('/supprimer-mon-compte', ['email' => 'awa@exemple.bj', 'confirm' => '1']);

    $outbox = MailOutbox::query()->where('template_slug', 'suppression-compte-verification')->firstOrFail();
    preg_match('~/supprimer-mon-compte/confirmer/([A-Za-z0-9]+)~', (string) $outbox->html, $m);
    $token = $m[1] ?? '';

    expect($token)->not->toBe('');

    // Le simple GET (aperçu d'un anti-virus) ne supprime rien.
    $this->get('/supprimer-mon-compte/confirmer/'.$token)->assertOk()->assertSee('Oui, supprimer mon compte')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    expect($user->fresh()->trashed())->toBeFalse();

    $this->post('/supprimer-mon-compte/confirmer/'.$token)->assertOk()->assertSee('Suppression lancée');
    expect($user->fresh()->trashed())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'account.deletion_requested')->where('user_id', $user->id)->exists())->toBeTrue()
        ->and(MailOutbox::query()->where('template_slug', 'compte-supprime')->exists())->toBeTrue()
        ->and(AccountDeletionRequest::query()->where('outcome', 'done')->exists())->toBeTrue();

    $this->get('/supprimer-mon-compte/confirmer/'.$token)->assertStatus(410);
    $this->post('/supprimer-mon-compte/confirmer/'.$token)->assertStatus(410);
});

it('refuse un lien expiré ou inconnu', function (): void {
    $user = User::factory()->withProfile()->create(['email' => 'awa@exemple.bj']);
    $this->post('/supprimer-mon-compte', ['email' => 'awa@exemple.bj', 'confirm' => '1']);
    preg_match('~/confirmer/([A-Za-z0-9]+)~', (string) MailOutbox::query()->firstOrFail()->html, $m);

    $this->travel(25)->hours();
    $this->post('/supprimer-mon-compte/confirmer/'.$m[1])->assertStatus(410);
    $this->get('/supprimer-mon-compte/confirmer/abcdef')->assertStatus(410);

    expect($user->fresh()->trashed())->toBeFalse();
});

it('n’envoie rien à un compte de l’équipe d’administration', function (): void {
    $admin = User::factory()->withProfile()->create(['email' => 'admin@exemple.bj']);
    $admin->assignRole('admin');

    $this->post('/supprimer-mon-compte', ['email' => 'admin@exemple.bj', 'confirm' => '1'])->assertRedirect(route('account-deletion.sent'));

    expect(MailOutbox::query()->where('template_slug', 'suppression-compte-verification')->count())->toBe(0)
        ->and(AccountDeletionRequest::query()->where('outcome', 'blocked')->count())->toBe(1);
});

it('valide l’adresse et la case de confirmation, et ignore le pot de miel', function (): void {
    User::factory()->withProfile()->create(['email' => 'awa@exemple.bj']);

    $this->from('/supprimer-mon-compte')->post('/supprimer-mon-compte', ['email' => 'pas-une-adresse'])
        ->assertRedirect(route('account-deletion.show').'#demande')->assertSessionHasErrors(['email', 'confirm']);

    $this->post('/supprimer-mon-compte', ['email' => 'awa@exemple.bj', 'confirm' => '1', 'website' => 'robot'])
        ->assertRedirect(route('account-deletion.sent'));

    expect(AccountDeletionRequest::query()->count())->toBe(0);
});

it('limite les demandes par session', function (): void {
    RateLimiter::clear('x');
    for ($i = 0; $i < 3; $i++) {
        $this->post('/supprimer-mon-compte', ['email' => "a{$i}@exemple.bj", 'confirm' => '1'])->assertRedirect(route('account-deletion.sent'));
    }

    $this->post('/supprimer-mon-compte', ['email' => 'a9@exemple.bj', 'confirm' => '1'])
        ->assertRedirect(route('account-deletion.show'))->assertSessionHas(App\Http\Controllers\Web\Legal\AccountDeletionController::FLASH);
});

it('purge les traces anciennes avec l’anonymisation quotidienne', function (): void {
    AccountDeletionRequest::query()->create(['email_hash' => str_repeat('a', 64), 'outcome' => 'no_account']);
    AccountDeletionRequest::query()->update(['created_at' => now()->subDays(100)]);
    AccountDeletionRequest::query()->create(['email_hash' => str_repeat('b', 64), 'outcome' => 'no_account']);

    $this->artisan('accounts:anonymize')->assertSuccessful();

    expect(AccountDeletionRequest::query()->count())->toBe(1);
});

it('exclut les pages à jeton de robots.txt', function (): void {
    $this->app->detectEnvironment(static fn (): string => 'production');
    $this->get('/robots.txt')->assertSee('Disallow: /supprimer-mon-compte/confirmer', false)->assertSee('Disallow: /supprimer-mon-compte/demande-envoyee', false);
});
