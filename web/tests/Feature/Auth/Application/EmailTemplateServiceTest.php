<?php

declare(strict_types=1);

use App\Application\Mail\EmailTemplateService;
use App\Application\Mail\Exceptions\TemplateInactive;
use App\Application\Mail\Exceptions\TemplateNotFound;
use App\Enums\MailTransport;
use App\Mail\TemplateMail;
use App\Models\EmailTemplate;
use App\Models\MailLog;
use App\Models\MailOutbox;
use App\Support\Audit;
use App\Support\Settings;
use Database\Seeders\EmailTemplatesSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/*
 * P3-01 : EmailTemplateService::send() avec Mail::fake(), transport boîte d'envoi (MAIL_MAILER=outbox), repli sur
 * échec SMTP, template inactif ou inconnu, variables non fournies. Les templates viennent du seeder (RG-10).
 */

beforeEach(function (): void {
    $this->seed([EmailTemplatesSeeder::class, SettingsSeeder::class]);
});

it('rend un template avec la mise en page Xomiton, les variables échappées et la version texte', function (): void {
    $rendered = EmailTemplateService::render('code-verification', [
        'otp_code' => '482913',
        'otp_expiry' => '10 minutes',
        'user_first_name' => 'Awa <script>',
    ]);

    expect($rendered['subject'])->toBe('Ton code de vérification Xomiton')
        ->and($rendered['html'])->toContain('>482913</h1>', 'Awa &lt;script&gt;', 'logo-mono-blanc-email.png', 'contact@xomiton.bj', '<!DOCTYPE html>')
        ->and($rendered['html'])->toContain('Ouvre ce message pour le voir.')
        ->and($rendered['text'])->toContain('482913', 'Il est valable 10 minutes.')
        ->and($rendered['text'])->not->toContain('<p>')
        ->and($rendered['unknown_vars'])->toBe([]);
});

it('signale les variables non fournies et les laisse visibles', function (): void {
    $rendered = EmailTemplateService::render('reinitialisation-mot-de-passe', ['user_first_name' => 'Awa']);

    expect($rendered['unknown_vars'])->toBe(['otp_code', 'reset_link', 'otp_expiry'])
        ->and($rendered['html'])->toContain('{{otp_code}}');
});

it('refuse un slug inconnu ou un template désactivé au rendu', function (): void {
    EmailTemplate::query()->whereSlug('bienvenue')->update(['is_active' => false]);

    expect(fn () => EmailTemplateService::render('inexistant', []))->toThrow(TemplateNotFound::class)
        ->and(fn () => EmailTemplateService::render('bienvenue', []))->toThrow(TemplateInactive::class);
});

it('envoie par le mailer de .env et journalise l’envoi', function (): void {
    Mail::fake();
    config(['mail.default' => 'array']);

    $log = EmailTemplateService::send('code-verification', 'awa@exemple.bj', ['otp_code' => '123456', 'otp_expiry' => '10 minutes', 'user_first_name' => 'Awa'], 'otp:login');

    Mail::assertSent(TemplateMail::class, function (TemplateMail $mail): bool {
        return $mail->hasTo('awa@exemple.bj')
            && $mail->subjectLine === 'Ton code de vérification Xomiton'
            && str_contains($mail->htmlBody, '>123456</h1>')
            && $mail->templateSlug === 'code-verification'
            && $mail->hasFrom('contact@xomiton.bj', 'Xomiton');
    });

    expect($log)->toBeInstanceOf(MailLog::class)
        ->and($log->ok)->toBeTrue()
        ->and($log->transport)->toBe(MailTransport::Smtp)
        ->and($log->template_slug)->toBe('code-verification')
        ->and($log->context)->toBe('otp:login')
        ->and($log->to)->toBe('awa@exemple.bj')
        ->and($log->subject)->toBe('Ton code de vérification Xomiton')
        ->and($log->subject)->not->toContain('123456')
        ->and(MailOutbox::query()->count())->toBe(0);
});

it('masque les valeurs sensibles dans le sujet journalisé mais pas dans celui envoyé ni déposé', function (): void {
    Mail::fake();
    config(['mail.default' => EmailTemplateService::OUTBOX_MAILER]);

    $log = EmailTemplateService::send('code-verification', 'awa@exemple.bj', ['otp_code' => '482913', 'otp_expiry' => '10 minutes', 'user_first_name' => 'Awa']);

    expect($log->subject)->toBe('Ton code de vérification Xomiton')
        ->and(MailOutbox::query()->firstOrFail()->subject)->toBe('Ton code de vérification Xomiton')
        ->and(EmailTemplateService::redactedSubject('Ton code Xomiton : 482913', ['otp_code' => '482913']))->toBe('Ton code Xomiton : '.Audit::MASK)
        ->and(EmailTemplateService::redactedSubject('Bienvenue Awa', ['user_first_name' => 'Awa']))->toBe('Bienvenue Awa')
        ->and(EmailTemplateService::redactedSubject('Code 12', ['otp_code' => '']))->toBe('Code 12');
});

it('dépose l’e-mail complet dans la boîte d’envoi quand aucun transport n’est utilisable', function (): void {
    Mail::fake();
    config(['mail.default' => EmailTemplateService::OUTBOX_MAILER]);

    $log = EmailTemplateService::send('bienvenue', 'awa@exemple.bj', ['user_first_name' => 'Awa'], 'inscription');

    Mail::assertNothingSent();

    $queued = MailOutbox::query()->pending()->firstOrFail();

    expect($log->ok)->toBeTrue()
        ->and($log->transport)->toBe(MailTransport::Outbox)
        ->and($log->error)->toBeNull()
        ->and($queued->to)->toBe('awa@exemple.bj')
        ->and($queued->subject)->toBe('Bienvenue dans ton espace safe, Awa')
        ->and($queued->html)->toContain('<!DOCTYPE html>', 'Bienvenue Awa')
        ->and($queued->text)->toContain('Bienvenue Awa')
        ->and($queued->template_slug)->toBe('bienvenue')
        ->and($queued->from_address)->toBe('contact@xomiton.bj')
        ->and($queued->attempts)->toBe(0);
});

it('utilise le serveur SMTP des paramètres et se replie sur la boîte d’envoi si la connexion échoue', function (): void {
    Settings::set('smtp_host', '127.0.0.1');
    Settings::set('smtp_port', 9);
    Settings::set('smtp_encryption', 'none');
    Settings::set('smtp_from_name', 'Équipe Xomiton');
    Settings::set('smtp_reply_to', 'aide@xomiton.bj');

    $log = EmailTemplateService::send('code-verification', 'awa@exemple.bj', ['otp_code' => '111111', 'otp_expiry' => '10 minutes']);

    $queued = MailOutbox::query()->pending()->firstOrFail();

    expect($log->ok)->toBeTrue()
        ->and($log->transport)->toBe(MailTransport::Outbox)
        ->and($log->error)->toStartWith('SMTP : ')
        ->and($queued->attempts)->toBe(1)
        ->and($queued->last_error)->toStartWith('SMTP : ')
        ->and($queued->from_name)->toBe('Équipe Xomiton')
        ->and($queued->reply_to)->toBe('aide@xomiton.bj');
});

it('journalise sans envoyer un template désactivé, inconnu ou une adresse invalide', function (): void {
    Mail::fake();
    EmailTemplate::query()->whereSlug('bienvenue')->update(['is_active' => false]);

    $inactive = EmailTemplateService::send('bienvenue', 'awa@exemple.bj');
    $unknown = EmailTemplateService::send('inexistant', 'awa@exemple.bj');
    $invalid = EmailTemplateService::send('code-verification', 'pas-une-adresse');

    Mail::assertNothingSent();

    expect($inactive->ok)->toBeFalse()
        ->and($inactive->transport)->toBe(MailTransport::None)
        ->and($inactive->error)->toContain('désactivé')
        ->and($unknown->ok)->toBeFalse()
        ->and($unknown->error)->toContain('introuvable')
        ->and($invalid->ok)->toBeFalse()
        ->and($invalid->error)->toBe('Adresse destinataire invalide.')
        ->and(MailLog::query()->failed()->count())->toBe(3)
        ->and(MailOutbox::query()->count())->toBe(0);
});

/*
 * Mise en page e-mail unique (2026-10-28) : chaque modèle système rend avec des variables d'exemple, contient le
 * layout (600 px, mode sombre, pied de confidentialité), une version texte, aucune variable {{…}} résiduelle, aucun
 * lien http et reste sous 100 Ko ; objet et pré-en-tête ne portent aucune donnée sensible (mode discret).
 */
it('rend chaque modèle système avec le layout, le texte, sans variable résiduelle ni lien http, sous 100 Ko, en mode discret', function (): void {
    URL::forceRootUrl('https://xomiton.test');
    URL::forceScheme('https');
    $sample = [
        'user_first_name' => 'Awa', 'user_pseudo' => 'awa_b', 'otp_code' => '482913', 'otp_expiry' => '10 minutes',
        'reset_link' => 'https://xomiton.test/r/1', 'activation_link' => 'https://xomiton.test/a/1',
        'unsubscribe_link' => 'https://xomiton.test/newsletter/desinscription/abc', 'alert_reference' => 'ALT-2026-0042',
        'alert_zone' => 'Cotonou', 'event_title' => 'Atelier confidentiel', 'event_date' => 'demain à 15 h',
        'deletion_date' => '15 novembre 2026', 'deletion_days' => '30', 'export_link' => 'https://xomiton.test/e/1',
        'export_expiry' => '48 heures', 'report_title' => 'Rapport mensuel', 'report_period' => 'octobre 2026', 'report_format' => 'PDF',
    ];
    $secrets = ['482913', 'ALT-2026-0042', 'Cotonou', 'Atelier confidentiel', 'Rapport mensuel', 'demain à 15 h'];
    $slugs = EmailTemplate::query()->pluck('slug');
    expect($slugs->count())->toBeGreaterThanOrEqual(11);

    foreach ($slugs as $slug) {
        $r = EmailTemplateService::render($slug, $sample);
        expect($r['html'])->toContain('width="600"', 'prefers-color-scheme:dark', 'logo-mono-blanc-email.png', 'contact@xomiton.bj')
            ->not->toContain('.svg')->not->toMatch('/\{\{[^}]*\}\}/')->not->toContain('http://')
            ->and($r['text'])->not->toBe('')->not->toMatch('/\{\{[^}]*\}\}/')->not->toContain('http://')->not->toContain('<p')
            ->and(strlen($r['html']))->toBeLessThan(100 * 1024)
            ->and($r['unknown_vars'])->toBe([]);

        foreach ($secrets as $secret) {
            expect($r['subject'])->not->toContain($secret);
            expect(trim((string) EmailTemplate::query()->whereSlug($slug)->value('preheader')))->not->toContain($secret);
        }
    }
});

it('transforme un lien isolé en bouton d’au moins 44 px et le code en bloc dédié', function (): void {
    $r = EmailTemplateService::render('code-verification', ['otp_code' => '482913', 'otp_expiry' => '10 minutes', 'user_first_name' => 'Awa']);
    expect($r['html'])->toContain('class="xm-code"', '482913')->and($r['subject'])->not->toContain('482913');

    $b = EmailTemplateService::render('bienvenue', ['user_first_name' => 'Awa', 'login_link' => 'https://xomiton.test/connexion']);
    expect($b['html'])->toContain('class="xm-btn"', 'padding:14px 28px', 'line-height:20px')
        ->and($b['text'])->toContain('Me connecter : https://xomiton.test/connexion');
});
