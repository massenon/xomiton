<?php

declare(strict_types=1);

use App\Enums\OtpPurpose;
use App\Mail\TemplateMail;
use App\Models\OtpCode;
use App\Models\User;
use Database\Seeders\EmailTemplatesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/*
 * Lot ecrans-web (P2-04, LOGIN §5) : /code-de-verification pour l'usage `signup` (vérification différée, Q12 c),
 * expiration, essais épuisés, renvoi trop tôt, limiteur 'otp'.
 */

beforeEach(function (): void {
    $this->seed([RolesAndPermissionsSeeder::class, EmailTemplatesSeeder::class, SettingsSeeder::class]);
    Mail::fake();
    config(['mail.default' => 'array']);
    Carbon::setTestNow('2026-10-17 10:00:00');

    $this->user = User::factory()->unverified()->create(['email' => 'awa@exemple.bj']);
    $this->user->assignRole('membre');
    $this->otp = app(App\Application\Auth\OtpService::class);
});

afterEach(fn () => Carbon::setTestNow());

function webSignupOtp(string $email, string $code = '135790'): OtpCode
{
    $otp = OtpCode::query()->forEmail($email, OtpPurpose::Signup)->valid()->latest('id')->firstOrFail();
    $otp->forceFill(['code_hash' => OtpCode::hash($code)])->save();

    return $otp;
}

it('affiche l’écran unique pour la vérification différée de l’e-mail (connecté·e) : 6 cases numériques, collage, renvoi', function (): void {
    $this->otp->issue('awa@exemple.bj', OtpPurpose::Signup);

    $response = $this->actingAs($this->user)->get('/code-de-verification')
        ->assertOk()
        ->assertSee('<title>Code de vérification – Xomiton</title>', false)
        ->assertSee('Confirme ton adresse e-mail pour débloquer toutes les fonctionnalités.')
        ->assertSee('valable 10 minutes')
        ->assertSee('autocomplete="one-time-code"', false)
        ->assertSee('data-xm-otp', false)
        ->assertSee('Plus tard')
        ->assertSee('data-resend-in="30"', false)
        ->assertSee('Renvoyer le code dans 30 s');

    expect(substr_count((string) $response->getContent(), 'inputmode="numeric"'))->toBe(6)
        ->and(substr_count((string) $response->getContent(), 'name="code[]"'))->toBe(6);
});

it('confirme l’e-mail avec le bon code et refuse un code faux, expiré ou épuisé avec le même message', function (): void {
    $invalid = 'Ce code est incorrect ou a expiré. Vérifie le dernier e-mail reçu ou demande un nouveau code.';

    $this->otp->issue('awa@exemple.bj', OtpPurpose::Signup);
    webSignupOtp('awa@exemple.bj');

    // Code faux : un essai compté.
    $this->actingAs($this->user)->post('/code-de-verification', ['purpose' => 'signup', 'code' => ['0', '0', '0', '0', '0', '0']])
        ->assertRedirect(route('otp', ['purpose' => 'signup']))
        ->assertSessionHasErrors(['code' => $invalid]);
    expect($this->user->fresh()?->email_verified_at)->toBeNull()
        ->and(OtpCode::query()->forEmail('awa@exemple.bj', OtpPurpose::Signup)->valid()->firstOrFail()->attempts)->toBe(1);

    // Code expiré.
    Carbon::setTestNow('2026-10-17 10:11:00');
    $this->actingAs($this->user)->post('/code-de-verification', ['purpose' => 'signup', 'code' => '135790'])
        ->assertSessionHasErrors(['code' => $invalid]);
    expect($this->user->fresh()?->email_verified_at)->toBeNull();

    // Nouveau code : 4 essais faux puis le 5e épuise le code, même avec la bonne valeur ensuite.
    $this->otp->issue('awa@exemple.bj', OtpPurpose::Signup);
    webSignupOtp('awa@exemple.bj');
    foreach (range(1, 5) as $i) {
        $this->actingAs($this->user)->post('/code-de-verification', ['purpose' => 'signup', 'code' => '999999'])->assertSessionHasErrors(['code' => $invalid]);
    }
    $this->actingAs($this->user)->post('/code-de-verification', ['purpose' => 'signup', 'code' => '135790'])->assertSessionHasErrors(['code' => $invalid]);
    expect(OtpCode::query()->forEmail('awa@exemple.bj', OtpPurpose::Signup)->valid()->exists())->toBeFalse();

    // Code correct sur un code frais (renvoi après 30 s) : e-mail confirmé, redirection vers la phase profil (profil vide).
    Carbon::setTestNow('2026-10-17 10:12:00');
    $this->otp->issue('awa@exemple.bj', OtpPurpose::Signup);
    webSignupOtp('awa@exemple.bj');
    $this->actingAs($this->user)->post('/code-de-verification', ['purpose' => 'signup', 'code' => '1 3 5 7 9 0'])
        ->assertRedirect(route('inscription.profil'))
        ->assertSessionHas('toast', 'Ton e-mail est confirmé. Merci !');
    expect($this->user->fresh()?->email_verified_at)->not->toBeNull();

    // Déjà confirmé : l'écran renvoie vers le fil.
    $this->actingAs($this->user)->get('/code-de-verification')->assertRedirect(route('fil'));
});

it('valide la saisie : 6 chiffres et un usage connu', function (): void {
    $this->otp->issue('awa@exemple.bj', OtpPurpose::Signup);

    $this->actingAs($this->user)->postJson('/code-de-verification', ['purpose' => 'signup', 'code' => '12ab'])
        ->assertStatus(422)->assertJsonValidationErrors(['code']);
    $this->actingAs($this->user)->postJson('/code-de-verification', ['purpose' => 'inconnu', 'code' => '123456'])
        ->assertStatus(422)->assertJsonValidationErrors(['purpose']);
});

it('refuse le renvoi avant 30 s, l’accepte ensuite (nouveau code), puis plafonne par le service et le limiteur (429)', function (): void {
    // Limiteur HTTP 'otp' relevé à 6 requêtes/h pour observer d'abord le plafond du service (5 envois/h), puis le 429.
    config()->set('xomiton.rate_limits.otp_per_hour', 6);
    $this->otp->issue('awa@exemple.bj', OtpPurpose::Signup);
    $first = OtpCode::query()->forEmail('awa@exemple.bj', OtpPurpose::Signup)->valid()->firstOrFail();

    $this->actingAs($this->user)->post('/code-de-verification/renvoyer', ['purpose' => 'signup'])
        ->assertRedirect(route('otp', ['purpose' => 'signup']))
        ->assertSessionHasErrors('code');
    expect(session('errors')->first('code'))->toStartWith('Un code vient de t’être envoyé.');

    Carbon::setTestNow('2026-10-17 10:00:31');
    $this->actingAs($this->user)->post('/code-de-verification/renvoyer', ['purpose' => 'signup'])
        ->assertRedirect(route('otp', ['purpose' => 'signup']))
        ->assertSessionHas('status', 'Un nouveau code vient de t’être envoyé.');

    expect($first->fresh()?->consumed_at)->not->toBeNull()
        ->and(OtpCode::query()->forEmail('awa@exemple.bj', OtpPurpose::Signup)->valid()->count())->toBe(1);
    Mail::assertSent(TemplateMail::class, 2);

    // 3 renvois de plus (5 envois dans l'heure : plafond du service) puis le 5e renvoi est refusé par le service…
    foreach (range(1, 3) as $i) {
        Carbon::setTestNow(now()->addSeconds(31));
        $this->actingAs($this->user)->post('/code-de-verification/renvoyer', ['purpose' => 'signup'])->assertSessionHas('status');
    }
    Carbon::setTestNow(now()->addSeconds(31));
    $this->actingAs($this->user)->post('/code-de-verification/renvoyer', ['purpose' => 'signup'])->assertSessionHasErrors('code');
    expect(session('errors')->first('code'))->toStartWith('Trop de codes demandés');

    // … et la requête suivante est bloquée par le limiteur HTTP 'otp' (429).
    Carbon::setTestNow(now()->addSeconds(31));
    $this->actingAs($this->user)->post('/code-de-verification/renvoyer', ['purpose' => 'signup'])->assertStatus(429);
});

it('limite la vérification à 20 envois par minute et par IP (défense en profondeur, 429)', function (): void {
    $this->otp->issue('awa@exemple.bj', OtpPurpose::Signup);

    foreach (range(1, 20) as $i) {
        $this->post('/code-de-verification', ['purpose' => 'reset', 'code' => '000000'])->assertStatus(302);
    }

    $this->post('/code-de-verification', ['purpose' => 'reset', 'code' => '000000'])->assertStatus(429);
});

it('renvoie vers le fil quand l’e-mail est déjà confirmé et vers la connexion sans code en attente', function (): void {
    $this->get('/code-de-verification?purpose=reset')->assertRedirect(route('connexion'));
    $this->post('/code-de-verification/renvoyer', ['purpose' => 'reset'])->assertRedirect(route('connexion'));

    $verified = User::factory()->create();
    $this->actingAs($verified)->get('/code-de-verification?purpose=signup')->assertRedirect(route('fil'));
    $this->actingAs($verified)->post('/code-de-verification/renvoyer', ['purpose' => 'signup'])->assertRedirect(route('fil'));
});
