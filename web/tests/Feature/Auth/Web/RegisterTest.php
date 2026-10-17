<?php

declare(strict_types=1);

use App\Enums\ConsentKind;
use App\Enums\MemberType;
use App\Enums\OtpPurpose;
use App\Enums\UserStatus;
use App\Mail\TemplateMail;
use App\Models\Commune;
use App\Models\OtpCode;
use App\Models\User;
use App\Models\Zone;
use App\Support\Settings;
use Database\Seeders\EmailTemplatesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Mail;

/*
 * Lot ecrans-web (P2-03, P4-09, D4, Q12 c, Q30 a) : /inscription en 4 phases, une seule obligatoire.
 */

beforeEach(function (): void {
    $this->seed([RolesAndPermissionsSeeder::class, EmailTemplatesSeeder::class, SettingsSeeder::class]);
    Mail::fake();
    config(['mail.default' => 'array']);
});

/** @return array<string, mixed> */
function webRegisterPayload(array $overrides = []): array
{
    return array_merge([
        'pseudo' => 'Awa_S',
        'email' => 'Awa@Exemple.bj',
        'password' => 'Xomiton2026!',
        'cgu' => '1',
        'privacy' => '1',
    ], $overrides);
}

it('affiche la phase 1 avec le stepper (étapes 2 à 4 facultatives), la jauge et des cases jamais pré-cochées', function (): void {
    $response = $this->get('/inscription')
        ->assertOk()
        ->assertSee('<title>Créer un compte – Xomiton</title>', false)
        ->assertSee('Étape 1 sur 4')
        ->assertSee('aria-current="step"', false)
        ->assertSee('Profil (facultative)')
        ->assertSee('Sécurité (facultative)')
        ->assertSee('Consentements (facultative)')
        ->assertSee('autocomplete="new-password"', false)
        ->assertSee('data-password-meter="#pwMeter"', false)
        ->assertSee('class="xm-pw-meter', false)
        ->assertSee('Au moins 10 caractères')
        ->assertSee('style="max-width:520px"', false)
        ->assertSee('class="col-lg-5 d-none d-lg-flex xm-auth-side', false)
        ->assertSee('Rejoins une communauté bienveillante.')
        ->assertSee('(nouvelle fenêtre)')
        ->assertSee('Aucune donnée n’est visible depuis l’écran de verrouillage.');

    expect($response->getContent())->not->toContain('name="cgu" value="1" checked')
        ->and($response->getContent())->not->toContain('name="privacy" value="1" checked');
});

it('crée le compte et ouvre la session dès la phase 1 : rôle membre, profil, réglages, consentements, code puis bienvenue', function (): void {
    $this->post('/inscription', webRegisterPayload())
        ->assertRedirect(route('inscription.profil'))
        ->assertSessionHas('toast');

    $user = User::query()->where('email', 'awa@exemple.bj')->firstOrFail();
    $this->assertAuthenticatedAs($user);

    expect($user->pseudo)->toBe('Awa_S')
        ->and($user->status)->toBe(UserStatus::Active)
        ->and($user->email_verified_at)->toBeNull()
        ->and($user->hasRole('membre'))->toBeTrue()
        ->and($user->profile?->member_type)->toBe(MemberType::Member)
        ->and($user->settings)->not->toBeNull()
        ->and($user->consents()->ofKind(ConsentKind::Terms)->granted()->exists())->toBeTrue()
        ->and($user->consents()->ofKind(ConsentKind::Privacy)->granted()->exists())->toBeTrue()
        ->and($user->consents()->count())->toBe(2)
        ->and(OtpCode::query()->forEmail('awa@exemple.bj', OtpPurpose::Signup)->valid()->count())->toBe(1);

    $slugs = [];
    Mail::assertSent(TemplateMail::class, function (TemplateMail $mail) use (&$slugs): bool {
        $slugs[] = $mail->templateSlug;

        return true;
    });
    expect($slugs)->toBe(['code-verification', 'bienvenue']);

    // Phase 2 : bandeau « Confirme ton e-mail » + bouton « Renvoyer le code » tant que l'adresse n'est pas confirmée.
    $this->get('/inscription/profil')
        ->assertOk()
        ->assertSee('Étape 2 sur 4')
        ->assertSee('Confirme ton e-mail')
        ->assertSee('Renvoyer le code')
        ->assertSee('Passer');
});

it('refuse chaque règle : pseudo pris (casse ignorée), e-mail pris, mot de passe faible, consentements manquants', function (): void {
    User::factory()->create(['pseudo' => 'awa_s', 'email' => 'deja@exemple.bj']);

    $this->from('/inscription')->post('/inscription', webRegisterPayload(['email' => 'DEJA@exemple.bj', 'password' => 'faible', 'cgu' => '', 'privacy' => '0']))
        ->assertRedirect('/inscription')
        ->assertSessionHasErrors(['pseudo', 'email', 'password', 'cgu', 'privacy'])
        ->assertSessionHasErrors(['pseudo' => 'Ce pseudonyme est déjà pris. Choisis-en un autre.'])
        ->assertSessionHasErrors(['email' => 'Un compte existe déjà avec cette adresse e-mail. Connecte-toi ou utilise « Mot de passe oublié ».']);

    foreach (['Court1Ab', 'sansmajuscule2026', 'SANSMINUSCULE2026', 'SansChiffreIci'] as $weak) {
        $this->postJson('/inscription', webRegisterPayload(['pseudo' => 'Nouvelle_'.strlen($weak), 'password' => $weak]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password'])
            ->assertJsonMissingValidationErrors(['pseudo', 'email']);
    }

    $this->assertGuest();
    expect(User::query()->count())->toBe(1);
});

it('remplace le formulaire par le message configuré, la newsletter et la connexion quand les inscriptions sont fermées', function (): void {
    Settings::set('registrations_open', false);
    Settings::set('reg_closed_msg', 'Fermé pour les fêtes, retour le 3 janvier.');
    Settings::set('reg_reopen_at', '2027-01-03');

    $this->get('/inscription')
        ->assertOk()
        ->assertSee('Les inscriptions sont momentanément fermées')
        ->assertSee('Fermé pour les fêtes, retour le 3 janvier.')
        ->assertSee('Réouverture prévue le 3 janvier 2027.')
        ->assertSee('action="'.route('newsletter.subscribe').'"', false)
        ->assertSee('M’inscrire à la newsletter')
        ->assertSee('J’ai déjà un compte : me connecter')
        ->assertDontSee('name="pseudo"', false);

    $this->post('/inscription', webRegisterPayload())
        ->assertRedirect(route('inscription'))
        ->assertSessionHasErrors(['pseudo' => 'Fermé pour les fêtes, retour le 3 janvier.']);

    $this->assertGuest();
    expect(User::query()->count())->toBe(0);
});

it('enregistre les phases 2 à 4 d’un·e pair : en attente (RG-03), rôle pair, contact de confiance, consentements', function (): void {
    $zone = Zone::factory()->create(['is_active' => true]);
    $commune = Commune::factory()->create(['zone_id' => $zone->getKey(), 'is_active' => true]);
    $otherCommune = Commune::factory()->create(['is_active' => true]);

    $this->post('/inscription', webRegisterPayload());
    $user = User::query()->where('email', 'awa@exemple.bj')->firstOrFail();

    // Commune hors du département choisi : refusée, données conservées.
    $this->from('/inscription/profil')->post('/inscription/profil', ['zone_id' => $zone->getKey(), 'commune_id' => $otherCommune->getKey()])
        ->assertRedirect('/inscription/profil')
        ->assertSessionHasErrors(['commune_id']);

    $this->post('/inscription/profil', ['pronouns' => 'elle', 'zone_id' => $zone->getKey(), 'commune_id' => $commune->getKey(), 'member_type' => 'pair'])
        ->assertRedirect(route('inscription.securite'));

    $user->refresh()->load('profile');
    expect($user->status)->toBe(UserStatus::Pending)
        ->and($user->hasRole('pair'))->toBeTrue()
        ->and($user->hasRole('membre'))->toBeFalse()
        ->and($user->zone_id)->toBe($zone->getKey())
        ->and($user->commune_id)->toBe($commune->getKey())
        ->and($user->profile?->pronouns)->toBe('elle')
        ->and($user->profile?->member_type)->toBe(MemberType::Peer);

    $this->assertAuthenticatedAs($user); // la session reste ouverte, l'accès à la collecte attend la validation

    $this->get('/inscription/securite')->assertOk()->assertSee('Étape 3 sur 4');

    $this->from('/inscription/securite')->post('/inscription/securite', ['trusted_contact_phone' => '12345'])
        ->assertSessionHasErrors(['trusted_contact_phone']);

    $this->post('/inscription/securite', ['trusted_contact_name' => 'Maman', 'trusted_contact_phone' => '0197388847', 'discreet_mode' => '1'])
        ->assertRedirect(route('inscription.consentements'));

    $user->refresh()->load('profile');
    expect($user->profile?->trusted_contact_phone)->toBe('+229 01 97 38 88 47')
        ->and($user->profile?->trusted_contact_name)->toBe('Maman')
        ->and($user->profile?->discreet_mode)->toBeTrue();

    $this->get('/inscription/consentements')->assertOk()->assertSee('Étape 4 sur 4')->assertSee('Terminer');

    $this->post('/inscription/consentements', ['sensitive_data' => '1'])
        ->assertRedirect(route('fil'))
        ->assertSessionHas('toast', 'Bienvenue sur Xomiton ! Tu peux compléter ton profil quand tu veux.');

    expect($user->consents()->count())->toBe(4)
        ->and($user->consents()->ofKind(ConsentKind::SensitiveData)->granted()->exists())->toBeTrue()
        ->and($user->consents()->ofKind(ConsentKind::Newsletter)->where('granted', false)->exists())->toBeTrue();
});

it('permet de passer chaque phase facultative sans rien enregistrer', function (): void {
    $this->post('/inscription', webRegisterPayload());
    $user = User::query()->where('email', 'awa@exemple.bj')->firstOrFail();

    $this->post('/inscription/profile/passer')->assertRedirect(route('inscription.securite'));
    $this->post('/inscription/security/passer')->assertRedirect(route('inscription.consentements'));
    $this->post('/inscription/consents/passer')->assertRedirect(route('fil'));
    $this->post('/inscription/account/passer')->assertNotFound();

    expect($user->fresh()?->status)->toBe(UserStatus::Active)
        ->and($user->consents()->count())->toBe(2)
        ->and($user->fresh()?->hasRole('membre'))->toBeTrue();
});

it('réserve les phases 2 à 4 aux personnes connectées et la phase 1 aux visiteur·ses', function (): void {
    $this->get('/inscription/profil')->assertRedirect(route('connexion'));
    $this->post('/inscription/securite', [])->assertRedirect(route('connexion'));
    $this->postJson('/inscription/consentements', [])->assertUnauthorized();

    $this->actingAs(User::factory()->create())->get('/inscription')->assertRedirect(route('fil'));
});
