<?php

declare(strict_types=1);

use App\Application\Account\PersonalDataExports;
use App\Enums\ConsentKind;
use App\Enums\SubscriberSource;
use App\Enums\SubscriberStatus;
use App\Models\AuditLog;
use App\Models\Consent;
use App\Models\NewsletterSubscriber;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\EmailTemplatesSeeder;
use Database\Seeders\NewsletterTaxonomySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/*
 * P6-06 (lot api-me) : me/newsletter (abonnement direct quand l'e-mail du compte est confirmé, double opt-in sinon,
 * rebond jamais réactivé, centres d'intérêt) et me/export (202, fichier construit en file, lien signé par e-mail,
 * téléchargement connecté au même compte, purge à l'expiration).
 */

beforeEach(function (): void {
    $this->seed([RolesAndPermissionsSeeder::class, EmailTemplatesSeeder::class, SettingsSeeder::class, NewsletterTaxonomySeeder::class]);
    Mail::fake();
    config(['mail.default' => 'array', 'xomiton.newsletter.response_floor_ms' => 0]);
    Storage::fake('local');

    $this->user = User::factory()->withProfile(['gender_identity' => 'femme_trans'])->create(['pseudo' => 'Awa_S', 'email' => 'awa@exemple.bj', 'name' => 'Awa Sossou']);
    $this->user->assignRole('membre');
    $this->token = $this->user->createToken('Pixel 7')->plainTextToken;
});

// --- me/newsletter ---------------------------------------------------------------------------------------------------

it('abonne directement un compte à l’e-mail confirmé, avec consentement tracé et centres d’intérêt', function (): void {
    $this->withToken($this->token)->getJson('/api/v1/me/newsletter')
        ->assertOk()
        ->assertJsonPath('data.subscribed', false)
        ->assertJsonPath('data.status', null)
        ->assertJsonCount(6, 'data.available_interests');

    $this->withToken($this->token)->patchJson('/api/v1/me/newsletter', ['subscribed' => true, 'interests' => ['sante', 'droits']])
        ->assertOk()
        ->assertJsonPath('data.subscribed', true)
        ->assertJsonPath('data.status', SubscriberStatus::Active->value)
        ->assertJsonPath('data.interests', ['sante', 'droits']);

    $subscriber = NewsletterSubscriber::query()->sole();

    expect($subscriber->user_id)->toBe($this->user->id)
        ->and($subscriber->source)->toBe(SubscriberSource::App)
        ->and($subscriber->consent_meta)->toContain('app')->not->toContain('127.0.0.1')
        ->and(Consent::query()->where('user_id', $this->user->id)->where('kind', ConsentKind::Newsletter)->where('granted', true)->exists())->toBeTrue()
        ->and($subscriber->interestModels()->count())->toBe(2);

    $this->withToken($this->token)->patchJson('/api/v1/me/newsletter', ['subscribed' => false])
        ->assertOk()->assertJsonPath('data.subscribed', false)->assertJsonPath('data.status', SubscriberStatus::Unsubscribed->value);

    expect(Consent::query()->where('user_id', $this->user->id)->where('kind', ConsentKind::Newsletter)->where('granted', false)->exists())->toBeTrue();

    // Désabonnée, la personne elle-même peut se réabonner depuis son compte.
    $this->withToken($this->token)->patchJson('/api/v1/me/newsletter', ['subscribed' => true])->assertOk()->assertJsonPath('data.subscribed', true);
});

it('passe par le double opt-in quand l’e-mail du compte n’est pas confirmé', function (): void {
    $unverified = User::factory()->unverified()->withProfile()->create(['email' => 'nouvelle@exemple.bj']);
    $token = $unverified->createToken('Moto G')->plainTextToken;

    $this->withToken($token)->patchJson('/api/v1/me/newsletter', ['subscribed' => true, 'interests' => ['sante']])
        ->assertOk()
        ->assertJsonPath('data.subscribed', false)
        ->assertJsonPath('data.pending_confirmation', true)
        ->assertJsonPath('data.interests', ['sante']);

    expect(DB::table('mail_outbox')->where('to', 'nouvelle@exemple.bj')->where('template_slug', 'newsletter-confirmation')->exists())->toBeTrue()
        ->and(NewsletterSubscriber::query()->sole()->user_id)->toBe($unverified->id);
});

it('refuse de réactiver une adresse en rebond et de choisir des centres d’intérêt sans abonnement (422)', function (): void {
    $this->withToken($this->token)->patchJson('/api/v1/me/newsletter', ['interests' => ['sante']])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'newsletter_not_subscribed')
        ->assertJsonValidationErrors(['interests' => __('account.newsletter.not_subscribed')]);

    NewsletterSubscriber::factory()->create(['email' => 'awa@exemple.bj', 'status' => SubscriberStatus::Bounced]);

    $this->withToken($this->token)->patchJson('/api/v1/me/newsletter', ['subscribed' => true])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'newsletter_bounced');

    $this->withToken($this->token)->patchJson('/api/v1/me/newsletter', ['interests' => ['inconnu']])
        ->assertUnprocessable()->assertJsonValidationErrors('interests.0');

    expect(NewsletterSubscriber::query()->sole()->status)->toBe(SubscriberStatus::Bounced);
});

// --- me/export -------------------------------------------------------------------------------------------------------

it('prépare l’export RGPD (202), l’envoie par lien signé et le sert seulement au même compte connecté', function (): void {
    Post::factory()->create(['user_id' => $this->user->id, 'body' => 'Mon témoignage', 'is_anonymous' => true]);

    $this->withToken($this->token)->postJson('/api/v1/me/export')
        ->assertAccepted()
        ->assertJsonPath('data.status', 'preparing')
        ->assertJsonPath('data.link_valid_hours', 48)
        ->assertJsonPath('data.message', __('profile.settings.data.preparing', ['hours' => 48]));

    $files = Storage::disk('local')->allFiles('exports/personal/'.$this->user->id);
    expect($files)->toHaveCount(1)->and($files[0])->toEndWith('.json.enc');

    // Chiffré sur le disque : aucune valeur sensible ni identifiante en clair (sauvegardes de l'hébergement).
    // Valeurs choisies hors de l'alphabet base64 (« _ », accents) : un hasard du texte chiffré ne peut pas les produire.
    $raw = (string) Storage::disk('local')->get($files[0]);
    expect($raw)->not->toContain('femme_trans')->not->toContain('Awa_S')->not->toContain('témoignage')->not->toContain('{');

    $export = json_decode(app(PersonalDataExports::class)->read($files[0]), true);
    expect($export['account']['pseudo'])->toBe('Awa_S')
        ->and($export['account'])->not->toHaveKey('password')
        ->and($export['profile']['gender_identity'])->toBe('femme_trans')
        ->and($export['posts'][0]['body'])->toBe('Mon témoignage')
        ->and($export['posts'][0]['anonymous'])->toBeTrue();

    $mail = DB::table('mail_outbox')->where('to', 'awa@exemple.bj')->where('template_slug', 'export-donnees')->first();
    expect($mail)->not->toBeNull();
    preg_match('~href="([^"]+mes-donnees/export/[^"]+)"~', (string) $mail->html, $match);
    $link = html_entity_decode($match[1] ?? '');
    expect($link)->toContain('signature=');

    // Le lien s'ouvre dans un navigateur : plus de jeton Bearer, garde web par défaut (auth:sanctum l'avait changé).
    $this->withoutToken();
    app('auth')->forgetGuards();
    app('auth')->shouldUse('web');

    // Sans connexion : redirection vers la connexion ; autre compte : 404 ; lien altéré : 403 ; bon compte : le fichier.
    $this->get($link)->assertRedirect(route('connexion'));
    $this->actingAs(User::factory()->create())->get($link)->assertNotFound();
    $expired = __('account.export.link_invalid.title');
    $this->actingAs($this->user)->get($link.'x')->assertForbidden()
        ->assertSee($expired)
        ->assertSee(__('account.export.link_invalid.action'))
        ->assertSee(route('parametres'), false);

    $download = $this->actingAs($this->user)->get($link)->assertOk();
    expect(json_decode((string) $download->streamedContent(), true)['profile']['gender_identity'])->toBe('femme_trans')
        ->and($download->headers->get('Content-Disposition'))->toContain('xomiton-mes-donnees-')
        ->and($download->headers->get('Cache-Control'))->toContain('no-store')
        ->and(AuditLog::query()->whereIn('action', ['account.export_requested', 'account.export_ready', 'account.export_downloaded'])->count())->toBe(3);

    // Expiré : le lien ne vaut plus et le fichier est purgé.
    $this->travel(49)->hours();
    $this->flushSession(); // 49 h plus tard la session aurait expiré (EnforceIdleTimeout) : on teste le lien, pas l'inactivité.
    $this->actingAs($this->user)->get($link)->assertForbidden()->assertSee($expired)->assertHeader('Cache-Control', 'no-store, private');
    touch(Storage::disk('local')->path($files[0]), now()->subHours(49)->getTimestamp());
    $this->artisan('accounts:purge-exports')->assertSuccessful();

    expect(Storage::disk('local')->allFiles('exports/personal'))->toBe([]);
});

it('remplace l’export précédent et efface les exports d’un compte supprimé', function (): void {
    $exports = app(PersonalDataExports::class);
    $exports->build($this->user);
    $exports->build($this->user);

    expect(Storage::disk('local')->allFiles('exports/personal/'.$this->user->id))->toHaveCount(1);

    $this->withToken($this->token)->deleteJson('/api/v1/me', ['password' => 'password'])->assertOk();

    expect(Storage::disk('local')->allFiles('exports/personal/'.$this->user->id))->toBe([]);
});
