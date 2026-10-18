<?php

declare(strict_types=1);

use App\Application\Auth\DeviceTrust;
use App\Enums\OtpPurpose;
use App\Mail\TemplateMail;
use App\Models\AuditLog;
use App\Models\OtpCode;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\EmailTemplatesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Mail;

/*
 * Lot ecrans-web (P2-02, P4-09, Q13 b, Q20 a) : écran /connexion et contrôleur LoginController.
 */

beforeEach(function (): void {
    $this->seed([RolesAndPermissionsSeeder::class, EmailTemplatesSeeder::class, SettingsSeeder::class]);
    Mail::fake();
    config(['mail.default' => 'array']);

    $this->user = User::factory()->create(['pseudo' => 'Awa_S', 'email' => 'awa@exemple.bj', 'password' => 'Xomiton2026!']);
    $this->user->assignRole('membre');
    $this->devices = app(DeviceTrust::class);
});

/** Code OTP `login` en attente pour l'adresse, forcé à une valeur connue. */
function webLoginOtpCode(string $email, string $code = '246810'): OtpCode
{
    $otp = OtpCode::query()->forEmail($email, OtpPurpose::Login)->valid()->latest('id')->firstOrFail();
    $otp->forceFill(['code_hash' => OtpCode::hash($code)])->save();

    return $otp;
}

it('affiche l’écran de connexion fidèle au prototype, dans le gabarit 5/12 · 7/12 (Q20 a), sans comptes de démonstration hors local', function (): void {
    $this->get('/connexion')
        ->assertOk()
        ->assertSee('<title>Se connecter'."\u{00A0}".': retrouver mon fil, mes messages – Xomiton</title>', false)
        ->assertSee('Bon retour')
        ->assertSee('Connecte-toi pour accéder à ton espace.')
        ->assertSee('Ton espace safe d’apprentissage et d’appui holistique.')
        ->assertSee('autocomplete="username"', false)
        ->assertSee('autocomplete="current-password"', false)
        ->assertSee('Rester connecté·e')
        ->assertSee('Mot de passe oublié ?')
        ->assertSee('Signaler (sans compte)')
        ->assertSee('Urgences')
        ->assertSee('Aucune donnée n’est visible depuis l’écran de verrouillage.')
        ->assertSee('class="col-lg-5 d-none d-lg-flex xm-auth-side', false)
        ->assertSee('class="col-lg-7 d-flex align-items-center"', false)
        ->assertSee('style="max-width:440px"', false)
        ->assertSee('class="d-lg-none d-inline-block mb-4"', false)
        ->assertDontSee('Comptes de démonstration')
        ->assertDontSee('xm-sos-fab');
});

it('propose les comptes de démonstration uniquement en environnement local', function (): void {
    $this->app['env'] = 'local';

    $this->get('/connexion')
        ->assertOk()
        ->assertSee('Comptes de démonstration')
        ->assertSee('data-demo="membre@xomiton.bj|Membre123!"', false);
});

it('ouvre la session sans OTP sur un appareil reconnu et redirige vers le fil', function (): void {
    $cookie = $this->devices->makeCookie($this->user)->getValue();

    $response = $this->withCookie(DeviceTrust::COOKIE, $cookie)
        ->post('/connexion', ['identifier' => 'AWA_s', 'password' => 'Xomiton2026!', 'remember' => '1']);

    $response->assertRedirect(route('fil'))->assertCookie(DeviceTrust::COOKIE)->assertSessionHas('toast');
    $this->assertAuthenticatedAs($this->user);
    Mail::assertNothingSent();

    expect(AuditLog::query()->ofAction('auth.login')->forSubject($this->user)->exists())->toBeTrue()
        ->and($this->user->fresh()?->last_login_at)->not->toBeNull();
});

it('exige un code OTP sur un appareil inconnu, puis ouvre la session et mémorise l’appareil (Q13 b)', function (): void {
    $this->post('/connexion', ['identifier' => 'awa@exemple.bj', 'password' => 'Xomiton2026!', 'next' => '/messages'])
        ->assertRedirect(route('otp', ['purpose' => 'login']));

    $this->assertGuest();
    Mail::assertSent(TemplateMail::class, fn (TemplateMail $mail): bool => $mail->templateSlug === 'code-verification' && $mail->hasTo('awa@exemple.bj'));

    $this->get('/code-de-verification')
        ->assertOk()
        ->assertSee('Nous ne reconnaissons pas cet appareil')
        ->assertSee('a•••@exemple.bj')
        ->assertSee('autocomplete="one-time-code"', false);

    webLoginOtpCode('awa@exemple.bj');

    $this->post('/code-de-verification', ['purpose' => 'login', 'code' => ['2', '4', '6', '8', '1', '0']])
        ->assertRedirect('/messages')
        ->assertCookie(DeviceTrust::COOKIE);

    $this->assertAuthenticatedAs($this->user);
    expect(session()->has('auth.pending_login'))->toBeFalse();
});

it('refuse un code de connexion incorrect avec le message indifférencié et sans ouvrir la session', function (): void {
    $this->post('/connexion', ['identifier' => 'awa@exemple.bj', 'password' => 'Xomiton2026!']);
    webLoginOtpCode('awa@exemple.bj');

    $this->post('/code-de-verification', ['purpose' => 'login', 'code' => '000000'])
        ->assertRedirect(route('otp', ['purpose' => 'login']))
        ->assertSessionHasErrors(['code' => 'Ce code est incorrect ou a expiré. Vérifie le dernier e-mail reçu ou demande un nouveau code.']);

    $this->assertGuest();
});

it('refuse le code de connexion d’un compte suspendu entre le mot de passe et le code', function (): void {
    $this->post('/connexion', ['identifier' => 'awa@exemple.bj', 'password' => 'Xomiton2026!']);
    webLoginOtpCode('awa@exemple.bj');
    $this->user->forceFill(['status' => 'suspendu', 'suspended_at' => now()])->save();

    $this->post('/code-de-verification', ['purpose' => 'login', 'code' => '246810'])
        ->assertRedirect(route('connexion'))
        ->assertSessionHasErrors(['identifier' => 'Ta demande de connexion a expiré. Reconnecte-toi.']);

    $this->assertGuest();
    expect(session()->has('auth.pending_login'))->toBeFalse();
});

it('renvoie le même message pour un identifiant inconnu et pour un mauvais mot de passe', function (): void {
    $expected = 'Identifiants incorrects. Vérifie ton e-mail et ton mot de passe.';

    $this->from('/connexion')->post('/connexion', ['identifier' => 'inconnue@exemple.bj', 'password' => 'Xomiton2026!'])
        ->assertRedirect('/connexion')
        ->assertSessionHasErrors(['identifier' => $expected]);

    $this->from('/connexion')->post('/connexion', ['identifier' => 'awa@exemple.bj', 'password' => 'mauvais'])
        ->assertRedirect('/connexion')
        ->assertSessionHasErrors(['identifier' => $expected])
        ->assertSessionHasInput('identifier', 'awa@exemple.bj');

    $this->assertGuest();
    expect(AuditLog::query()->ofAction('auth.failed')->count())->toBe(2);
});

it('valide le formulaire (422 en JSON) et limite à 5 tentatives par minute (429)', function (): void {
    $this->postJson('/connexion', [])->assertStatus(422)->assertJsonValidationErrors(['identifier', 'password']);

    $payload = ['identifier' => 'awa@exemple.bj', 'password' => 'mauvais'];

    foreach (range(1, 5) as $i) {
        $this->post('/connexion', $payload)->assertStatus(302);
    }

    $this->post('/connexion', $payload)->assertStatus(429)->assertHeader('Retry-After');
});

it('laisse entrer un·e pair en attente de validation avec le bandeau', function (): void {
    $this->user->forceFill(['status' => 'en_attente'])->save();

    $this->withCookie(DeviceTrust::COOKIE, $this->devices->makeCookie($this->user)->getValue())
        ->post('/connexion', ['identifier' => 'awa@exemple.bj', 'password' => 'Xomiton2026!'])
        ->assertRedirect(route('fil'))
        ->assertSessionHas('status', 'Ton compte est en attente de validation par l’équipe Xomiton.')
        ->assertSessionHas('status_type', 'warning');

    $this->assertAuthenticatedAs($this->user);
});

it('signale un compte suspendu et la maintenance seulement après vérification du mot de passe', function (): void {
    $this->user->forceFill(['status' => 'suspendu', 'suspended_at' => now()])->save();

    $this->from('/connexion')->post('/connexion', ['identifier' => 'awa@exemple.bj', 'password' => 'mauvais'])
        ->assertSessionHasErrors(['identifier' => 'Identifiants incorrects. Vérifie ton e-mail et ton mot de passe.']);

    $this->from('/connexion')->post('/connexion', ['identifier' => 'awa@exemple.bj', 'password' => 'Xomiton2026!'])
        ->assertSessionHasErrors(['identifier' => 'Ce compte est suspendu. Contacte-nous : contact@xomiton.bj']);

    $this->user->forceFill(['status' => 'actif', 'suspended_at' => null])->save();
    Settings::set('maintenance', true);

    $this->from('/connexion')->post('/connexion', ['identifier' => 'awa@exemple.bj', 'password' => 'Xomiton2026!'])
        ->assertSessionHasErrors(['identifier' => 'La plateforme est en maintenance. Réessaie dans quelques instants.']);

    $this->assertGuest();
});

it('impose l’OTP à l’administration même sur un appareil reconnu, puis redirige vers /admin', function (): void {
    $admin = User::factory()->create(['email' => 'admin@exemple.bj', 'password' => 'Admin2026!!']);
    $admin->assignRole('admin');

    $this->withCookie(DeviceTrust::COOKIE, $this->devices->makeCookie($admin)->getValue())
        ->post('/connexion', ['identifier' => 'admin@exemple.bj', 'password' => 'Admin2026!!', 'next' => 'https://evil.example/'])
        ->assertRedirect(route('otp', ['purpose' => 'login']));

    $this->assertGuest();
    webLoginOtpCode('admin@exemple.bj');

    $this->post('/code-de-verification', ['purpose' => 'login', 'code' => '246810'])
        ->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs($admin);
});

it('ignore une destination `next` externe et redirige une personne déjà connectée', function (): void {
    $this->withCookie(DeviceTrust::COOKIE, $this->devices->makeCookie($this->user)->getValue())
        ->post('/connexion', ['identifier' => 'awa@exemple.bj', 'password' => 'Xomiton2026!', 'next' => '//evil.example/'])
        ->assertRedirect(route('fil'));

    $this->actingAs($this->user)->get('/connexion')->assertRedirect(route('fil'));
});

it('renvoie vers la connexion quand aucun code n’est attendu', function (): void {
    $this->get('/code-de-verification')->assertRedirect(route('connexion'));
    $this->get('/code-de-verification?purpose=login')->assertRedirect(route('connexion'));
});
