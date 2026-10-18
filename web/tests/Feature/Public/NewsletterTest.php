<?php

declare(strict_types=1);

use App\Application\Newsletter\NewsletterLinks;
use App\Enums\ConsentKind;
use App\Enums\SubscriberStatus;
use App\Models\AuditLog;
use App\Models\Consent;
use App\Models\MailOutbox;
use App\Models\NewsletterCampaignRecipient;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use Database\Seeders\EmailTemplatesSeeder;
use Database\Seeders\NewsletterTaxonomySeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Illuminate\Support\Timebox;

/*
 * Newsletter publique (P4-07, prototype newsletter.php, vous) : page /newsletter, formulaire du pied de page, double
 * opt-in par la boîte d'envoi (`newsletter-confirmation`, jamais de SMTP dans la requête), réponse et durée identiques
 * quelle que soit l'adresse (anti-énumération, DEVELOPMENT_STANDARDS.md §7), pot de miel, consentement, limiteur
 * 1 / 20 s par session et 10 / h par IP en empreinte, confirmation en deux temps, désabonnée qui reste désabonnée sans
 * clic, désinscription en un clic + motif (RFC 8058), CSRF, pixel et clic signés.
 */

/** Timebox qui consigne chaque appel (durée plancher demandée, retour anticipé) ; Sleep::fake() évite l'attente réelle. */
final class RecordingTimebox extends Timebox
{
    /** @var list<array{microseconds: int, early: bool}> */
    public array $calls = [];

    public function call(callable $callback, int $microseconds)
    {
        try {
            return parent::call($callback, $microseconds);
        } finally {
            $this->calls[] = ['microseconds' => $microseconds, 'early' => $this->earlyReturn];
        }
    }
}

beforeEach(function (): void {
    $this->seed([EmailTemplatesSeeder::class, NewsletterTaxonomySeeder::class]);
    Sleep::fake();
    $this->timebox = new RecordingTimebox;
    $this->app->instance(Timebox::class, $this->timebox);
});

/** @param  array<string, mixed>  $extra */
function nlSubscribe(string $email, array $extra = []): Illuminate\Testing\TestResponse
{
    return test()->post('/newsletter', array_merge(['email' => $email, 'consent' => '1', 'back' => '/ressources', 'nl_form' => 'footer'], $extra));
}

/** Jeton du dernier e-mail de confirmation déposé dans la boîte d'envoi pour cette adresse. */
function nlConfirmationToken(string $email): string
{
    $html = (string) MailOutbox::query()->where('to', $email)->where('template_slug', 'newsletter-confirmation')->latest('id')->value('html');

    expect(preg_match('~/newsletter/confirmer/([a-f0-9]{32})~', $html, $m))->toBe(1);

    return $m[1];
}

it('sert la page /newsletter indexable avec le formulaire complet, le pot de miel et les centres d’intérêt', function (): void {
    $html = (string) $this->get('/newsletter')->assertOk()->getContent();

    expect($html)
        ->toContain('<title>Newsletter'."\u{00A0}".': actualités et ressources par e-mail – Xomiton</title>')
        ->toContain('content="index, follow, max-image-preview:large"')
        ->toContain('id="inscription"', 'name="nl_form" value="page"', 'name="website"', 'id="f-consent"', 'value="securite-numerique"')
        ->toContain('action="'.route('newsletter.subscribe').'"')
        // Le pied de page garde sa version courte, sans doublon d'identifiants.
        ->toContain('id="nlEmail"', 'id="nlPageWebsite"')
        ->and(substr_count($html, 'id="f-email"'))->toBe(1);
});

it('inscrit en double opt-in : en attente, e-mail par la boîte d’envoi, GET sans effet, POST confirme', function (): void {
    nlSubscribe('Awa@Exemple.bj', ['interests' => ['sante', 'droits']])
        ->assertStatus(303)->assertRedirect(route('newsletter.thanks'));

    $subscriber = NewsletterSubscriber::query()->whereEmail('awa@exemple.bj')->firstOrFail();
    $outbox = MailOutbox::query()->where('to', 'awa@exemple.bj')->sole();

    expect($subscriber->status)->toBe(SubscriberStatus::Pending)
        ->and($subscriber->interests)->toBe(['sante', 'droits'])
        ->and($subscriber->confirmation_sent_at)->not->toBeNull()
        ->and($subscriber->consent_meta)->not->toContain('127.0.0.1')
        ->and($outbox->template_slug)->toBe('newsletter-confirmation')
        ->and($outbox->sent_at)->toBeNull()
        ->and($outbox->html)->toContain(rtrim((string) config('app.url'), '/').'/newsletter/confirmer/'.$subscriber->token)
        ->and($outbox->html)->toContain('/newsletter/desinscription/'.$subscriber->token);

    $this->get('/newsletter/merci')->assertOk()
        ->assertSee('Un e-mail de confirmation vient de vous être envoyé.')
        ->assertSee('content="noindex, nofollow"', false)
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

    // Un aperçu de lien (GET) ne confirme rien.
    $token = nlConfirmationToken('awa@exemple.bj');
    $this->get('/newsletter/confirmer/'.$token)->assertOk()
        ->assertSee('Confirmer mon inscription')
        ->assertSee('<strong>awa@exemple.bj</strong>', false)
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertSee('content="noindex, nofollow"', false);
    expect($subscriber->fresh()->status)->toBe(SubscriberStatus::Pending);

    $this->post('/newsletter/confirmer/'.$token)->assertStatus(303)->assertRedirect(route('newsletter.confirm', ['token' => $token]));

    expect($subscriber->fresh()->status)->toBe(SubscriberStatus::Active)
        ->and($subscriber->fresh()->confirmed_at)->not->toBeNull()
        ->and(Consent::query()->where('email', 'awa@exemple.bj')->where('kind', ConsentKind::Newsletter->value)->where('granted', true)->whereNull('ip')->count())->toBe(1);

    $this->get('/newsletter/confirmer/'.$token)->assertOk()->assertSee('Inscription confirmée');

    // Journal d'audit sans IP ni navigateur (personne non connectée).
    expect(AuditLog::query()->where('action', 'newsletter.subscription_confirmed')->sole())
        ->ip->toBeNull()->user_agent->toBeNull()->subject_id->toBe($subscriber->getKey());
});

it('répond à l’identique, dans la même durée plancher, pour une adresse inconnue, en attente, abonnée, désabonnée, en rebond ou un robot', function (): void {
    $active = NewsletterSubscriber::factory()->withInterests(['sante'])->create(['email' => 'active@exemple.bj']);
    NewsletterSubscriber::factory()->pending()->create(['email' => 'pending@exemple.bj', 'confirmation_sent_at' => now()]);
    $gone = NewsletterSubscriber::factory()->unsubscribed()->create(['email' => 'partie@exemple.bj', 'confirmation_sent_at' => now()->subMonths(2), 'unsubscribed_at' => now()->subDays(3)]);
    NewsletterSubscriber::factory()->bounced()->create(['email' => 'bounce@exemple.bj']);

    $snapshots = [];
    $emails = ['inconnue@exemple.bj', 'active@exemple.bj', 'pending@exemple.bj', 'partie@exemple.bj', 'bounce@exemple.bj', 'robot@exemple.bj'];

    foreach ($emails as $email) {
        $response = nlSubscribe($email, ['interests' => ['droits']] + ($email === 'robot@exemple.bj' ? ['website' => 'https://spam.example'] : []));
        $snapshots[$email] = [$response->getStatusCode(), $response->headers->get('Location'), $response->getContent(), session()->has('errors'), session()->has('newsletter_flash')];
        $this->travel(21)->seconds();
    }

    expect(array_unique(array_map('serialize', $snapshots)))->toHaveCount(1)
        ->and($snapshots['inconnue@exemple.bj'][0])->toBe(303)
        ->and($snapshots['inconnue@exemple.bj'][1])->toBe(route('newsletter.thanks'));

    // Chaque demande passe par la même Timebox, sans retour anticipé, à la durée plancher configurée.
    expect($this->timebox->calls)->toHaveCount(6)
        ->and(array_unique(array_column($this->timebox->calls, 'microseconds')))->toBe([(int) config('xomiton.newsletter.response_floor_ms') * 1000])
        ->and(array_filter(array_column($this->timebox->calls, 'early')))->toBe([]);
    Sleep::assertSleptTimes(6);

    // E-mail pour l'adresse inconnue et la désabonnée (qui reste désabonnée sans clic) ; l'abonnée garde ses centres
    // d'intérêt, le robot n'est pas enregistré.
    expect(MailOutbox::query()->orderBy('id')->pluck('to')->all())->toBe(['inconnue@exemple.bj', 'partie@exemple.bj'])
        ->and($active->fresh()->status)->toBe(SubscriberStatus::Active)
        ->and($active->fresh()->interestModels()->pluck('slug')->all())->toBe(['sante'])
        ->and($gone->fresh()->status)->toBe(SubscriberStatus::Unsubscribed)
        ->and($gone->fresh()->unsubscribed_at->toDateTimeString())->toBe($gone->unsubscribed_at->toDateTimeString())
        ->and(NewsletterSubscriber::query()->whereEmail('robot@exemple.bj')->exists())->toBeFalse();
});

it('ignore en silence le pot de miel rempli', function (): void {
    nlSubscribe('bot@exemple.bj', ['website' => 'x'])->assertStatus(303)->assertRedirect(route('newsletter.thanks'));

    expect(NewsletterSubscriber::query()->count())->toBe(0)->and(MailOutbox::query()->count())->toBe(0);
});

it('exige le consentement et une adresse valide, sans consommer la tentative du limiteur', function (): void {
    $this->post('/newsletter', ['email' => 'awa@exemple.bj', 'back' => '/ressources?page=2', 'nl_form' => 'footer'])
        ->assertStatus(302)
        ->assertRedirect(url('/ressources?page=2').'#newsletter')
        ->assertSessionHasErrorsIn('newsletter', ['consent' => __('newsletter.public.consent_required')]);

    $this->post('/newsletter', ['email' => 'pas-une-adresse', 'consent' => '1', 'nl_form' => 'page'])
        ->assertRedirect(route('newsletter.show').'#inscription')
        ->assertSessionHasErrorsIn('newsletter', ['email' => __('newsletter.public.email_invalid')]);

    $this->post('/newsletter', ['email' => 'awa@exemple.bj', 'consent' => '1', 'interests' => ['inconnu']])
        ->assertSessionHasErrorsIn('newsletter', ['interests.0']);

    expect(NewsletterSubscriber::query()->count())->toBe(0);

    // Le formulaire corrigé passe tout de suite : les refus n'ont pas compté.
    nlSubscribe('awa@exemple.bj')->assertRedirect(route('newsletter.thanks'));
    expect(NewsletterSubscriber::query()->count())->toBe(1);
});

it('affiche les erreurs dans le seul formulaire envoyé, avec l’adresse saisie', function (): void {
    $this->from('/newsletter')->post('/newsletter', ['email' => 'awa@exemple.bj', 'nl_form' => 'page', 'back' => '/newsletter']);

    $html = (string) $this->get('/newsletter')->getContent();

    expect($html)->toContain('id="f-consent-error"', 'aria-invalid="true"', 'value="awa@exemple.bj"')
        ->not->toContain('id="nlErrors"');
});

it('ne remplit jamais un autre champ `email` avec l’adresse saisie dans le pied de page, et focalise son erreur', function (): void {
    $this->post('/newsletter', ['email' => 'awa@exemple.bj', 'nl_form' => 'footer', 'back' => '/newsletter'])
        ->assertRedirect(route('newsletter.show').'#newsletter');

    expect(session()->getOldInput('email'))->toBeNull()
        ->and(session()->getOldInput('newsletter.email'))->toBe('awa@exemple.bj');

    $html = (string) $this->get('/newsletter')->getContent();

    expect($html)->toContain('id="nlErrors" tabindex="-1" x-data x-init="$el.focus()"', 'id="nlEmail" name="email" value="awa@exemple.bj"')
        ->and(substr_count($html, 'value="awa@exemple.bj"'))->toBe(1)
        ->and($html)->not->toContain('id="f-consent-error"');
});

it('ne suit jamais une adresse de retour externe', function (): void {
    $this->post('/newsletter', ['email' => 'x', 'back' => 'https://evil.example/piege'])->assertRedirect(route('newsletter.show').'#newsletter');
    $this->post('/newsletter', ['email' => 'x', 'back' => '//evil.example/piege'])->assertRedirect(route('newsletter.show').'#newsletter');
});

it('n’accepte qu’une inscription toutes les 20 secondes, avec un message et une clé sans IP en clair', function (): void {
    nlSubscribe('un@exemple.bj')->assertRedirect(route('newsletter.thanks'));

    nlSubscribe('deux@exemple.bj')
        ->assertStatus(303)
        ->assertRedirect(url('/ressources').'#newsletter')
        ->assertHeader('Retry-After')
        ->assertSessionHas('newsletter_flash', fn (array $flash): bool => $flash['ok'] === false && $flash['message'] === __('newsletter.public.too_fast'));

    $this->postJson('/newsletter', ['email' => 'trois@exemple.bj', 'consent' => '1'])->assertStatus(429)->assertHeader('Retry-After');

    expect(NewsletterSubscriber::query()->pluck('email')->all())->toBe(['un@exemple.bj']);

    $this->travel(21)->seconds();
    nlSubscribe('deux@exemple.bj')->assertRedirect(route('newsletter.thanks'));

    [$session, $ip] = RateLimiter::limiter('newsletter')(Request::create('/newsletter', 'POST', server: ['REMOTE_ADDR' => '41.79.12.34']));
    expect($session->key)->not->toContain('41.79.12.34')->and($session->maxAttempts)->toBe(1)->and($session->decaySeconds)->toBe(20)
        ->and($ip->key)->not->toContain('41.79.12.34')->and($ip->maxAttempts)->toBe(10)->and($ip->decaySeconds)->toBe(3600);
});

it('affiche le refus « trop rapide » dans le formulaire envoyé, focalisé, avec l’adresse saisie', function (): void {
    nlSubscribe('un@exemple.bj', ['back' => '/newsletter']);
    nlSubscribe('deux@exemple.bj', ['back' => '/newsletter'])->assertRedirect(route('newsletter.show').'#newsletter');

    $html = (string) $this->get('/newsletter')->getContent();

    expect($html)->toContain('id="nlFlash" tabindex="-1" x-data x-init="$el.focus()">'.e(__('newsletter.public.too_fast')).'</div>')
        ->toContain('id="nlEmail" name="email" value="deux@exemple.bj"')
        ->and(substr_count($html, 'value="deux@exemple.bj"'))->toBe(1)
        ->and(substr_count($html, e(__('newsletter.public.too_fast'))))->toBe(1);
});

it('plafonne les inscriptions à 10 par heure et par IP, même avec une session neuve à chaque envoi', function (): void {
    foreach (range(1, 10) as $i) {
        $this->withCookie(config('session.cookie'), Str::random(40));
        nlSubscribe("n{$i}@exemple.bj")->assertRedirect(route('newsletter.thanks'));
    }

    $this->withCookie(config('session.cookie'), Str::random(40));
    $refused = nlSubscribe('n11@exemple.bj')
        ->assertStatus(303)
        ->assertRedirect(url('/ressources').'#newsletter')
        ->assertSessionHas('newsletter_flash', fn (array $flash): bool => $flash['message'] === __('newsletter.public.too_many', ['minutes' => 60]));

    expect((int) $refused->headers->get('Retry-After'))->toBeGreaterThan(60)
        ->and(NewsletterSubscriber::query()->count())->toBe(10)
        ->and(MailOutbox::query()->count())->toBe(10);

    // Une autre IP n'est pas concernée ; l'heure écoulée, la même IP recommence.
    $this->withCookie(config('session.cookie'), Str::random(40))
        ->post('/newsletter', ['email' => 'ailleurs@exemple.bj', 'consent' => '1'], ['REMOTE_ADDR' => '203.0.113.42'])
        ->assertRedirect(route('newsletter.thanks'));

    $this->travel(61)->minutes();
    $this->withCookie(config('session.cookie'), Str::random(40));
    nlSubscribe('n11@exemple.bj')->assertRedirect(route('newsletter.thanks'));
});

it('renvoie la confirmation d’une adresse en attente au plus toutes les 10 minutes, avec le même lien tant qu’il vaut', function (): void {
    nlSubscribe('awa@exemple.bj');
    $first = nlConfirmationToken('awa@exemple.bj');

    $this->travel(5)->minutes();
    nlSubscribe('awa@exemple.bj');
    expect(MailOutbox::query()->where('to', 'awa@exemple.bj')->count())->toBe(1);

    // Un tiers qui redemande l'inscription n'invalide pas le lien déjà reçu.
    $this->travel(6)->minutes();
    nlSubscribe('awa@exemple.bj');
    expect(MailOutbox::query()->where('to', 'awa@exemple.bj')->count())->toBe(2)->and(nlConfirmationToken('awa@exemple.bj'))->toBe($first);
    $this->get('/newsletter/confirmer/'.$first)->assertOk()->assertSee('Confirmer mon inscription');

    // Lien expiré (48 h) : le renvoi change de jeton.
    $this->travel(49)->hours();
    nlSubscribe('awa@exemple.bj');
    $renewed = nlConfirmationToken('awa@exemple.bj');
    expect($renewed)->not->toBe($first)->and(MailOutbox::query()->where('to', 'awa@exemple.bj')->count())->toBe(3);
    $this->get('/newsletter/confirmer/'.$first)->assertNotFound();
    $this->get('/newsletter/confirmer/'.$renewed)->assertOk();
});

it('garde le refus d’une adresse désabonnée tant que la personne n’a pas confirmé le nouvel e-mail', function (): void {
    $gone = NewsletterSubscriber::factory()->unsubscribed('Trop d’e-mails')->create([
        'email' => 'partie@exemple.bj',
        'confirmed_at' => now()->subMonths(3),
        'confirmation_sent_at' => now()->subMonths(3),
        'unsubscribed_at' => now()->subDays(2),
    ]);

    // L'ancien lien de confirmation (même jeton) ne réactive rien.
    $this->get('/newsletter/confirmer/'.$gone->token)->assertNotFound();
    $this->post('/newsletter/confirmer/'.$gone->token)->assertNotFound();

    nlSubscribe('partie@exemple.bj', ['interests' => ['sante']]);
    $this->travel(30)->seconds();
    nlSubscribe('partie@exemple.bj');

    $waiting = $gone->fresh();
    expect($waiting->status)->toBe(SubscriberStatus::Unsubscribed)
        ->and($waiting->unsubscribe_reason)->toBe('Trop d’e-mails')
        ->and($waiting->unsubscribed_at->toDateTimeString())->toBe($gone->unsubscribed_at->toDateTimeString())
        ->and($waiting->token)->toBe($gone->token)
        ->and(MailOutbox::query()->where('to', 'partie@exemple.bj')->count())->toBe(1)
        ->and(nlConfirmationToken('partie@exemple.bj'))->toBe($gone->token);
    $this->get('/newsletter/desinscription/'.$gone->token)->assertOk()->assertSee('Vous êtes désinscrit·e');

    // La personne confirme : actif, date de consentement du jour, une seule ligne `consents` malgré un second envoi.
    $this->travel(1)->minutes();
    $this->get('/newsletter/confirmer/'.$gone->token)->assertOk()->assertSee('Confirmer mon inscription');
    $this->post('/newsletter/confirmer/'.$gone->token)->assertStatus(303);
    $this->post('/newsletter/confirmer/'.$gone->token)->assertStatus(303);

    $active = $gone->fresh();
    expect($active->status)->toBe(SubscriberStatus::Active)
        ->and($active->unsubscribed_at)->toBeNull()
        ->and($active->unsubscribe_reason)->toBeNull()
        ->and($active->confirmed_at->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and($active->interestModels()->pluck('slug')->all())->toBe(['sante'])
        ->and(Consent::query()->where('email', 'partie@exemple.bj')->where('granted', true)->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'newsletter.subscription_confirmed')->count())->toBe(1);
});

it('refuse un lien de confirmation inconnu (404), expiré après 48 h (410) ou visant une adresse désabonnée', function (): void {
    $this->get('/newsletter/confirmer/'.str_repeat('a', 32))->assertNotFound()->assertSee('Lien invalide ou expiré');
    $this->get('/newsletter/confirmer/pas-un-jeton')->assertNotFound();

    $pending = NewsletterSubscriber::factory()->pending()->create(['confirmation_sent_at' => now()->subHours(49)]);
    $this->get('/newsletter/confirmer/'.$pending->token)->assertStatus(410)->assertSee('a expiré (48'."\u{00A0}".'h)', false);
    $this->post('/newsletter/confirmer/'.$pending->token)->assertStatus(410);
    expect($pending->fresh()->status)->toBe(SubscriberStatus::Pending);

    // En attente sans e-mail de confirmation parti (import) : rien à confirmer par ce lien.
    $imported = NewsletterSubscriber::factory()->pending()->create(['confirmation_sent_at' => null]);
    $this->get('/newsletter/confirmer/'.$imported->token)->assertStatus(410);
    $this->post('/newsletter/confirmer/'.$imported->token)->assertStatus(410);
    expect($imported->fresh()->status)->toBe(SubscriberStatus::Pending);

    $gone = NewsletterSubscriber::factory()->unsubscribed()->create(['confirmation_sent_at' => now()->subMonths(2), 'unsubscribed_at' => now()->subDays(3)]);
    $this->post('/newsletter/confirmer/'.$gone->token)->assertNotFound();
    expect($gone->fresh()->status)->toBe(SubscriberStatus::Unsubscribed);

    // Désabonnée qui a redemandé l'inscription il y a plus de 48 h : lien expiré, toujours désabonnée.
    $asked = NewsletterSubscriber::factory()->unsubscribed()->create(['confirmation_sent_at' => now()->subHours(49), 'unsubscribed_at' => now()->subDays(3)]);
    $this->post('/newsletter/confirmer/'.$asked->token)->assertStatus(410);
    expect($asked->fresh()->status)->toBe(SubscriberStatus::Unsubscribed);
});

it('désinscrit en un clic avec un motif facultatif et marque le destinataire de la campagne', function (): void {
    $subscriber = NewsletterSubscriber::factory()->create(['email' => 'awa@exemple.bj']);
    $recipient = NewsletterCampaignRecipient::factory()->forSubscriber($subscriber)->sent()->create();
    $url = app(NewsletterLinks::class)->unsubscribeUrl($subscriber, $recipient->campaign_id);

    expect($url)->toBe(rtrim((string) config('app.url'), '/').'/newsletter/desinscription/'.$subscriber->token.'?c='.$recipient->campaign_id);

    $this->get($url)->assertOk()
        ->assertSee('<strong>awa@exemple.bj</strong>', false)
        ->assertSee('Trop d’e-mails')
        ->assertSee('name="c" value="'.$recipient->campaign_id.'"', false)
        ->assertSee('content="noindex, nofollow"', false);

    $this->post('/newsletter/desinscription/'.$subscriber->token, ['reason' => 'too_many', 'c' => $recipient->campaign_id])
        ->assertStatus(303)->assertRedirect(route('newsletter.unsubscribe', ['token' => $subscriber->token]));

    expect($subscriber->fresh()->status)->toBe(SubscriberStatus::Unsubscribed)
        ->and($subscriber->fresh()->unsubscribe_reason)->toBe('Trop d’e-mails')
        ->and($recipient->fresh()->unsubscribed)->toBeTrue()
        ->and(Consent::query()->where('email', 'awa@exemple.bj')->where('granted', false)->count())->toBe(1);

    $this->get('/newsletter/desinscription/'.$subscriber->token)->assertOk()->assertSee('Vous êtes désinscrit·e');
    $this->get('/newsletter/desinscription/'.str_repeat('b', 32))->assertNotFound();
});

it('accepte la désinscription « one-click » des messageries (RFC 8058) sans jeton CSRF ni redirection', function (): void {
    $subscriber = NewsletterSubscriber::factory()->create();

    expect(Route::getRoutes()->getByName('newsletter.unsubscribe.store')->excludedMiddleware())->toContain(ValidateCsrfToken::class);

    $this->post('/newsletter/desinscription/'.$subscriber->token, ['List-Unsubscribe' => 'One-Click'])
        ->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8')->assertSee('Vous êtes désinscrit·e');

    expect($subscriber->fresh()->status)->toBe(SubscriberStatus::Unsubscribed)
        ->and($subscriber->fresh()->unsubscribe_reason)->toBeNull();

    // Jeton inconnu : 404 en texte brut, sans redirection.
    $this->post('/newsletter/desinscription/'.str_repeat('d', 32), ['List-Unsubscribe' => 'One-Click'])
        ->assertNotFound()->assertHeader('Content-Type', 'text/plain; charset=UTF-8')->assertHeaderMissing('Location');

    $headers = app(NewsletterLinks::class)->unsubscribeHeaders($subscriber, 7);
    expect($headers['List-Unsubscribe'])->toBe('<'.app(NewsletterLinks::class)->unsubscribeUrl($subscriber, 7).'>')
        ->and($headers['List-Unsubscribe-Post'])->toBe('List-Unsubscribe=One-Click');
});

it('exige le jeton CSRF pour l’inscription et la confirmation, jamais pour la désinscription « one-click »', function (): void {
    // ValidateCsrfToken s'efface pendant les tests : on rétablit ici la vérification réelle.
    $this->app->bind(ValidateCsrfToken::class, fn ($app): ValidateCsrfToken => new class($app, $app['encrypter']) extends ValidateCsrfToken
    {
        protected function runningUnitTests(): bool
        {
            return false;
        }
    });

    $subscriber = NewsletterSubscriber::factory()->create();
    $pending = NewsletterSubscriber::factory()->pending()->create(['confirmation_sent_at' => now()]);

    $this->post('/newsletter', ['email' => 'awa@exemple.bj', 'consent' => '1'])->assertStatus(419);
    $this->post('/newsletter/confirmer/'.$pending->token)->assertStatus(419);
    $this->post('/newsletter/desinscription/'.$subscriber->token, ['List-Unsubscribe' => 'One-Click'])->assertOk();

    expect(NewsletterSubscriber::query()->whereEmail('awa@exemple.bj')->exists())->toBeFalse()
        ->and($pending->fresh()->status)->toBe(SubscriberStatus::Pending)
        ->and($subscriber->fresh()->status)->toBe(SubscriberStatus::Unsubscribed);
});

it('suit un clic signé puis redirige ; un lien altéré ou mal signé répond 403 sans redirection', function (): void {
    $recipient = NewsletterCampaignRecipient::factory()->sent()->create();
    $target = 'https://xomiton.org/ressources/sante-sexuelle?utm_source=newsletter';
    $url = app(NewsletterLinks::class)->clickUrl($recipient, $target);

    $this->get($url)->assertStatus(302)->assertRedirect($target)->assertHeader('Cache-Control', 'no-store, private')->assertCookieMissing(config('session.cookie'));

    expect($recipient->fresh()->opened_at)->not->toBeNull()
        ->and($recipient->clicks()->sole()->url)->toBe($target);

    $this->get($url)->assertRedirect($target);
    expect($recipient->clicks()->sole()->clicks_count)->toBe(2);

    $tampered = str_replace(rawurlencode('sante-sexuelle'), rawurlencode('autre'), $url);
    expect($tampered)->not->toBe($url);
    $this->get($tampered)->assertForbidden()->assertSee('Ce lien n’est pas valide.')->assertHeaderMissing('Location');

    $wrongSignature = preg_replace('~/clic/(\d+)/[a-f0-9]{32}~', '/clic/$1/'.str_repeat('0', 32), $url);
    $this->get((string) $wrongSignature)->assertForbidden();

    $this->get('/newsletter/clic/'.$recipient->getKey().'/'.str_repeat('0', 32).'?url='.rawurlencode('https://evil.example'))->assertForbidden();
    $this->get('/newsletter/clic/'.$recipient->getKey().'/'.str_repeat('0', 32))->assertForbidden();

    // Une cible non web n'est jamais suivie, même signée.
    $javascript = app(NewsletterLinks::class)->clickUrl($recipient, 'javascript:alert(1)');
    $this->get($javascript)->assertForbidden();
});

it('sert le pixel d’ouverture dans tous les cas et n’enregistre que l’ouverture signée', function (): void {
    $recipient = NewsletterCampaignRecipient::factory()->sent()->create();
    $pixel = app(NewsletterLinks::class)->openPixelUrl($recipient);

    $this->get('/newsletter/ouverture/'.$recipient->getKey().'/'.str_repeat('f', 32))
        ->assertOk()->assertHeader('Content-Type', 'image/gif');
    expect($recipient->fresh()->opened_at)->toBeNull();

    $response = $this->get($pixel)->assertOk()->assertHeader('Content-Type', 'image/gif')->assertCookieMissing(config('session.cookie'));
    expect(strlen((string) $response->getContent()))->toBe(42)
        ->and($recipient->fresh()->opened_at)->not->toBeNull()
        ->and($recipient->subscriber->fresh()->last_opened_at)->not->toBeNull();
});

it('construit les liens sur app.url, jamais sur l’hôte de la requête', function (): void {
    $this->post('/newsletter', ['email' => 'awa@exemple.bj', 'consent' => '1'], ['HTTP_HOST' => 'evil.example']);

    $html = (string) MailOutbox::query()->where('to', 'awa@exemple.bj')->value('html');

    expect($html)->not->toContain('evil.example')->toContain(rtrim((string) config('app.url'), '/').'/newsletter/confirmer/');
});

it('relie l’abonnement au compte connecté qui inscrit sa propre adresse', function (): void {
    $user = User::factory()->create(['email' => 'membre@exemple.bj']);

    $this->actingAs($user)->post('/newsletter', ['email' => 'membre@exemple.bj', 'consent' => '1'])->assertRedirect(route('newsletter.thanks'));
    $this->travel(21)->seconds();
    $this->actingAs($user)->post('/newsletter', ['email' => 'autre@exemple.bj', 'consent' => '1']);

    expect(NewsletterSubscriber::query()->whereEmail('membre@exemple.bj')->value('user_id'))->toBe($user->getKey())
        ->and(NewsletterSubscriber::query()->whereEmail('autre@exemple.bj')->value('user_id'))->toBeNull();
});

it('redirige les anciennes adresses newsletter.php du prototype sans redirection ouverte', function (): void {
    $token = str_repeat('c', 32);

    $this->get('/newsletter.php?a=confirm&t='.$token)->assertStatus(301)->assertRedirect(route('newsletter.confirm', ['token' => $token]));
    $this->get('/newsletter.php?a=unsub&t='.$token.'&c=4')->assertStatus(301)->assertRedirect(route('newsletter.unsubscribe', ['token' => $token, 'c' => 4]));
    $this->get('/newsletter.php?a=click&c=1&s=1&u='.rawurlencode('https://evil.example').'&h=abc')->assertStatus(301)->assertRedirect(route('newsletter.show'));
});
