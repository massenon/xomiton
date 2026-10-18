<?php

declare(strict_types=1);

use App\Enums\OtpPurpose;
use App\Http\Controllers\Auth\Support\RegistrationSteps;
use App\Models\NewsletterSubscriber;
use App\Models\OtpCode;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\EmailTemplatesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Sleep;

/*
 * Lot auth-ecrans (P4-09) : corrections de l'analyse d'écart des écrans d'authentification contre
 * prototype_social/sign-in.php, sign-up.php, forgot-password.php, design-system/xomiton/pages/authentification.md,
 * LOGIN_INSCRPTION_MULTISTEP_USER_INSTRUCTION.md et Q8, Q12, Q13, Q20, Q30 (écart consigné dans DECISIONS.md).
 */

beforeEach(function (): void {
    $this->seed([RolesAndPermissionsSeeder::class, EmailTemplatesSeeder::class, SettingsSeeder::class]);
    Mail::fake();
    config(['mail.default' => 'array']);
});

it('tire les titres et descriptions des écrans de lang/fr/seo.php : connexion et inscription indexées, au tutoiement', function (): void {
    $nb = "\u{00A0}";
    $loginDescription = 'Connecte-toi à ton espace Xomiton avec ton e-mail ou ton pseudonyme et ton mot de passe'.$nb.': retrouve ton fil, tes messages, tes groupes et tes ressources.';
    $registerDescription = 'Crée ton compte en une étape, avec un pseudonyme et un mot de passe'.$nb.': ton vrai nom n’est jamais demandé. Communauté bienveillante et soutien confidentiel.';

    $login = (string) $this->get('/connexion')->assertOk()->getContent();
    expect($login)
        ->toContain('<title>Se connecter'.$nb.': retrouver mon fil, mes messages – Xomiton</title>')
        ->toContain('<meta name="description" content="'.e($loginDescription).'">')
        ->toContain('<meta name="robots" content="index, follow, max-image-preview:large">');

    $register = (string) $this->get('/inscription')->assertOk()->getContent();
    expect($register)
        ->toContain('<title>Créer un compte'.$nb.': rejoindre la communauté – Xomiton</title>')
        ->toContain('<meta name="description" content="'.e($registerDescription).'">')
        ->toContain('<meta name="robots" content="index, follow, max-image-preview:large">');

    $forgot = (string) $this->get('/mot-de-passe-oublie')->assertOk()->getContent();
    expect($forgot)
        ->toContain('<title>Mot de passe oublié – Xomiton</title>')
        ->toContain('<meta name="robots" content="noindex, nofollow">');

    // 140 à 160 caractères (P4-11), tutoiement du tunnel (VOICE_AND_TONE §3, Q36, Q47), mot de passe nommé.
    foreach ([$loginDescription, $registerDescription] as $description) {
        expect(mb_strlen($description))->toBeGreaterThanOrEqual(140)->toBeLessThanOrEqual(160)
            ->and($description)->toContain('mot de passe')
            ->not->toMatch('/\b(vous|votre|vos|Connectez|Créez)\b/u');
    }
});

it('met le code de vérification et le nouveau mot de passe en noindex, avec leur propre titre', function (): void {
    $user = User::factory()->unverified()->create(['email' => 'awa@exemple.bj']);
    $user->assignRole('membre');
    app(App\Application\Auth\OtpService::class)->issue('awa@exemple.bj', OtpPurpose::Signup);

    $otp = (string) $this->actingAs($user)->get('/code-de-verification')->assertOk()->getContent();
    expect($otp)
        ->toContain('<title>Code de vérification – Xomiton</title>')
        ->toContain('<meta name="robots" content="noindex, nofollow">');

    auth()->logout();
    $this->post('/mot-de-passe-oublie', ['email' => 'awa@exemple.bj']);
    OtpCode::query()->forEmail('awa@exemple.bj', OtpPurpose::Reset)->valid()->latest('id')->firstOrFail()
        ->forceFill(['code_hash' => OtpCode::hash('975310')])->save();
    $this->post('/code-de-verification', ['purpose' => 'reset', 'code' => '975310'])->assertRedirect(route('reinitialisation'));

    $reset = (string) $this->get('/nouveau-mot-de-passe')->assertOk()->getContent();
    expect($reset)
        ->toContain('<title>Nouveau mot de passe – Xomiton</title>')
        ->toContain('<meta name="robots" content="noindex, nofollow">');
});

it('met la page « inscriptions fermées » en noindex avec son titre et le formulaire newsletter partagé, avec consentement', function (): void {
    Settings::set('registrations_open', false);

    $html = (string) $this->get('/inscription')->assertOk()->getContent();

    expect($html)
        ->toContain('<title>Inscriptions momentanément fermées – Xomiton</title>')
        ->toContain('<meta name="robots" content="noindex, nofollow">')
        ->not->toContain('Crée ton compte en une étape')
        ->toContain('id="inscription"', 'name="nl_form" value="page"', 'name="website"', 'name="consent" value="1"')
        ->and(substr_count($html, 'name="consent"'))->toBe(1);
});

it('abonne à la newsletter depuis la page « inscriptions fermées » (consentement coché), et affiche l’erreur sans lui', function (): void {
    Sleep::fake();
    Settings::set('registrations_open', false);
    $this->get('/inscription')->assertOk();

    $this->post(route('newsletter.subscribe'), ['email' => 'awa@exemple.bj', 'nl_form' => 'page', 'back' => '/newsletter', 'website' => ''])
        ->assertRedirect(route('newsletter.show').'#inscription')
        ->assertSessionHasErrors(['consent' => 'Cochez la case de consentement pour vous inscrire.'], null, 'newsletter');
    $this->get('/newsletter')->assertOk()->assertSee('Cochez la case de consentement pour vous inscrire.');

    $this->post(route('newsletter.subscribe'), ['email' => 'awa@exemple.bj', 'consent' => '1', 'nl_form' => 'page', 'back' => '/newsletter', 'website' => ''])
        ->assertRedirect(route('newsletter.thanks'));

    expect(NewsletterSubscriber::query()->whereEmail('awa@exemple.bj')->exists())->toBeTrue();
});

it('titre chaque phase de l’inscription « étape n sur 4 » et ne laisse indexer que la phase 1', function (): void {
    expect(RegistrationSteps::seo('account'))->toMatchArray(['title' => 'Créer un compte'."\u{00A0}".': rejoindre la communauté – Xomiton', 'index' => true])
        ->and(RegistrationSteps::seo('profile'))->toBe(['title' => 'Créer mon compte, étape 2 sur 4 – Xomiton', 'index' => false])
        ->and(RegistrationSteps::seo('security')['title'])->toBe('Créer mon compte, étape 3 sur 4 – Xomiton')
        ->and(RegistrationSteps::seo('consents')['title'])->toBe('Créer mon compte, étape 4 sur 4 – Xomiton');

    $user = User::factory()->create();
    $user->assignRole('membre');

    foreach (['/inscription/profil' => 2, '/inscription/securite' => 3, '/inscription/consentements' => 4] as $url => $n) {
        $html = (string) $this->actingAs($user)->get($url)->assertOk()->getContent();
        expect($html)
            ->toContain('<title>Créer mon compte, étape '.$n.' sur 4 – Xomiton</title>')
            ->toContain('<meta name="robots" content="noindex, nofollow">')
            ->toContain('data-xm-step-counter>Étape '.$n.' sur 4, facultative</p>');
    }
});

it('marque l’étape courante facultative dans le compteur, jamais la phase 1 obligatoire', function (): void {
    $this->get('/inscription')
        ->assertOk()
        ->assertSee('data-xm-step-counter>Étape 1 sur 4</p>', false)
        ->assertDontSee('Étape 1 sur 4, facultative');
});

it('n’affiche aucun rouge hors erreurs sur la connexion : le lien Urgences est neutre, avec une icône', function (): void {
    $html = (string) $this->get('/connexion')->assertOk()->getContent();

    expect($html)->not->toContain('text-danger')
        ->and($html)->toContain('<a href="'.route('urgences').'"><i class="bi bi-telephone me-1" aria-hidden="true"></i>Urgences</a>');
});

it('écrit la jauge en texte et en couleur : niveau préfixé pour les lecteurs d’écran, neutre tant que le champ est vide', function (): void {
    $html = (string) $this->get('/inscription')->assertOk()->getContent();

    expect($html)
        ->toContain('class="xm-pw-meter mt-2" data-level="empty"')
        ->toContain('<p class="xm-pw-meter__label small fw-bold mb-1" aria-live="polite" aria-atomic="true"><span class="visually-hidden">Solidité du mot de passe'."\u{00A0}".': </span><span data-password-label>Saisis un mot de passe</span></p>')
        ->and(substr_count($html, 'class="xm-pw-meter__segment"'))->toBe(4)
        ->and(substr_count($html, 'data-rule="'))->toBe(5);
});

it('relie le champ mot de passe à la liste des règles (aria-describedby), sur l’inscription et le nouveau mot de passe', function (): void {
    $html = (string) $this->get('/inscription')->assertOk()->getContent();

    expect($html)
        ->toContain('<ul id="pwMeter-rules" class="xm-pw-meter__rules')
        ->toMatch('/<input[^>]*id="f-password"[^>]*aria-describedby="f-password-hint pwMeter-rules"/')
        ->not->toContain('data-password-meter="#pwMeter" aria-describedby="pwMeter-rules"');

    // L'erreur s'ajoute sans écraser l'aide ni les règles.
    $field = Blade::render('<x-field name="password" type="password" label="Mot de passe" hint="Aide" error="Trop faible." aria-describedby="pwMeter-rules" />');
    expect($field)->toContain('aria-describedby="f-password-hint pwMeter-rules f-password-error"');

    $reset = Blade::render("@include('auth.partials.password-meter', ['id' => 'pwMeter'])");
    expect($reset)->toContain('id="pwMeter-rules"')
        ->and((string) file_get_contents(resource_path('views/auth/reset-password.blade.php')))->toContain('aria-describedby="pwMeter-rules"');
});

it('pointe les liens des conditions et de la politique de confidentialité vers leurs ancres de /confidentialite', function (): void {
    $html = (string) $this->get('/inscription')->assertOk()->getContent();

    expect($html)
        ->toContain('<a href="'.route('confidentialite').'#conditions" target="_blank" rel="noopener">conditions d’utilisation')
        ->toContain('<a href="'.route('confidentialite').'#charte" target="_blank" rel="noopener">')
        ->toContain('<a href="'.route('confidentialite').'#confidentialite" target="_blank" rel="noopener">politique de confidentialité');
});

it('laisse la première case recevoir un code collé avec séparateurs (maxlength = 2 × longueur), les autres un chiffre', function (): void {
    $user = User::factory()->unverified()->create(['email' => 'awa@exemple.bj']);
    $user->assignRole('membre');
    app(App\Application\Auth\OtpService::class)->issue('awa@exemple.bj', OtpPurpose::Signup);

    $html = (string) $this->actingAs($user)->get('/code-de-verification')->assertOk()->getContent();

    expect($html)->toMatch('/<input[^>]*id="f-code"[^>]*maxlength="12"/')
        ->and(preg_match_all('/<input[^>]*id="f-code-\d"[^>]*maxlength="1"/', $html))->toBe(5)
        // « 482 913 », « 4 8 2 9 1 3 » : tiennent dans la case.
        ->and(mb_strlen('4 8 2 9 1 3'))->toBeLessThanOrEqual(12);
});

it('accepte un code collé d’un bloc dans la première case, avec espaces, tirets ou points (sans JavaScript)', function (string $pasted): void {
    $user = User::factory()->unverified()->create(['email' => 'awa@exemple.bj']);
    $user->assignRole('membre');
    app(App\Application\Auth\OtpService::class)->issue('awa@exemple.bj', OtpPurpose::Signup);
    OtpCode::query()->forEmail('awa@exemple.bj', OtpPurpose::Signup)->valid()->latest('id')->firstOrFail()
        ->forceFill(['code_hash' => OtpCode::hash('482913')])->save();

    $this->actingAs($user)
        ->post('/code-de-verification', ['purpose' => 'signup', 'code' => [$pasted, '', '', '', '', '']])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('inscription.profil'));

    expect($user->fresh()?->email_verified_at)->not->toBeNull();
})->with(['482 913', '482-913', '482.913', "482\u{00A0}913"]);

it('place une espace insécable avant « : ; ? ! » et à l’intérieur des guillemets dans tous les textes du tunnel', function (): void {
    $texts = Arr::dot((array) trans('auth'));
    $texts += ['seo.noindex.forgot_password' => __('seo.noindex.forgot_password'), 'seo.noindex.reset_password' => __('seo.noindex.reset_password'),
        'seo.noindex.register_step' => __('seo.noindex.register_step'), 'seo.pages.connexion.title' => __('seo.pages.connexion.title'),
        'seo.pages.inscription.title' => __('seo.pages.inscription.title')];

    foreach ($texts as $key => $text) {
        expect((string) $text)
            ->not->toMatch('/ [;?!»]/u', (string) $key)
            ->not->toMatch('/ :(?![a-z_])/u', (string) $key)
            ->not->toMatch('/« /u', (string) $key);
    }
});

it('borne la largeur du formulaire entre 440 et 520 px et garde la grille 5/12 · 7/12 (Q20 a)', function (): void {
    $wide = Blade::render('<x-layouts.auth :max-width="640"><p>Contenu</p></x-layouts.auth>');
    $narrow = Blade::render('<x-layouts.auth :max-width="320"><p>Contenu</p></x-layouts.auth>');

    expect($wide)->toContain('style="max-width:520px"')
        ->and($narrow)->toContain('style="max-width:440px"')
        ->and($wide)->toContain('class="col-lg-5 d-none d-lg-flex xm-auth-side')
        ->and($wide)->toContain('class="col-lg-7 d-flex align-items-center"')
        ->and($wide)->toContain('logo-mono-blanc.svg');
});

it('sert une variante haute densité de sign-in.jpg par image-set() (WebP portrait, colonne 5/12 pleine hauteur)', function (): void {
    $path = public_path('assets/images/xomiton/sign-in-2x.webp');
    $info = getimagesize($path) ?: [0, 0, 'mime' => null];

    expect($info['mime'])->toBe('image/webp')
        ->and($info[1])->toBeGreaterThanOrEqual(1800)
        ->and($info[0])->toBeGreaterThanOrEqual(1200)
        ->and(filesize($path))->toBeLessThan(200 * 1024);

    $html = (string) $this->get('/connexion')->assertOk()->getContent();
    expect($html)
        ->toContain('background-image:url(&#039;'.asset('assets/images/xomiton/sign-in.jpg').'&#039;)')
        ->toContain('background-image:image-set(url(&#039;'.asset('assets/images/xomiton/sign-in.jpg').'&#039;) 1x, url(&#039;'.asset('assets/images/xomiton/sign-in-2x.webp').'&#039;) 2x)');

    // Sans variante (hero-12.jpg) : l'image 1x seule.
    Settings::set('registrations_open', true);
    expect((string) $this->get('/inscription')->getContent())->not->toContain('hero-12-2x.webp')->not->toContain('image-set(url(&#039;'.asset('assets/images/xomiton/hero-12.jpg'));
});

it('livre l’illustration sign-in.jpg recompressée (authentification.md) : 1600 px de large au plus, moins de 120 Ko', function (): void {
    $path = public_path('assets/images/xomiton/sign-in.jpg');
    [$width] = getimagesize($path) ?: [0];

    expect($width)->toBeGreaterThan(0)->toBeLessThanOrEqual(1600)
        ->and(filesize($path))->toBeLessThan(120 * 1024);
});
