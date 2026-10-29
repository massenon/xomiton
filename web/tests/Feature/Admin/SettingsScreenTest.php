<?php

declare(strict_types=1);

use App\Application\Platform\Settings\PasswordPolicy;
use App\Application\Platform\Settings\SettingsCatalog;
use App\Application\Platform\Settings\UpdateSettings;
use App\Http\Middleware\EnforceIdleTimeout;
use App\Livewire\Admin\Settings\Index as SettingsScreen;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use App\Rules\StrongPassword;
use App\Support\Settings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/*
 * P13-04 : écran /admin/parametres — clés typées avec défaut (catalogue de config/xomiton.php), une validation par clé,
 * secrets chiffrés jamais renvoyés, lecture `settings.view` (admin), écriture et SMTP `settings.manage` (superadmin),
 * politique de mot de passe qui ne peut que durcir le plancher, durée de session et fuseau appliqués à l'exécution.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function settingsUser(string $role): User
{
    $user = User::factory()->create();
    $user->syncRoles([$role]);

    return $user;
}

/** Valeur invalide et valeur valide pour chaque clé du catalogue : « chaque clé a un test de validation ». */
function settingsCases(): array
{
    return [
        'site_name' => ['x', 'Xomiton Bénin'],
        'contact_email' => ['pas-un-email', 'equipe@xomiton.bj'],
        'timezone' => ['Mars/Olympus', 'Africa/Lagos'],
        'languages' => [['en'], ['fr', 'fon']],
        'anonymous_reports' => ['peut-être', false],
        'gps_sos' => ['peut-être', false],
        'offline_sync' => ['peut-être', false],
        'password_min_length' => [9, 12],
        'password_require_symbol' => ['peut-être', true],
        'password_expiry_days' => [400, 90],
        'session_minutes' => [4, 240],
        'audit_log_days' => [6, 365],
        'app_min_version' => ['1.5', '1.4.0'],
        'mobile_space_peer_enabled' => ['peut-être', false],
        'mobile_space_admin_enabled' => ['peut-être', false],
        'sync_interval_minutes' => [7, 30],
        'sync_max_mb' => [0, 12],
        'sync_wifi_only' => ['peut-être', true],
        'smtp_host' => [str_repeat('a', 200), 'smtp.exemple.bj'],
        'smtp_port' => [70000, 465],
        'smtp_encryption' => ['rot13', 'ssl'],
        'smtp_username' => [str_repeat('a', 200), 'xomiton'],
        'smtp_password' => [str_repeat('a', 200), 'secret-smtp-2'],
        'smtp_from_email' => ['pas-un-email', 'envoi@xomiton.bj'],
        'smtp_from_name' => ['', 'Équipe Xomiton'],
        'smtp_reply_to' => ['pas-un-email', 'reponse@xomiton.bj'],
    ];
}

it('déclare pour chaque clé un type, un groupe, un défaut et des règles, et chaque clé a un test de validation', function (): void {
    $catalog = SettingsCatalog::all();

    expect(array_keys($catalog))->toEqualCanonicalizing(array_keys(settingsCases()));

    foreach ($catalog as $key => $definition) {
        expect($definition->type)->toBeIn(Setting::TYPES, "type de {$key}")
            ->and($definition->group)->toBeIn(Setting::GROUPS, "groupe de {$key}")
            ->and($definition->rules)->not->toBeEmpty("règles de {$key}");

        if (! $definition->isSecret()) {
            expect($definition->default)->not->toBeNull("défaut de {$key}");
        }
    }
});

it('refuse la valeur invalide et accepte la valeur valide de chaque clé, secrets chiffrés compris', function (string $key, mixed $invalid, mixed $valid): void {
    $super = settingsUser('superadmin');
    $update = app(UpdateSettings::class);

    expect(fn () => $update($super, [$key => $invalid]))->toThrow(ValidationException::class);
    expect(Setting::query()->where('key', $key)->exists())->toBeFalse();

    $update($super, [$key => $valid]);
    $row = Setting::query()->where('key', $key)->firstOrFail();
    $definition = SettingsCatalog::definition($key);

    expect($row->type)->toBe($definition->type)->and($row->group)->toBe($definition->group)->and($row->is_public)->toBe($definition->public);

    if ($definition->isSecret()) {
        expect((string) $row->getRawOriginal('value'))->not->toContain($valid)->and(Settings::get($key))->toBe($valid);
    } else {
        expect(SettingsCatalog::value($key))->toBe($valid);
    }
})->with(fn (): array => collect(settingsCases())->map(fn (array $pair, string $key): array => [$key, $pair[0], $pair[1]])->values()->all());

it('sert l’écran à l’admin en lecture seule et au superadmin, refuse le modérateur, la pair·e et le membre', function (): void {
    foreach (['admin', 'superadmin'] as $role) {
        $html = $this->actingAs(settingsUser($role))->get(route('admin.parametres'))
            ->assertOk()->assertSeeLivewire(SettingsScreen::class)->assertSee('Paramètres généraux')->getContent();
        expect($html)->toMatch('/data-xm-nav="parametres"\s+aria-current="page"/');
    }

    $this->actingAs(settingsUser('admin'))->get(route('admin.parametres'))->assertSee('seul le superadmin peut les modifier');

    foreach (['moderateur', 'pair', 'membre'] as $role) {
        $this->actingAs(settingsUser($role))->get(route('admin.parametres'))->assertForbidden();
    }
});

it('enregistre les paramètres généraux, avec le français obligatoire et la validation par clé', function (): void {
    $super = settingsUser('superadmin');
    $this->actingAs($super);

    Livewire::test(SettingsScreen::class)
        ->set('form.site_name', 'X')->set('form.contact_email', 'faux')
        ->call('save', 'general')->assertHasErrors(['form.site_name', 'form.contact_email'])
        ->set('form.site_name', 'Xomiton Bénin')->set('form.contact_email', 'contact@xomiton.bj')->set('form.languages', ['en'])
        ->call('save', 'general')->assertHasErrors(['form.languages'])
        ->set('form.languages', ['fr', 'fon'])->set('form.timezone', 'Africa/Lagos')->set('form.gps_sos', false)
        ->call('save', 'general')->assertHasNoErrors()->assertDispatched('toast');

    expect(Settings::string('site_name'))->toBe('Xomiton Bénin')
        ->and(Settings::array('languages'))->toBe(['fr', 'fon'])
        ->and(Settings::string('timezone'))->toBe('Africa/Lagos')
        ->and(Settings::bool('gps_sos', true))->toBeFalse();

    $log = AuditLog::query()->where('action', 'settings.updated')->latest('id')->firstOrFail();
    expect($log->detail)->toContain('site_name')->toContain('timezone');
});

it('refuse l’écriture à l’admin (403) et masque l’onglet SMTP', function (): void {
    $this->actingAs(settingsUser('admin'));

    Livewire::test(SettingsScreen::class)
        ->assertDontSee('Messagerie (SMTP)')
        ->set('form.site_name', 'Piraté')->call('save', 'general')->assertForbidden();
    Livewire::test(SettingsScreen::class)->call('save', 'smtp')->assertForbidden();

    expect(Settings::string('site_name', 'Xomiton'))->not->toBe('Piraté');
    expect(fn () => app(UpdateSettings::class)(settingsUser('admin'), ['site_name' => 'Piraté']))->toThrow(AuthorizationException::class);
});

it('ne renvoie jamais le mot de passe SMTP, le garde si le champ reste vide et le journal le masque', function (): void {
    $this->actingAs(settingsUser('superadmin'));

    $component = Livewire::test(SettingsScreen::class)->call('setTab', 'smtp')
        ->set('form.smtp_host', 'smtp.exemple.bj')->set('smtpPassword', 'secret-smtp-2')
        ->call('save', 'smtp')->assertHasNoErrors()
        ->assertSet('smtpPassword', '')
        ->assertDontSee('secret-smtp-2')
        ->assertSee('Un mot de passe est enregistré');

    expect(Settings::get('smtp_password'))->toBe('secret-smtp-2');

    $component->set('form.smtp_port', 2525)->call('save', 'smtp')->assertHasNoErrors();
    expect(Settings::get('smtp_password'))->toBe('secret-smtp-2')->and(Settings::int('smtp_port'))->toBe(2525);

    $logs = AuditLog::query()->where('action', 'settings.updated')->pluck('detail')->implode(' ');
    expect($logs)->not->toContain('secret-smtp-2');
    expect(Setting::query()->where('key', 'smtp_password')->firstOrFail()->type)->toBe('secret');
});

it('ne laisse que durcir la politique de mot de passe : longueur ≥ 10, symbole, plancher conservé', function (): void {
    $this->actingAs(settingsUser('superadmin'));

    Livewire::test(SettingsScreen::class)->call('setTab', 'security')
        ->set('form.password_min_length', 8)->call('save', 'security')->assertHasErrors(['form.password_min_length'])
        ->set('form.password_min_length', 12)->set('form.password_require_symbol', true)->call('save', 'security')->assertHasNoErrors();

    expect(PasswordPolicy::minLength())->toBe(12)->and(PasswordPolicy::requireSymbol())->toBeTrue();

    $check = fn (string $password): bool => Validator::make(['password' => $password], ['password' => [new StrongPassword]])->passes();

    expect($check('Abcdefghi1!'))->toBeFalse()      // 11 caractères : sous la longueur durcie
        ->and($check('Abcdefghijk1'))->toBeFalse()   // sans symbole
        ->and($check('Abcdefghij1!'))->toBeTrue();

    Setting::query()->where('key', 'password_min_length')->delete();
    Setting::flushCache();
    Setting::query()->where('key', 'password_require_symbol')->delete();
    Setting::flushCache();
    expect(PasswordPolicy::minLength())->toBe(10)->and($check('Abcdefghi1'))->toBeTrue();
});

it('applique la durée d’inactivité et le fuseau d’affichage enregistrés à l’exécution', function (): void {
    $this->actingAs(settingsUser('superadmin'));

    Livewire::test(SettingsScreen::class)->call('setTab', 'security')
        ->set('form.session_minutes', 45)->call('save', 'security')->assertHasNoErrors();
    Livewire::test(SettingsScreen::class)
        ->set('form.timezone', 'Africa/Abidjan')->call('save', 'general')->assertHasNoErrors();

    $this->get(route('admin.parametres'))->assertOk();

    expect((int) config('session.lifetime'))->toBe(45)
        ->and(EnforceIdleTimeout::idleSeconds())->toBe(45 * 60)
        ->and(config('xomiton.display_timezone'))->toBe('Africa/Abidjan');
});

it('enregistre la durée de conservation du journal, lue par la purge et par l’écran Journal', function (): void {
    $this->actingAs(settingsUser('superadmin'));

    Livewire::test(SettingsScreen::class)->call('setTab', 'retention')
        ->set('form.audit_log_days', 3)->call('save', 'retention')->assertHasErrors(['form.audit_log_days'])
        ->set('form.audit_log_days', 200)->call('save', 'retention')->assertHasNoErrors();

    expect(app(App\Application\Retention\PurgeAuditLogs::class)->resolveDays())->toBe(200);
});

it('affiche les informations système, la garde et l’identité visuelle en lecture seule', function (): void {
    $this->actingAs(settingsUser('admin'));

    Livewire::test(SettingsScreen::class)->call('setTab', 'system')
        ->assertSee('Informations système')->assertSee(PHP_VERSION)->assertSee('Identité visuelle')->assertSee('Garde');
});
