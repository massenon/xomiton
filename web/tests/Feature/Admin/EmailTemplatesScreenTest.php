<?php

declare(strict_types=1);

use App\Application\Mail\EmailTemplateService;
use App\Application\Mail\Exceptions\TemplateInactive;
use App\Livewire\Admin\Emails\Index as EmailsScreen;
use App\Models\AuditLog;
use App\Models\EmailTemplate;
use App\Models\MailLog;
use App\Models\MailOutbox;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

/*
 * P14-01 : /admin/emails (Livewire) — `email_templates.manage` (admin, superadmin) ; slug système immuable et suppression
 * par slug (RG-10) ; HTML nettoyé ; variables conservées ; aperçu avec données fictives ; test d'envoi par la boîte d'envoi ;
 * template inactif = envoi refusé et journalisé.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function emailUser(string $role = 'admin'): User
{
    $user = User::factory()->create();
    $user->syncRoles([$role]);

    return $user;
}

function emailScreen(string $role = 'admin')
{
    test()->actingAs(emailUser($role));

    return Livewire::test(EmailsScreen::class);
}

function validEmailForm(array $override = []): array
{
    return $override + [
        'name' => 'Rappel de rendez-vous',
        'slug' => '',
        'category' => 'communaute',
        'subject' => '{{site_name}} : ton rendez-vous',
        'preheader' => 'Rendez-vous le {{current_year}}',
        'body' => '<p>Bonjour {{user_first_name}}</p><p><a href="{{activation_link}}">Voir</a></p>',
        'is_active' => true,
    ];
}

it('réserve /admin/emails à l’administration : 403 pour le modérateur, le pair et le membre', function (string $role): void {
    $this->actingAs(emailUser($role))->get('/admin/emails')->assertForbidden();
})->with(['moderateur', 'pair', 'membre']);

it('affiche la liste, les KPI et l’état de l’envoi', function (): void {
    EmailTemplate::factory()->create(['name' => 'Alpha', 'slug' => 'alpha']);
    EmailTemplate::factory()->system()->inactive()->create(['name' => 'Bêta', 'slug' => 'beta']);
    MailOutbox::factory()->create();
    $this->actingAs(emailUser())->get('/admin/emails')->assertOk()->assertSee('Alpha')->assertSee('Bêta')->assertSee('alpha');

    emailScreen()->assertSee('Templates')->assertSee('Système')->assertSee('Désactivé');
});

it('filtre par catégorie, état et recherche nom / slug', function (): void {
    EmailTemplate::factory()->create(['name' => 'Alpha', 'slug' => 'alpha', 'category' => 'alertes']);
    EmailTemplate::factory()->create(['name' => 'Bêta', 'slug' => 'beta', 'category' => 'autre', 'is_active' => false]);

    emailScreen()->set('category', 'alertes')->assertSee('Alpha')->assertDontSee('Bêta')
        ->set('category', '')->set('state', 'inactif')->assertSee('Bêta')->assertDontSee('Alpha')
        ->set('state', '')->set('search', 'bet')->assertSee('Bêta')->assertDontSee('Alpha');
});

it('crée un template : slug généré, HTML nettoyé, variables conservées jusque dans les liens', function (): void {
    emailScreen()->call('create')
        ->set('form', validEmailForm(['body' => '<script>alert(1)</script><p onclick="x()">Bonjour {{user_first_name}}</p><p><a href="{{activation_link}}">Voir</a></p>']))
        ->call('save')->assertHasNoErrors();

    $template = EmailTemplate::query()->whereSlug('rappel-de-rendez-vous')->firstOrFail();
    expect($template->is_system)->toBeFalse()
        ->and($template->body)->not->toContain('script')->not->toContain('onclick')
        ->and($template->body)->toContain('href="{{activation_link}}"')
        ->and($template->variables)->toEqualCanonicalizing(['site_name', 'current_year', 'user_first_name', 'activation_link']);

    $audit = AuditLog::query()->where('action', 'email_template.created')->firstOrFail();
    expect($audit->detail)->toBe('rappel-de-rendez-vous');
});

it('refuse un nom, un objet ou un corps trop courts, une catégorie inconnue et un slug mal formé', function (): void {
    emailScreen()->call('create')->set('form', validEmailForm(['name' => 'ab', 'subject' => 'x', 'body' => '<p>x</p>', 'category' => 'zzz', 'slug' => 'Pas Valide']))
        ->call('save')->assertHasErrors(['form.name', 'form.subject', 'form.body', 'form.category', 'form.slug']);
    expect(EmailTemplate::query()->count())->toBe(0);
});

it('génère un slug libre quand il est déjà pris', function (): void {
    EmailTemplate::factory()->create(['slug' => 'rappel-de-rendez-vous']);

    emailScreen()->call('create')->set('form', validEmailForm())->call('save')->assertHasNoErrors();

    expect(EmailTemplate::query()->whereSlug('rappel-de-rendez-vous-2')->exists())->toBeTrue();
});

it('verrouille le slug d’un template système (RG-10) : la modification ne le change pas', function (): void {
    $system = EmailTemplate::factory()->system()->create(['slug' => 'code-verification', 'name' => 'Code']);

    emailScreen()->call('edit', $system->id)->assertSet('form.slug', 'code-verification')->assertSee('readonly', false)
        ->set('form.slug', 'autre-chose')->set('form.name', 'Code de vérification')->call('save')->assertHasNoErrors();

    expect($system->refresh()->slug)->toBe('code-verification')->and($system->name)->toBe('Code de vérification');
});

it('permet de renommer le slug d’un template ordinaire', function (): void {
    $template = EmailTemplate::factory()->create(['slug' => 'ancien']);

    emailScreen()->call('edit', $template->id)->set('form.slug', 'nouveau')->call('save')->assertHasNoErrors();

    expect($template->refresh()->slug)->toBe('nouveau');
});

it('duplique en `-copie`, désactivé et jamais système', function (): void {
    $system = EmailTemplate::factory()->system()->create(['slug' => 'alerte-routee']);

    emailScreen()->call('duplicate', $system->id);

    $copy = EmailTemplate::query()->whereSlug('alerte-routee-copie')->firstOrFail();
    expect($copy->is_active)->toBeFalse()->and($copy->is_system)->toBeFalse()->and($copy->body)->toBe($system->body);

    emailScreen()->call('duplicate', $system->id);
    expect(EmailTemplate::query()->whereSlug('alerte-routee-copie-2')->exists())->toBeTrue();
});

it('active et désactive : un template inactif refuse l’envoi, exception journalisée', function (): void {
    $template = EmailTemplate::factory()->create(['slug' => 'rappel', 'is_active' => true]);

    emailScreen()->call('toggle', $template->id);
    expect($template->refresh()->is_active)->toBeFalse();

    expect(fn () => EmailTemplateService::render('rappel'))->toThrow(TemplateInactive::class);

    Log::spy();
    $log = EmailTemplateService::send('rappel', 'awa@exemple.bj');
    expect($log->ok)->toBeFalse();
    Log::shouldHaveReceived('warning')->once();
    expect(MailLog::query()->where('ok', false)->where('template_slug', 'rappel')->count())->toBe(1);

    emailScreen()->call('toggle', $template->id);
    expect($template->refresh()->is_active)->toBeTrue();
});

it('supprime un template ordinaire sans saisie, un template système seulement avec son slug (RG-10) et le superadmin', function (): void {
    $plain = EmailTemplate::factory()->create(['slug' => 'ordinaire']);
    $system = EmailTemplate::factory()->system()->create(['slug' => 'newsletter-confirmation']);

    emailScreen()->call('askDelete', $plain->id)->call('delete')->assertHasNoErrors();
    expect(EmailTemplate::query()->whereSlug('ordinaire')->exists())->toBeFalse();

    // Un·e admin ne supprime pas un système.
    emailScreen('admin')->call('askDelete', $system->id)->assertForbidden();

    // Le superadmin doit saisir le slug exact.
    emailScreen('superadmin')->call('askDelete', $system->id)->set('deleteSlug', 'mauvais')->call('delete')->assertHasErrors('deleteSlug');
    expect(EmailTemplate::query()->whereSlug('newsletter-confirmation')->exists())->toBeTrue();

    emailScreen('superadmin')->call('askDelete', $system->id)->set('deleteSlug', 'newsletter-confirmation')->call('delete')->assertHasNoErrors();
    expect(EmailTemplate::query()->whereSlug('newsletter-confirmation')->exists())->toBeFalse();
});

it('prévisualise un contenu non enregistré avec des données fictives, bureau et mobile', function (): void {
    $screen = emailScreen()->call('create')->set('form', validEmailForm(['body' => '<p>Bonjour {{user_first_name}}, code {{otp_code}} {{inconnue_x}}</p>']))
        ->call('setPane', 'preview')->assertSet('pane', 'preview');

    expect($screen->get('previewHtml'))->toContain('Bonjour Awa')->toContain('482913')
        ->and($screen->get('previewSubject'))->toContain('Xomiton')
        ->and($screen->get('previewUnknown'))->toContain('inconnue_x')
        ->and(EmailTemplate::query()->count())->toBe(0);

    $screen->call('setPreviewMode', 'mobile')->assertSet('previewMode', 'mobile')->assertSee('375px');
});

it('dépose le test d’envoi dans la boîte d’envoi, sans SMTP ni adresse dans l’audit', function (): void {
    Mail::fake();

    emailScreen()->call('create')->set('form', validEmailForm())->set('testTo', 'test@exemple.bj')->call('sendTest')->assertHasNoErrors();

    $mail = MailOutbox::query()->where('template_slug', 'test')->firstOrFail();
    expect($mail->to)->toBe('test@exemple.bj')->and($mail->subject)->toStartWith('[TEST] ')->and($mail->html)->toContain('Awa');
    Mail::assertNothingSent();
    Mail::assertNothingQueued();
    expect(AuditLog::query()->where('action', 'email_template.test_queued')->count())->toBe(1)
        ->and(AuditLog::query()->get()->toJson())->not->toContain('test@exemple.bj');
});

it('refuse une adresse de test invalide et limite les tests à 5 par minute', function (): void {
    $screen = emailScreen()->call('create')->set('form', validEmailForm())->set('testTo', 'pas-une-adresse')->call('sendTest')->assertHasErrors('testTo');

    $screen->set('testTo', 'a@exemple.bj');
    foreach (range(1, 5) as $i) {
        $screen->call('sendTest');
    }
    $screen->call('sendTest')->assertHasErrors('testTo');
    expect(MailOutbox::query()->count())->toBe(5);
});

it('masque les adresses dans les derniers envois', function (): void {
    MailLog::factory()->create(['to' => 'chantal@exemple.bj', 'template_slug' => 'code-verification']);

    emailScreen()->assertSee('c***@exemple.bj')->assertDontSee('chantal@exemple.bj');
});

it('refuse une variable non autorisée dans un template et dans son test d’envoi', function (): void {
    $screen = emailScreen()->call('create')
        ->set('form', validEmailForm(['body' => '<p>Bonjour {{user_first_name}} {{mot_de_passe_clair}}</p>']))
        ->call('save')->assertHasErrors('form.body');
    expect(EmailTemplate::query()->count())->toBe(0);

    $screen->set('testTo', 'a@exemple.bj')->call('sendTest')->assertHasErrors('testTo');
    expect(MailOutbox::query()->count())->toBe(0);
});

it('plafonne les envois de test à 20 par jour et par compte', function (): void {
    $screen = emailScreen()->call('create')->set('form', validEmailForm())->set('testTo', 'a@exemple.bj');
    $user = auth()->user();

    foreach (range(1, 20) as $i) {
        Illuminate\Support\Facades\RateLimiter::hit('email-template-test-day:'.$user->getKey(), 86400);
    }

    $screen->call('sendTest')->assertHasErrors('testTo');
    expect(MailOutbox::query()->count())->toBe(0);
});
