<?php

declare(strict_types=1);

use App\Application\Auth\DeviceTrust;
use App\Application\Auth\Support\SignedResetToken;
use App\Enums\OtpPurpose;
use App\Mail\TemplateMail;
use App\Models\OtpCode;
use App\Models\User;
use Database\Seeders\EmailTemplatesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/*
 * Lot ecrans-web (P2-05, Q8 c) : /mot-de-passe-oublie (réponse identique), écran OTP `reset`, /nouveau-mot-de-passe
 * par code saisi ou par lien magique signé, révocation des sessions et jetons, e-mail de confirmation.
 */

beforeEach(function (): void {
    $this->seed([RolesAndPermissionsSeeder::class, EmailTemplatesSeeder::class, SettingsSeeder::class]);
    Mail::fake();
    config(['mail.default' => 'array']);
    Carbon::setTestNow('2026-10-17 10:00:00');

    $this->user = User::factory()->create(['email' => 'awa@exemple.bj', 'name' => 'Awa Sossou', 'password' => 'Ancien2026!']);
});

afterEach(fn () => Carbon::setTestNow());

function webResetOtp(string $email, string $code = '975310'): OtpCode
{
    $otp = OtpCode::query()->forEmail($email, OtpPurpose::Reset)->valid()->latest('id')->firstOrFail();
    $otp->forceFill(['code_hash' => OtpCode::hash($code)])->save();

    return $otp;
}

/** Lien magique extrait du dernier e-mail `reinitialisation-mot-de-passe` envoyé. */
function webResetLinkFromMail(): string
{
    $link = null;
    Mail::assertSent(TemplateMail::class, function (TemplateMail $mail) use (&$link): bool {
        if ($mail->templateSlug === 'reinitialisation-mot-de-passe' && preg_match('/href="([^"]+nouveau-mot-de-passe[^"]*)"/', $mail->htmlBody, $m) === 1) {
            $link = html_entity_decode($m[1]);
        }

        return true;
    });

    expect($link)->not->toBeNull();

    return (string) $link;
}

it('affiche l’écran et répond de la même façon que le compte existe ou non, avec un seul e-mail portant code et lien', function (): void {
    $this->get('/mot-de-passe-oublie')
        ->assertOk()
        ->assertSee('<title>Mot de passe oublié – Xomiton</title>', false)
        ->assertSee('Mot de passe oublié ?')
        ->assertSee('autocomplete="email"', false)
        ->assertSee('Envoyer le code');

    $known = $this->post('/mot-de-passe-oublie', ['email' => 'AWA@exemple.bj']);
    $unknown = $this->post('/mot-de-passe-oublie', ['email' => 'personne@exemple.bj']);

    foreach ([$known, $unknown] as $response) {
        $response->assertRedirect(route('otp', ['purpose' => 'reset']))
            ->assertSessionHas('status', 'Si un compte correspond à cette adresse, un code vient d’être envoyé. Pense à vérifier tes courriers indésirables.');
    }

    Mail::assertSent(TemplateMail::class, 1);
    $link = webResetLinkFromMail();
    expect($link)->toContain('token=')->toContain('email=awa%40exemple.bj');

    // L'écran OTP `reset` est accessible pour l'adresse inconnue aussi (aucune énumération par l'interface).
    $this->get('/code-de-verification?purpose=reset')->assertOk()->assertSee('p•••@exemple.bj');

    $this->postJson('/mot-de-passe-oublie', ['email' => 'pas-un-mail'])->assertStatus(422)->assertJsonValidationErrors(['email']);
});

it('affiche le même compte à rebours et refuse le renvoi de la même façon que le compte existe ou non (FR-AUTH-04)', function (): void {
    $tooSoon = 'Un code vient de t’être envoyé.';

    foreach (['awa@exemple.bj', 'personne@exemple.bj'] as $email) {
        $this->post('/mot-de-passe-oublie', ['email' => $email]);

        $this->get('/code-de-verification?purpose=reset')->assertOk()->assertSee('data-resend-in="30"', false);

        $this->post('/code-de-verification/renvoyer', ['purpose' => 'reset'])
            ->assertRedirect(route('otp', ['purpose' => 'reset']))
            ->assertSessionHasErrors('code');
        expect(session('errors')->first('code'))->toStartWith($tooSoon);

        Carbon::setTestNow(now()->addSeconds(31));
        $this->get('/code-de-verification?purpose=reset')->assertOk()->assertSee('data-resend-in="0"', false);
        $this->post('/code-de-verification/renvoyer', ['purpose' => 'reset'])
            ->assertRedirect(route('otp', ['purpose' => 'reset']))
            ->assertSessionHas('status', 'Un nouveau code vient de t’être envoyé.');
        $this->get('/code-de-verification?purpose=reset')->assertOk()->assertSee('data-resend-in="30"', false);

        // Un code faux sur une adresse inconnue reçoit exactement le même message.
        $this->post('/code-de-verification', ['purpose' => 'reset', 'code' => '000000'])
            ->assertRedirect(route('otp', ['purpose' => 'reset']))
            ->assertSessionHasErrors(['code' => 'Ce code est incorrect ou a expiré. Vérifie le dernier e-mail reçu ou demande un nouveau code.']);
    }

    // Seul le compte existant a reçu des e-mails : 2 (demande + renvoi).
    Mail::assertSent(TemplateMail::class, 2);
});

it('réinitialise par code saisi : sessions et jetons révoqués, appareil oublié, e-mail de confirmation, toast', function (): void {
    DB::table('sessions')->insert(['id' => 'ancienne-session', 'user_id' => $this->user->getKey(), 'ip_address' => '127.0.0.1', 'user_agent' => 'test', 'payload' => 'x', 'last_activity' => now()->getTimestamp()]);
    $this->user->createToken('mobile');

    $this->post('/mot-de-passe-oublie', ['email' => 'awa@exemple.bj']);
    webResetOtp('awa@exemple.bj');

    $this->get('/code-de-verification')->assertOk()->assertSee('Saisis le code reçu pour choisir un nouveau mot de passe.');

    // Code faux : un essai compté, le code reste valable.
    $this->post('/code-de-verification', ['purpose' => 'reset', 'code' => '000000'])
        ->assertRedirect(route('otp', ['purpose' => 'reset']))
        ->assertSessionHasErrors(['code']);
    expect(OtpCode::query()->forEmail('awa@exemple.bj', OtpPurpose::Reset)->valid()->firstOrFail()->attempts)->toBe(1);

    // Sans code vérifié, l'écran du nouveau mot de passe renvoie vers « Mot de passe oublié ».
    $this->get('/nouveau-mot-de-passe')->assertRedirect(route('mot-de-passe-oublie'));

    $this->post('/code-de-verification', ['purpose' => 'reset', 'code' => '975310'])->assertRedirect(route('reinitialisation'));

    // Le code à 6 chiffres est consommé et échangé contre une preuve signée : il n'est jamais gardé en session.
    $proof = session('auth.pending_reset.proof');
    expect($proof)->toBeString()->not->toBe('975310')
        ->and(SignedResetToken::looksLikeToken((string) $proof))->toBeTrue()
        ->and(OtpCode::query()->forEmail('awa@exemple.bj', OtpPurpose::Reset)->valid()->firstOrFail()->matches('975310'))->toBeFalse();

    $this->get('/nouveau-mot-de-passe')
        ->assertOk()
        ->assertSee('<title>Nouveau mot de passe – Xomiton</title>', false)
        ->assertSee('autocomplete="new-password"', false)
        ->assertSee('data-password-meter="#pwMeter"', false)
        ->assertSee('name="password_confirmation"', false);

    $this->from('/nouveau-mot-de-passe')->post('/nouveau-mot-de-passe', ['password' => 'faible', 'password_confirmation' => 'faible'])
        ->assertRedirect('/nouveau-mot-de-passe')
        ->assertSessionHasErrors(['password']);

    $this->withCookie(DeviceTrust::COOKIE, app(DeviceTrust::class)->makeCookie($this->user)->getValue())
        ->post('/nouveau-mot-de-passe', ['password' => 'Nouveau2026!', 'password_confirmation' => 'Nouveau2026!'])
        ->assertRedirect(route('connexion'))
        ->assertSessionHas('toast', 'Ton mot de passe est modifié. Connecte-toi avec le nouveau.')
        ->assertCookieExpired(DeviceTrust::COOKIE);

    $this->user->refresh();
    expect(Hash::check('Nouveau2026!', $this->user->password))->toBeTrue()
        ->and(DB::table('sessions')->where('user_id', $this->user->getKey())->count())->toBe(0)
        ->and($this->user->tokens()->count())->toBe(0)
        ->and(OtpCode::query()->forEmail('awa@exemple.bj', OtpPurpose::Reset)->valid()->exists())->toBeFalse()
        ->and(session()->has('auth.pending_reset'))->toBeFalse();

    Mail::assertSent(TemplateMail::class, fn (TemplateMail $mail): bool => $mail->templateSlug === 'mot-de-passe-modifie' && $mail->hasTo('awa@exemple.bj'));

    // Le code ne peut pas servir deux fois : une nouvelle demande émet un autre code.
    Carbon::setTestNow('2026-10-17 10:00:31');
    $this->post('/mot-de-passe-oublie', ['email' => 'awa@exemple.bj']);
    $this->post('/code-de-verification', ['purpose' => 'reset', 'code' => '975310'])->assertSessionHasErrors(['code']);
});

it('réinitialise par lien magique signé et répond 403 à un lien altéré ou expiré', function (): void {
    $this->post('/mot-de-passe-oublie', ['email' => 'awa@exemple.bj']);
    $link = webResetLinkFromMail();
    parse_str((string) parse_url($link, PHP_URL_QUERY), $query);

    $this->get($link)->assertOk()->assertSee('name="token"', false);

    $this->get(route('reinitialisation', ['email' => 'awa@exemple.bj', 'token' => 'abc.def']))->assertForbidden();
    $this->get(route('reinitialisation', ['email' => 'autre@exemple.bj', 'token' => $query['token']]))->assertForbidden();

    $this->post('/nouveau-mot-de-passe', ['email' => $query['email'], 'token' => $query['token'], 'password' => 'Nouveau2026!', 'password_confirmation' => 'Nouveau2026!'])
        ->assertRedirect(route('connexion'));

    expect(Hash::check('Nouveau2026!', $this->user->fresh()?->password))->toBeTrue();

    // Lien expiré avec le code : 403.
    Carbon::setTestNow('2026-10-17 10:00:31');
    $this->post('/mot-de-passe-oublie', ['email' => 'awa@exemple.bj']);
    $link = webResetLinkFromMail();
    Carbon::setTestNow('2026-10-17 10:30:00');
    $this->get($link)->assertForbidden();
});
