<?php

declare(strict_types=1);

use App\Application\Shared\MediaUploader;
use App\Enums\AlertSource;
use App\Enums\AlertStatus;
use App\Enums\AlertType;
use App\Enums\ContactChannel;
use App\Models\Alert;
use App\Models\AlertAttachment;
use App\Models\AlertStatusChange;
use App\Models\DirectoryContact;
use App\Support\KeyedHash;
use App\Support\Settings;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\AlertFixtures;

/*
 * P5-04 : page publique /signaler (gabarit sobre, six étapes Q11 a, tu Q47), envoi web (POST + CSRF, StoreReportRequest
 * et SubmitReport partagés avec l'API), confirmation avec code de suivi remis une seule fois, suivi par code /suivi,
 * limiteurs `report` et `tracking`, aucune IP enregistrée (sessions d'invité·e comprises, contenu de session chiffré),
 * rejeu d'une même clé avec un contenu modifié, EXIF retiré et preuve servie par lien signé, /signaler dans le sitemap.
 */

beforeEach(function (): void {
    $this->seed([ReferenceDataSeeder::class, RolesAndPermissionsSeeder::class]);
    Notification::fake();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function webReport(array $overrides = []): array
{
    return [
        'idempotency_key' => (string) Str::uuid(),
        'type' => 'report',
        'category' => 'physique',
        'description' => AlertFixtures::DESCRIPTION,
        'zone_id' => AlertFixtures::zone('Littoral')->id,
        'anonymous' => '1',
        'contactable' => '0',
        ...$overrides,
    ];
}

it('affiche le formulaire en six étapes dans le gabarit sobre, sans JavaScript requis', function (): void {
    $response = $this->get('/signaler')->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('Permissions-Policy', 'geolocation=(self), camera=(), microphone=()');
    $html = (string) $response->getContent();

    $response->assertSee('<body class="xm-plain', false)
        ->assertSee('<title>'.e(__('seo.pages.signaler.title')).'</title>', false)
        ->assertSee('class="alert alert-info', false)
        ->assertSee(__('alerts.report.privacy.title'))
        ->assertSee('method="POST" action="'.route('signaler.store').'" enctype="multipart/form-data"', false)
        ->assertSee('data-outbox="signalement"', false)
        ->assertSee('name="_token"', false)
        ->assertSee(__('alerts.report.step', ['current' => 1, 'total' => 6]))
        ->assertSee(__('alerts.report.step', ['current' => 6, 'total' => 6]))
        ->assertSee('aria-current="step"', false)
        ->assertSee(__('buttons.help.send_report'))
        ->assertSee('x-data="xmReport(', false)
        ->assertSee('name="needs[]"', false)
        ->assertSee('<optgroup label="Littoral" data-zone="'.AlertFixtures::zone('Littoral')->id.'">', false)
        ->assertSee('value="sms"', false)
        ->assertSee('value="whatsapp"', false)
        ->assertSee('accept="image/jpeg,', false)
        ->assertSee(__('alerts.report.fields.evidence_warning'))
        ->assertDontSee('SIG-');

    expect(substr_count($html, 'class="xm-report__step'))->toBe(6)
        ->and(substr_count($html, 'aria-current="step"'))->toBe(1)
        ->and(preg_match('/name="idempotency_key" value="([0-9a-f-]{36})"/', $html))->toBe(1)
        // Bouton d'envoi bleu (signaler-et-sos.md : pas de rouge, réservé à l'accès SOS).
        ->and($html)->toMatch('/<button type="submit" class="btn btn-primary btn-lg/')
        ->and($html)->not->toContain('btn-danger btn-lg');
});

it('présélectionne « Exprimer un besoin » avec ?tab=besoin', function (): void {
    $this->get('/signaler?tab=besoin')->assertOk()
        ->assertSee('id="f-type-1" value="need"'."\n", false);

    expect((string) $this->get('/signaler?tab=besoin')->getContent())->toMatch('/value="need"\s+aria-describedby="f-type-1-help" required checked/');
});

it('refuse un récit trop court (422 en JSON, retour à l’étape « Les faits » en HTML)', function (): void {
    $short = webReport(['description' => 'Trop court']);

    $this->postJson('/signaler', $short)->assertUnprocessable()
        ->assertJsonValidationErrors(['description' => __('alerts.report.fields.description_min', ['min' => 20])]);

    $this->from('/signaler')->post('/signaler', $short)
        ->assertRedirect('/signaler')
        ->assertSessionHasErrors('description');

    $page = (string) $this->get('/signaler')->assertOk()->getContent();

    expect($page)->toContain(__('alerts.report.fields.description_min', ['min' => 20]))
        ->and($page)->toContain('href="#f-description"')
        ->and($page)->toMatch('/<li class="xm-stepper__item is-current" aria-current="step">\s*<span class="xm-stepper__dot" aria-hidden="true">2<\/span>/')
        ->and($page)->toContain(__('alerts.report.fields.evidence_reselect'))
        ->and(Alert::query()->count())->toBe(0);
});

it('exige au moins un besoin pour « Exprimer un besoin » (FR-ALT-02) et un type de violence pour un signalement', function (): void {
    $this->postJson('/signaler', webReport(['type' => 'need', 'category' => null]))->assertUnprocessable()
        ->assertJsonValidationErrors(['needs' => __('alerts.report.fields.needs_error')]);

    $this->postJson('/signaler', webReport(['category' => '']))->assertUnprocessable()
        ->assertJsonValidationErrors(['category' => __('alerts.report.fields.category_error')]);

    $this->postJson('/signaler', webReport(['occurred_on' => now()->addDay()->toDateString()]))->assertUnprocessable()
        ->assertJsonValidationErrors(['occurred_on' => __('alerts.report.fields.date_future')]);

    $this->postJson('/signaler', webReport(['type' => 'need', 'category' => null, 'needs' => ['hebergement']]))->assertRedirect();

    expect(Alert::query()->sole()->type)->toBe(AlertType::Need);
});

it('envoie un signalement anonyme puis remet le code de suivi une seule fois (303 → confirmation)', function (): void {
    $response = $this->post('/signaler', webReport())
        ->assertStatus(303)
        ->assertRedirect(route('signaler.confirmation'));

    $alert = Alert::query()->sole();

    expect($alert->source)->toBe(AlertSource::Web)
        ->and($alert->is_anonymous)->toBeTrue()
        ->and($alert->user_id)->toBeNull()
        ->and($alert->contact)->toBeNull()
        ->and($response->headers->get('Location'))->not->toContain($alert->tracking_code);

    $this->get(route('signaler.confirmation'))->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertSee('content="noindex', false)
        ->assertSee($alert->displayCode())
        ->assertSee(__('alerts.report.done.title'))
        ->assertSee(__('alerts.report.done.once'))
        ->assertSee('x-data="xmReportSent(', false)
        ->assertSee(__('alerts.report.done.copy'))
        ->assertSee(__('buttons.print'));

    // Rechargement : le code n'est plus affiché, la page renvoie au suivi par code.
    $this->get(route('signaler.confirmation'))->assertRedirect(route('signaler.suivi'));
});

/**
 * JPEG fabriqué avec GD, porteur d'un segment APP1 Exif (Make = « Xomiton Test », Orientation = 6), comme MediaUploaderTest.
 */
function signalerJpegWithExif(): string
{
    $image = imagecreatetruecolor(300, 200);
    imagefilledrectangle($image, 0, 0, 299, 199, (int) imagecolorallocate($image, 20, 20, 220));
    ob_start();
    imagejpeg($image, null, 90);
    $jpeg = (string) ob_get_clean();
    imagedestroy($image);

    $make = "Xomiton Test\0";
    $ifd = pack('v', 2)
        .pack('vvVV', 0x010F, 2, strlen($make), 8 + 2 + 2 * 12 + 4)
        .pack('vvVv', 0x0112, 3, 1, 6).pack('v', 0)
        .pack('V', 0);
    $app1 = "Exif\0\0".'II'.pack('v', 42).pack('V', 8).$ifd.$make;

    return substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($app1) + 2).$app1.substr($jpeg, 2);
}

it('n’enregistre aucune adresse IP, ni en base ni en session, et garde le récit chiffré même flashé', function (): void {
    // Pilotes de production (D7) : sessions et cache en base, pour fouiller toutes les tables.
    config(['session.driver' => 'database', 'cache.default' => 'database']);
    $ip = '203.0.113.77';
    $agent = 'NavigateurTemoin/9.9';
    $story = 'Mon voisin me menace depuis trois semaines, témoin unique.';
    $contact = 'temoin.unique@example.bj';
    $this->withServerVariables(['REMOTE_ADDR' => $ip])->withHeader('User-Agent', $agent);

    $this->get('/signaler')->assertOk();
    // Envoi refusé (canal manquant) : récit et contact reviennent par old(), donc passent par la session.
    $this->from('/signaler')->post('/signaler', webReport(['description' => $story, 'contactable' => '1', 'contact' => $contact]))
        ->assertRedirect('/signaler')
        ->assertSessionHasErrors('preferred_channel');
    $this->get('/signaler')->assertOk()->assertSee($story)->assertSee($contact);
    $this->post('/signaler', webReport(['description' => $story, 'contactable' => '1', 'contact' => $contact, 'preferred_channel' => 'sms']))
        ->assertStatus(303);

    $sessions = DB::table('sessions')->get();

    expect(config('session.encrypt'))->toBeTrue()
        ->and($sessions)->not->toBeEmpty()
        ->and($sessions->whereNotNull('ip_address')->count())->toBe(0)
        ->and($sessions->whereNotNull('user_agent')->count())->toBe(0)
        ->and(Alert::query()->sole()->description)->toBe($story);

    // Contenu des sessions : base64 d'une charge chiffrée, jamais le récit ni le contact en clair.
    foreach ($sessions as $session) {
        expect((string) base64_decode((string) $session->payload, true))->not->toContain('menace')->not->toContain('temoin.unique');
    }

    foreach (Schema::getTables() as $table) {
        $dump = json_encode(DB::table($table['name'])->get(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        expect($dump)->not->toContain($ip, $table['name'])
            ->not->toContain($agent, $table['name'])
            ->not->toContain('menace depuis', $table['name'])
            ->not->toContain('temoin.unique', $table['name']);
    }

    $row = (array) DB::table('alerts')->first();
    expect(array_filter(array_keys($row), static fn (string $column): bool => preg_match('/(^|_)ip(_|$)|ip_address/', $column) === 1))->toBe([])
        // Limiteur `report` : l'IP n'y figure qu'en empreinte HMAC (ADR-33), jamais en clé md5 réversible.
        ->and(RateLimiter::attempts(md5('report'.'report|'.KeyedHash::make($ip))))->toBe(2)
        ->and(RateLimiter::attempts(md5('report'.'report|'.$ip)))->toBe(0);
});

it('garde l’IP et le navigateur d’une session connectée (appareils connectés), jamais ceux d’un·e invité·e', function (): void {
    config(['session.driver' => 'database']);
    $member = AlertFixtures::member();

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.78'])->actingAs($member)->get('/signaler')->assertOk();

    expect(DB::table('sessions')->where('user_id', $member->id)->value('ip_address'))->toBe('203.0.113.78');
});

it('ne laisse pas « déjà reçu » avaler une correction : même clé, autre contenu → formulaire avec une clé neuve', function (): void {
    $payload = webReport();
    $this->post('/signaler', $payload)->assertStatus(303);
    $alert = Alert::query()->sole();
    $corrected = [...$payload, 'description' => AlertFixtures::DESCRIPTION.' Précision ajoutée après coup.'];

    $this->from('/signaler')->post('/signaler', $corrected)
        ->assertStatus(303)
        ->assertRedirect(route('signaler'))
        ->assertSessionHasErrors(['idempotency_key' => __('alerts.report.changed_after_send', ['code' => $alert->displayCode()])])
        ->assertSessionHasInput('description', $corrected['description']);

    expect(Alert::query()->count())->toBe(1)
        ->and(Alert::query()->sole()->description)->toBe(AlertFixtures::DESCRIPTION);

    $page = (string) $this->get('/signaler')->assertOk()->getContent();
    preg_match('/name="idempotency_key" value="([0-9a-f-]{36})"/', $page, $key);

    expect($key[1] ?? null)->not->toBeNull()->not->toBe($payload['idempotency_key'])
        ->and($page)->toContain('href="#f-send"')
        ->and($page)->toContain('id="f-send"')
        ->and($page)->toMatch('/<li class="xm-stepper__item is-current" aria-current="step">\s*<span class="xm-stepper__dot" aria-hidden="true">6<\/span>/');

    // Renvoi avec la clé neuve : la version corrigée part comme un nouveau signalement.
    $this->post('/signaler', [...$corrected, 'idempotency_key' => $key[1]])->assertRedirect(route('signaler.confirmation'));

    expect(Alert::query()->count())->toBe(2);
});

it('rejoue la même clé sans doublon et affiche le même code', function (): void {
    $payload = webReport();

    $this->post('/signaler', $payload)->assertStatus(303);
    $code = Alert::query()->sole()->displayCode();
    $this->post('/signaler', $payload)->assertStatus(303);

    expect(Alert::query()->count())->toBe(1);
    $this->get(route('signaler.confirmation'))->assertOk()
        ->assertSee($code)
        ->assertSee(__('alerts.report.done.duplicate'));
});

it('enregistre le contact, le canal préféré et les preuves sur le disque privé', function (): void {
    Storage::fake(MediaUploader::privateDisk());
    $pdf = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[]/Count 0>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";

    $this->post('/signaler', webReport([
        'contactable' => '1',
        'contact' => '97 38 88 47',
        'preferred_channel' => 'whatsapp',
        'needs' => ['soutien_juridique'],
        'lat' => '6.3654',
        'lng' => '2.4183',
        'accuracy' => '25',
        'attachments' => [UploadedFile::fake()->createWithContent('certificat.pdf', $pdf)],
    ]))->assertStatus(303);

    $alert = Alert::query()->sole();
    $attachment = AlertAttachment::query()->sole();

    expect($alert->contact)->toBe('+229 01 97 38 88 47')
        ->and($alert->preferred_channel)->toBe(ContactChannel::Whatsapp)
        ->and($alert->needs)->toBe(['soutien_juridique'])
        ->and($alert->location_accuracy)->toBe(25)
        ->and($attachment->alert_id)->toBe($alert->id)
        ->and($attachment->disk)->toBe(MediaUploader::privateDisk());

    Storage::disk(MediaUploader::privateDisk())->assertExists($attachment->path);
});

it('exige le contact et le canal seulement si la personne accepte d’être recontactée', function (): void {
    $this->postJson('/signaler', webReport(['contactable' => '1']))->assertUnprocessable()
        ->assertJsonValidationErrors(['contact', 'preferred_channel']);

    $this->post('/signaler', webReport(['contactable' => '0', 'contact' => 'ignoré', 'preferred_channel' => 'nimporte']))->assertStatus(303);

    expect(Alert::query()->sole()->contact)->toBeNull();
});

it('rattache le signalement au compte d’un·e membre qui décoche l’anonymat, et propose la case', function (): void {
    $member = AlertFixtures::member();

    $this->actingAs($member)->get('/signaler')->assertOk()
        ->assertSee(__('alerts.report.fields.link_account'))
        ->assertSee('<input type="hidden" name="anonymous" value="0">', false)
        ->assertDontSee('<input type="hidden" name="anonymous" value="1">', false);

    $this->actingAs($member)->post('/signaler', webReport(['anonymous' => '0']))->assertStatus(303);

    expect(Alert::query()->sole()->user_id)->toBe($member->id);
});

it('n’associe pas au compte le signalement d’un·e membre qui laisse cochée « ne pas lier » (défaut, RG-01, RG-02)', function (): void {
    $member = AlertFixtures::member();
    $page = (string) $this->actingAs($member)->get('/signaler')->assertOk()->getContent();
    preg_match('/<input[^>]*id="f-anonymous"[^>]*>/', $page, $switch);

    // Champ caché à 0 puis case cochée par défaut : le navigateur envoie anonymous=0&anonymous=1, PHP garde « 1 ».
    expect($switch[0] ?? '')->toContain('name="anonymous"')->toContain('value="1"')->toContain('checked')
        ->and(strpos($page, '<input type="hidden" name="anonymous" value="0">'))->toBeLessThan(strpos($page, 'id="f-anonymous"'));

    $this->actingAs($member)->post('/signaler', webReport(['anonymous' => '1']))->assertStatus(303);
    $this->actingAs($member)->post('/signaler', webReport(['anonymous' => null]))->assertStatus(303);

    $alerts = Alert::query()->get();

    expect($alerts)->toHaveCount(2)
        ->and($alerts->pluck('user_id')->filter()->all())->toBe([])
        ->and($alerts->every(static fn (Alert $alert): bool => $alert->is_anonymous && $alert->pseudo === null))->toBeTrue();

    // Absents de « Mes signalements » (P5-06 `GET signalements/mine`, page membre P8-12).
    $this->actingAs($member, 'sanctum')->getJson('/api/v1/signalements/mine')->assertOk()->assertJsonCount(0, 'data');
});

it('rouvre l’étape qui porte l’erreur et fait pointer le résumé vers le champ', function (array $overrides, string $error, int $step, string $target): void {
    $this->from('/signaler')->post('/signaler', webReport($overrides))->assertRedirect('/signaler')->assertSessionHasErrors($error);

    $page = (string) $this->get('/signaler')->assertOk()->getContent();

    expect($page)->toMatch('/<li class="xm-stepper__item is-current" aria-current="step">\s*<span class="xm-stepper__dot" aria-hidden="true">'.$step.'<\/span>/')
        ->and($page)->toContain('href="#'.$target.'"')
        ->and($page)->toContain('id="'.$target.'"');
})->with([
    'besoins' => [['type' => 'need', 'category' => null], 'needs', 3, 'f-needs'],
    'contact' => [['contactable' => '1', 'preferred_channel' => 'sms'], 'contact', 5, 'f-contact'],
    'position' => [['lat' => '200', 'lng' => '2.4183'], 'lat', 3, 'f-position'],
]);

it('retire l’EXIF d’une photo envoyée par /signaler et ne la sert que par lien signé', function (): void {
    Storage::fake(MediaUploader::privateDisk());
    Storage::fake('public');
    $jpeg = signalerJpegWithExif();
    expect(exif_read_data('data://image/jpeg;base64,'.base64_encode($jpeg)))->toMatchArray(['Make' => 'Xomiton Test']);

    $this->post('/signaler', webReport(['attachments' => [UploadedFile::fake()->createWithContent('photo.jpg', $jpeg)]]))->assertStatus(303);

    $attachment = AlertAttachment::query()->sole();
    $stored = (string) Storage::disk($attachment->disk)->get($attachment->path);

    expect($attachment->disk)->toBe(MediaUploader::privateDisk())
        ->and($stored)->not->toBe('')
        ->and($stored)->not->toContain('Exif')
        ->and($stored)->not->toContain('EXIF')
        ->and($stored)->not->toContain('Xomiton Test');
    Storage::disk('public')->assertMissing($attachment->path);

    $this->get(route('media.signed', ['disk' => $attachment->disk, 'path' => $attachment->path]))->assertForbidden();
    $this->get(app(MediaUploader::class)->signedUrl($attachment->disk, $attachment->path))->assertOk();
});

it('limite les envois à `report_per_hour_per_ip` par IP (429)', function (): void {
    config(['xomiton.rate_limits.report_per_hour_per_ip' => 2]);

    $this->post('/signaler', webReport())->assertStatus(303);
    $this->post('/signaler', webReport())->assertStatus(303);
    $this->post('/signaler', webReport())->assertTooManyRequests();

    expect(Alert::query()->count())->toBe(2);
    $this->get('/signaler')->assertOk();
});

it('affiche le statut d’un signalement par son code, sans récit ni nom du contact', function (): void {
    $contact = DirectoryContact::factory()->hidden()->create(['name' => 'NOM Confidentiel']);
    $alert = Alert::factory()->routedTo($contact)->withStatus(AlertStatus::Acknowledged)
        ->create(['description' => 'Récit secret', 'acknowledged_at' => now()]);
    AlertStatusChange::factory()->create(['alert_id' => $alert->id, 'from_status' => null, 'to_status' => AlertStatus::New]);
    AlertStatusChange::factory()->create(['alert_id' => $alert->id, 'from_status' => AlertStatus::New, 'to_status' => AlertStatus::Acknowledged]);

    $this->get('/suivi')->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertSee('content="noindex', false)
        ->assertSee('action="'.route('signaler.suivi.show').'"', false)
        ->assertSee(__('alerts.report.tracking.title'));

    $this->post('/suivi', ['tracking_code' => ' sig-'.strtolower($alert->tracking_code)])->assertOk()
        ->assertSee($alert->displayCode())
        ->assertSee(__('alerts.report.tracking.status', ['status' => AlertStatus::Acknowledged->label()]))
        ->assertSee(__('alerts.report.status_text.pris_en_charge'))
        ->assertSee(__('alerts.report.tracking.history'))
        ->assertSee($contact->role->label())
        ->assertDontSee('Récit secret')
        ->assertDontSee('NOM Confidentiel');
});

it('répond de façon neutre à un code inconnu, annulé ou mal formé, et exige un code', function (): void {
    $cancelled = Alert::factory()->sos()->cancelled()->create();
    $message = __('alerts.report.tracking.not_found', ['days' => 90]);

    $this->post('/suivi', ['tracking_code' => 'SIG-AAAAAAAAAA'])->assertNotFound()->assertSee($message);
    $this->post('/suivi', ['tracking_code' => $cancelled->tracking_code])->assertNotFound()->assertSee($message);
    $this->post('/suivi', ['tracking_code' => 'n’importe quoi'])->assertNotFound()->assertSee($message);

    $this->from('/suivi')->post('/suivi', ['tracking_code' => ''])
        ->assertRedirect('/suivi')
        ->assertSessionHasErrors(['tracking_code' => __('alerts.report.tracking.required')]);
});

it('limite le suivi par code à `tracking_per_hour_per_ip` (429), sans compter l’affichage du formulaire', function (): void {
    config(['xomiton.rate_limits.tracking_per_hour_per_ip' => 2]);

    $this->get('/suivi')->assertOk();
    $this->get('/suivi')->assertOk();
    $this->post('/suivi', ['tracking_code' => 'SIG-AAAAAAAAAA'])->assertNotFound();
    $this->post('/suivi', ['tracking_code' => 'SIG-AAAAAAAAAB'])->assertNotFound();
    $this->post('/suivi', ['tracking_code' => 'SIG-AAAAAAAAAC'])->assertTooManyRequests();
});

it('reste servi pendant la maintenance (RG-01) : formulaire, envoi, confirmation et suivi', function (): void {
    $this->seed(SettingsSeeder::class);
    Settings::set('maintenance', true);

    $this->get('/ressources')->assertStatus(503);
    $this->get('/signaler')->assertOk();
    $this->post('/signaler', webReport())->assertStatus(303);
    $this->get(route('signaler.confirmation'))->assertOk();
    $this->get('/suivi')->assertOk();
    $this->post('/suivi', ['tracking_code' => Alert::query()->sole()->tracking_code])->assertOk();
});

it('inscrit /signaler dans le sitemap (route livrée), sans /suivi ni la confirmation', function (): void {
    $this->app->detectEnvironment(static fn (): string => 'production');
    App\Application\Seo\SitemapBuilder::flush();

    $xml = (string) $this->get('/sitemap.xml')->assertOk()->getContent();
    $root = rtrim((string) config('app.url'), '/');

    expect($xml)->toContain('<loc>'.$root.'/signaler</loc>')
        ->and($xml)->not->toContain($root.'/suivi')
        ->and($xml)->not->toContain($root.'/signaler/confirmation');
});
