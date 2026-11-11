<?php

declare(strict_types=1);

use App\Enums\ConsentKind;
use App\Enums\MemberType;
use App\Enums\OtpPurpose;
use App\Enums\UserStatus;
use App\Models\Commune;
use App\Models\OtpCode;
use App\Models\User;
use App\Models\Zone;
use App\Notifications\PeerPending;
use App\Support\Settings;
use Database\Seeders\EmailTemplatesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\Support\FeedFixtures as F;
use Tests\Support\RegistrationFixtures as R;

/*
 * /inscription : ancien formulaire du client, 3 étapes obligatoires, un seul envoi à la fin, compte en attente de validation
 * (décision du 11/11/2026). Rendu entièrement côté serveur : sans JavaScript les trois étapes sont visibles.
 */

beforeEach(function (): void {
    $this->seed([RolesAndPermissionsSeeder::class, EmailTemplatesSeeder::class, SettingsSeeder::class]);
    Mail::fake();
    config(['mail.default' => 'array']);
});

/** Plusieurs envois refusés d'affilée : le limiteur `login` (5 par minute) est vidé avant chaque envoi. */
function postRegister(mixed $test, array $payload, string $from = '/inscription'): Illuminate\Testing\TestResponse
{
    Illuminate\Support\Facades\Cache::flush();

    return $test->from($from)->post('/inscription', $payload);
}

/** @return array<string, mixed> */
function webRegisterPayload(array $overrides = []): array
{
    return R::payload($overrides);
}

it('affiche le formulaire complet côté serveur : trois étapes, stepper « Étape 1 sur 3 », champs de chaque étape, aucune case pré-cochée', function (): void {
    $response = $this->get('/inscription')
        ->assertOk()
        ->assertSee('<title>Créer un compte'."\u{00A0}".': rejoindre la communauté – Xomiton</title>', false)
        ->assertSee('Étape 1 sur 3')
        ->assertSee('aria-current="step"', false)
        ->assertSee('Informations de base')
        ->assertSee('Profil et identité')
        ->assertSee('Détails et sécurité')
        ->assertDontSee('Étape 1 sur 4')
        ->assertDontSee('(facultative)')
        ->assertSee('id="etape-1-titre"', false)
        ->assertSee('id="etape-2-titre"', false)
        ->assertSee('id="etape-3-titre"', false)
        ->assertSee('Précédent')
        ->assertSee('Suivant')
        ->assertSee('Créer mon compte')
        ->assertSee('x-data="xmRegister(', false)
        ->assertSee('Au moins 18 ans, en chiffres.')
        ->assertSee('Rejoins une communauté bienveillante.')
        ->assertSee('(nouvelle fenêtre)')
        ->assertDontSee('écran de verrouillage');

    foreach (['first_name', 'last_name', 'pseudo', 'email', 'age', 'pronouns', 'country_code', 'city', 'phone', 'zone_id', 'commune_id', 'member_type', 'gender_identity', 'gender_identity_other', 'orientation', 'orientation_other', 'sensitive_data', 'ally_role', 'ally_role_other', 'password', 'password_confirmation', 'cgu', 'privacy'] as $field) {
        $response->assertSee('name="'.$field.'"', false);
    }

    $html = $response->getContent();
    // Pays : Bénin proposé en premier et présélectionné, liste ISO complète en français.
    expect($html)->toContain('<option value="BJ" selected>Bénin</option>')
        ->and(substr_count($html, '<option value="', strpos($html, 'name="country_code"')) >= 249)->toBeTrue()
        ->and($html)->toContain('>France</option>', '>Côte d’Ivoire</option>')
        ->and($html)->not->toContain('name="cgu" value="1" checked')
        ->and($html)->not->toContain('name="privacy" value="1" checked')
        ->and($html)->not->toContain('name="sensitive_data" value="1" checked')
        // Aucune case « j'ai l'âge » séparée : l'âge saisi en tient lieu.
        ->and($html)->not->toContain('name="age_confirmed"')
        ->and(substr($html, (int) strpos($html, '<form method="post" action="'.route('inscription.store'))))->not->toContain(' style="')
        ->and($html)->toContain('<option value="">Choisir…</option>');

    // Les choix de l'ancien formulaire : pronoms il/elle/iel, « Tu es ? » à deux choix.
    $response->assertSee('Membre de la communauté')->assertSee('Personne ressource / Allié·e');
    foreach (['elle', 'il', 'iel'] as $value) {
        expect($html)->toContain('<option value="'.$value.'"');
    }
    expect($html)->not->toContain('<option value="nr"');
});

it('affiche l’âge minimal de 18 ans et refuse 17 ans', function (): void {
    $this->get('/inscription')->assertOk()->assertSee('Au moins 18 ans, en chiffres.')->assertSee('min="18"', false);

    $this->post('/inscription', webRegisterPayload(['age' => 17]))
        ->assertSessionHasErrors(['age' => 'Tu dois avoir au moins 18 ans pour créer un compte.']);
    expect(User::query()->count())->toBe(0);

    $this->post('/inscription', webRegisterPayload(['age' => 18]))->assertSessionHasNoErrors();
    expect(User::query()->count())->toBe(1);
});

it('lit l’âge minimal dans la configuration, y compris dans le texte', function (): void {
    config(['xomiton.accounts.min_age' => 21]);

    $this->get('/inscription')->assertOk()->assertSee('Au moins 21 ans, en chiffres.')->assertSee('min="21"', false);

    $this->post('/inscription', webRegisterPayload(['age' => 20]))
        ->assertSessionHasErrors(['age' => 'Tu dois avoir au moins 21 ans pour créer un compte.']);
});

it('ne crée le compte qu’à la fin : un seul envoi, en attente de validation, session ouverte, ni code ni e-mail', function (): void {
    Notification::fake();
    $admin = F::member('admin');

    $this->post('/inscription', webRegisterPayload())
        ->assertRedirect(route('fil'))
        ->assertSessionHas('status', __('auth.register.created_pending'));

    $user = User::query()->where('email', 'awa@exemple.bj')->firstOrFail();
    $this->assertAuthenticatedAs($user);

    expect($user->pseudo)->toBe('Awa_S')
        ->and($user->name)->toBe('Awa Sossou')
        ->and($user->status)->toBe(UserStatus::Pending)
        ->and($user->email_verified_at)->toBeNull()
        ->and($user->hasRole('membre'))->toBeTrue()
        ->and($user->profile?->member_type)->toBe(MemberType::Member)
        ->and($user->profile?->age)->toBe(24)
        ->and($user->consents()->count())->toBe(4)
        ->and($user->consents()->ofKind(ConsentKind::Terms)->granted()->exists())->toBeTrue()
        ->and($user->consents()->ofKind(ConsentKind::Privacy)->granted()->exists())->toBeTrue()
        ->and(OtpCode::query()->forEmail('awa@exemple.bj', OtpPurpose::Signup)->count())->toBe(0);

    Mail::assertNothingSent();
    Notification::assertSentTo($admin, PeerPending::class);
});

it('ne crée aucun compte si une étape est invalide, rouvre l’étape fautive et garde les données saisies (sauf mots de passe)', function (): void {
    // Étape 1 : pseudo invalide.
    $this->from('/inscription')->post('/inscription', webRegisterPayload(['pseudo' => 'ab']))
        ->assertRedirect('/inscription')
        ->assertSessionHasErrors(['pseudo']);
    $this->get('/inscription')->assertSee('Étape 1 sur 3')->assertSee('value="Awa"', false)->assertSee('value="Sossou"', false)->assertDontSee('Xomiton2026!');

    // Étape 2 : pays absent → l'étape 2 est rouverte.
    $payload = webRegisterPayload(['country_code' => '']);
    $this->from('/inscription')->post('/inscription', $payload)->assertSessionHasErrors(['country_code']);
    $html = (string) $this->get('/inscription')->getContent();
    expect($html)->toContain('Étape 2 sur 3')->and($html)->toContain('Choisis ton pays.');

    // Étape 3 : consentements manquants → l'étape 3 est rouverte, le résumé d'erreurs est focalisable.
    $this->from('/inscription')->post('/inscription', webRegisterPayload(['cgu' => '', 'privacy' => '0']))
        ->assertSessionHasErrors(['cgu', 'privacy']);
    $this->get('/inscription')->assertSee('Étape 3 sur 3')->assertSee('class="alert alert-danger xm-error-summary"', false)->assertSee('tabindex="-1"', false);

    $this->assertGuest();
    expect(User::query()->count())->toBe(0);
});

it('applique les règles conditionnelles : membre → identité, orientation, consentement ; « autre » → précision ; allié·e → statut', function (): void {
    $payload = webRegisterPayload();
    unset($payload['gender_identity'], $payload['orientation'], $payload['sensitive_data']);
    postRegister($this, $payload)->assertSessionHasErrors(['gender_identity', 'orientation', 'sensitive_data']);

    $this->post('/inscription', webRegisterPayload(['gender_identity' => 'autre', 'orientation' => 'autre']))
        ->assertSessionHasErrors(['gender_identity_other', 'orientation_other']);

    $this->post('/inscription', webRegisterPayload(['sensitive_data' => '']))
        ->assertSessionHasErrors(['sensitive_data' => 'Pour continuer, accepte le traitement de ces informations sensibles.']);

    $ally = R::allyPayload();
    unset($ally['ally_role']);
    postRegister($this, $ally)->assertSessionHasErrors(['ally_role']);
    postRegister($this, R::allyPayload(['ally_role' => 'autre']))->assertSessionHasErrors(['ally_role_other']);
    expect(User::query()->count())->toBe(0);

    // Allié·e : aucune identité de genre ni orientation demandée ; le statut suffit.
    postRegister($this, R::allyPayload(['ally_role' => 'psychologue']))->assertRedirect(route('fil'));
    $user = User::query()->where('email', 'awa@exemple.bj')->firstOrFail();
    expect($user->profile?->member_type)->toBe(MemberType::Ally)->and($user->profile?->ally_role)->toBe('psychologue');
});

it('refuse un âge sous le minimum, un âge non numérique, un pays inconnu, des pronoms hors liste et un téléphone mal formé', function (): void {
    postRegister($this, webRegisterPayload(['age' => 17]))->assertSessionHasErrors(['age' => 'Tu dois avoir au moins 18 ans pour créer un compte.']);
    postRegister($this, webRegisterPayload(['age' => 'abc']))->assertSessionHasErrors(['age']);
    postRegister($this, webRegisterPayload(['country_code' => 'XX']))->assertSessionHasErrors(['country_code']);
    postRegister($this, webRegisterPayload(['pronouns' => 'nr']))->assertSessionHasErrors(['pronouns']);
    postRegister($this, webRegisterPayload(['phone' => '123']))->assertSessionHasErrors(['phone']);
    postRegister($this, webRegisterPayload(['country_code' => 'FR', 'phone' => '0612345678']))->assertSessionHasErrors(['phone']);
    $this->assertGuest();

    postRegister($this, webRegisterPayload(['country_code' => 'FR', 'phone' => '+33 6 12 34 56 78']))->assertRedirect(route('fil'));
});

it('garde zone puis commune du pays choisi, avec la commune dans sa zone', function (): void {
    $zone = Zone::factory()->create(['is_active' => true]);
    $commune = Commune::factory()->create(['zone_id' => $zone->getKey(), 'is_active' => true]);
    $other = Commune::factory()->create(['is_active' => true]);

    postRegister($this, webRegisterPayload(['zone_id' => $zone->getKey(), 'commune_id' => $other->getKey()]))->assertSessionHasErrors(['commune_id']);

    postRegister($this, webRegisterPayload(['zone_id' => $zone->getKey(), 'commune_id' => $commune->getKey(), 'city' => 'Cotonou']))->assertRedirect(route('fil'));
    $user = User::query()->where('email', 'awa@exemple.bj')->firstOrFail();
    expect($user->zone_id)->toBe($zone->getKey())->and($user->commune_id)->toBe($commune->getKey())->and($user->profile?->city)->toBe('Cotonou');
});

it('accepte une zone et une commune hors Bénin pour le pays choisi, et refuse celles d’un autre pays', function (): void {
    $douala = Zone::factory()->inCountry('CM')->create(['is_active' => true]);
    $commune = Commune::factory()->create(['zone_id' => $douala->getKey(), 'is_active' => true]);
    $benin = Zone::factory()->create(['is_active' => true]);

    postRegister($this, webRegisterPayload(['country_code' => 'CM', 'phone' => '+237 6 71 23 45 67', 'zone_id' => $benin->getKey()]))->assertSessionHasErrors(['zone_id']);
    $this->assertGuest();

    postRegister($this, webRegisterPayload(['country_code' => 'CM', 'phone' => '+237 6 71 23 45 67', 'zone_id' => $douala->getKey(), 'commune_id' => $commune->getKey()]))->assertRedirect(route('fil'));
    $user = User::query()->where('email', 'awa@exemple.bj')->firstOrFail();
    expect($user->zone_id)->toBe($douala->getKey())->and($user->commune_id)->toBe($commune->getKey());
});

it('propose zone et commune dans le formulaire pour tout pays qui a des zones actives', function (): void {
    $douala = Zone::factory()->inCountry('CM')->create(['name' => 'Littoral camerounais', 'slug' => 'littoral-cm', 'is_active' => true]);
    Commune::factory()->create(['zone_id' => $douala->getKey(), 'name' => 'Douala', 'slug' => 'douala', 'is_active' => true]);

    $this->get('/inscription')->assertOk()
        ->assertSee('Littoral camerounais')->assertSee('Douala')->assertSee('Cameroun')
        ->assertSee('x-show="hasZones"', false)
        ->assertSee('zoneCountries', false);
});

it('refuse chaque règle de base : pseudo pris (casse ignorée), e-mail pris, mot de passe faible, consentements manquants', function (): void {
    User::factory()->create(['pseudo' => 'awa_s', 'email' => 'deja@exemple.bj']);

    $this->from('/inscription')->post('/inscription', webRegisterPayload(['email' => 'DEJA@exemple.bj', 'password' => 'faible', 'password_confirmation' => 'faible', 'cgu' => '', 'privacy' => '0']))
        ->assertRedirect('/inscription')
        ->assertSessionHasErrors(['pseudo', 'email', 'password', 'cgu', 'privacy'])
        ->assertSessionHasErrors(['pseudo' => 'Ce pseudonyme est déjà pris. Choisis-en un autre.'])
        ->assertSessionHasErrors(['email' => __('auth.api.email_taken')]);

    foreach (['Court1Ab', 'sansmajuscule2026', 'SANSMINUSCULE2026', 'SansChiffreIci'] as $weak) {
        $this->postJson('/inscription', webRegisterPayload(['pseudo' => 'Nouvelle_'.strlen($weak), 'password' => $weak, 'password_confirmation' => $weak]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password'])
            ->assertJsonMissingValidationErrors(['pseudo', 'email']);
    }

    $this->assertGuest();
    expect(User::query()->count())->toBe(1);
});

it('exige la confirmation du mot de passe, identique au mot de passe', function (): void {
    $this->from('/inscription')->post('/inscription', webRegisterPayload(['password_confirmation' => 'Autre2026!x']))
        ->assertSessionHasErrors(['password' => 'Les deux mots de passe ne correspondent pas.']);
    $this->assertGuest();

    $payload = webRegisterPayload();
    unset($payload['password_confirmation']);
    $this->post('/inscription', $payload)->assertSessionHasErrors('password');
    $this->assertGuest();
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
        ->assertSee('Être prévenu·e de la réouverture')
        ->assertSee('name="consent"', false)
        ->assertSee(__('auth.register.closed.sign_in'))
        ->assertDontSee('name="pseudo"', false);

    $this->post('/inscription', webRegisterPayload())
        ->assertRedirect(route('inscription'))
        ->assertSessionHasErrors(['pseudo' => 'Fermé pour les fêtes, retour le 3 janvier.']);

    $this->assertGuest();
    expect(User::query()->count())->toBe(0);
});

it('met le compte en attente quel que soit le profil : pas de code de vérification, pas de bienvenue', function (): void {
    $this->post('/inscription', webRegisterPayload(['email' => 'ordinaire@exemple.bj']))->assertRedirect(route('fil'));

    $user = User::query()->where('email', 'ordinaire@exemple.bj')->firstOrFail();
    expect($user->status)->toBe(UserStatus::Pending)
        ->and(OtpCode::query()->forEmail('ordinaire@exemple.bj', OtpPurpose::Signup)->count())->toBe(0);
});

it('redirige les anciennes adresses du tunnel en 4 phases vers le fil, sans casser les comptes déjà créés', function (): void {
    foreach (['/inscription/profil', '/inscription/securite', '/inscription/consentements'] as $url) {
        $this->get($url)->assertRedirect('/fil');
    }

    foreach (['profile', 'security', 'consents'] as $step) {
        $this->post("/inscription/{$step}/passer")->assertRedirect('/fil');
    }

    $this->actingAs(User::factory()->withProfile()->create())->get('/inscription/profil')->assertRedirect('/fil');
    $this->post('/inscription/account/passer')->assertNotFound();
});

it('réserve l’inscription aux visiteur·ses', function (): void {
    $this->actingAs(User::factory()->create())->get('/inscription')->assertRedirect(route('fil'));
});
