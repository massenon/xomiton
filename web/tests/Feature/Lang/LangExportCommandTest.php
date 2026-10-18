<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/*
 * `lang:export` (P3-05) : JSON à plat pour vue-i18n et ARB pour Flutter depuis lang/<locale>, option --check pour la CI.
 * Les tests travaillent sur un dossier lang/ de fixture (app()->useLangPath) et des dossiers de sortie temporaires ;
 * le dernier test exporte le vrai lang/fr dans un dossier temporaire.
 */

beforeEach(function (): void {
    $this->workDir = sys_get_temp_dir().'/xomiton-lang-'.uniqid();
    File::ensureDirectoryExists($this->workDir.'/lang/fr/enums');

    File::put($this->workDir.'/lang/fr/demo.php', <<<'PHP'
        <?php

        return [
            'hello' => 'Bonjour :name !',
            'items' => '{0} Aucun élément|{1} :count élément|[2,*] :count éléments',
            'simple' => 'pomme|pommes',
            'mail' => 'nom@exemple.bj',
            'braces' => 'Variable {{prenom}} possible',
            'time' => 'Rendez-vous à 15:00',
            'nested' => ['deep' => ['key' => 'Profond'], 'flag' => true, 'number' => 3],
            'range' => ['50+' => '50 ans et plus'],
        ];
        PHP);

    File::put($this->workDir.'/lang/fr/enums/kind.php', <<<'PHP'
        <?php

        return ['a' => 'Alpha', 'sync-offline' => 'Hors connexion'];
        PHP);

    File::put($this->workDir.'/lang/fr.json', json_encode(['Anonyme' => 'Anonyme'], JSON_UNESCAPED_UNICODE));

    $this->originalLangPath = app()->langPath();
    app()->useLangPath($this->workDir.'/lang');

    config([
        'xomiton.lang_export.json_dir' => $this->workDir.'/js/lang',
        'xomiton.lang_export.arb_dir' => $this->workDir.'/mobile/lib/l10n',
    ]);
});

afterEach(function (): void {
    app()->useLangPath($this->originalLangPath);
    File::deleteDirectory($this->workDir);
});

/** @return array<string, mixed> */
function readJson(string $path): array
{
    return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
}

it('exporte un JSON à plat pour vue-i18n avec placeholders {nom}, pluriels et échappements', function (): void {
    $this->artisan('lang:export', ['--locale' => 'fr'])
        ->expectsOutputToContain('fr · 11 clés')
        ->assertSuccessful();

    $json = readJson($this->workDir.'/js/lang/fr.json');

    expect($json)
        ->toHaveKey('demo.hello', 'Bonjour {name} !')
        ->toHaveKey('demo.items', 'Aucun élément | {count} élément | {count} éléments')
        ->toHaveKey('demo.simple', 'pomme | pommes')
        ->toHaveKey('demo.mail', "nom{'@'}exemple.bj")
        ->toHaveKey('demo.braces', "Variable {'{'}{'{'}prenom{'}'}{'}'} possible")
        ->toHaveKey('demo.time', 'Rendez-vous à 15:00')
        ->toHaveKey('demo.nested.deep.key', 'Profond')
        ->toHaveKey('demo.range.50+', '50 ans et plus')
        ->toHaveKey('enums/kind.a', 'Alpha')
        ->toHaveKey('enums/kind.sync-offline', 'Hors connexion')
        ->toHaveKey('Anonyme', 'Anonyme')
        ->not->toHaveKey('demo.nested.flag')
        ->not->toHaveKey('demo.nested.number')
        ->and(array_keys($json))->toBe(collect(array_keys($json))->sort(SORT_STRING)->values()->all());
});

it('retire du JSON Vue les préfixes de vue_exclude, jamais de l’ARB', function (): void {
    config(['xomiton.lang_export.vue_exclude' => ['demo.nested', 'enums/', 'demo.hello']]);

    $this->artisan('lang:export', ['--locale' => 'fr'])->assertSuccessful();

    $json = readJson($this->workDir.'/js/lang/fr.json');
    $arb = readJson($this->workDir.'/mobile/lib/l10n/app_fr.arb');

    expect($json)->toHaveKey('demo.items')
        ->toHaveKey('demo.simple')
        ->toHaveKey('demo.range.50+')
        ->not->toHaveKey('demo.hello')
        ->not->toHaveKey('demo.nested.deep.key')
        ->not->toHaveKey('enums/kind.a')
        ->and($arb)->toHaveKey('demo_nested_deep_key')
        ->and($arb)->toHaveKey('enums_kind_a');
});

it('exporte un ARB Flutter avec clés Dart, placeholders ICU et pluriels ICU', function (): void {
    $this->artisan('lang:export', ['--locale' => 'fr'])->assertSuccessful();

    $arb = readJson($this->workDir.'/mobile/lib/l10n/app_fr.arb');

    expect($arb)
        ->toHaveKey('@@locale', 'fr')
        ->toHaveKey('demo_hello', 'Bonjour {name} !')
        ->toHaveKey('demo_items', '{count, plural, =0{Aucun élément} =1{{count} élément} other{{count} éléments}}')
        ->toHaveKey('demo_simple', '{count, plural, =1{pomme} other{pommes}}')
        ->toHaveKey('demo_braces', "Variable '{''{'prenom'}''}' possible")
        ->toHaveKey('demo_range_50plus', '50 ans et plus')
        ->toHaveKey('enums_kind_sync_offline', 'Hors connexion')
        ->toHaveKey('Anonyme', 'Anonyme')
        ->and($arb['@demo_hello'])->toBe(['description' => 'demo.hello', 'placeholders' => ['name' => ['type' => 'String']]])
        ->and($arb['@demo_items'])->toBe(['description' => 'demo.items', 'placeholders' => ['count' => ['type' => 'int']]])
        ->and($arb['@demo_time'])->toBe(['description' => 'demo.time']);

    foreach (array_keys($arb) as $key) {
        if (! str_starts_with($key, '@')) {
            expect($key)->toMatch('/^[A-Za-z][A-Za-z0-9_]*$/');
        }
    }
});

it('réussit --check après un export puis échoue (code 1) dès qu’un export est modifié ou manquant', function (): void {
    $this->artisan('lang:export', ['--locale' => 'fr', '--check' => true])->assertExitCode(1);

    $this->artisan('lang:export', ['--locale' => 'fr'])->assertSuccessful();

    $this->artisan('lang:export', ['--locale' => 'fr', '--check' => true])
        ->expectsOutputToContain('Exports à jour.')
        ->assertExitCode(0);

    File::put($this->workDir.'/lang/fr/demo.php', "<?php\n\nreturn ['hello' => 'Salut :name'];\n");

    $this->artisan('lang:export', ['--locale' => 'fr', '--check' => true])
        ->expectsOutputToContain('Exports obsolètes ou manquants')
        ->expectsOutputToContain('/js/lang/fr.json')
        ->expectsOutputToContain('/mobile/lib/l10n/app_fr.arb')
        ->assertExitCode(1);

    expect(file_get_contents($this->workDir.'/js/lang/fr.json'))->toContain('"demo.items"');

    $this->artisan('lang:export', ['--locale' => 'fr'])->assertSuccessful();
    File::delete($this->workDir.'/mobile/lib/l10n/app_fr.arb');

    $this->artisan('lang:export', ['--locale' => 'fr', '--check' => true])->assertExitCode(1);
});

it('ignore une locale sans messages et échoue (code 2) si aucune locale n’est exportable', function (): void {
    $this->artisan('lang:export', ['--locale' => 'xx'])
        ->expectsOutputToContain('Aucun message pour la locale « xx »')
        ->assertExitCode(2);

    $this->artisan('lang:export', ['--locale' => 'fr,xx'])->assertSuccessful();

    expect(File::exists($this->workDir.'/js/lang/fr.json'))->toBeTrue()
        ->and(File::exists($this->workDir.'/js/lang/xx.json'))->toBeFalse();
});

it('exporte le vrai lang/fr sans collision de clé ARB et avec les textes de référence', function (): void {
    app()->useLangPath($this->originalLangPath);

    $this->artisan('lang:export', ['--locale' => 'fr'])->assertSuccessful();

    $json = readJson($this->workDir.'/js/lang/fr.json');
    $arb = readJson($this->workDir.'/mobile/lib/l10n/app_fr.arb');

    expect(count($json))->toBeGreaterThan(1500)
        ->and($json)->toHaveKey('errors.419.member.text')
        ->and($json['alerts.report.done.text'])->toBe('Une personne formée le lira sous 24 h.')
        ->and($json)->not->toHaveKey('admin.datatable.showing')
        ->and(collect(array_keys($json))->filter(fn (string $key): bool => str_starts_with($key, 'pages/') || str_starts_with($key, 'seo.'))->all())->toBe([])
        ->and($json)->toHaveKey('newsletter.public.subscribed_title')
        ->and($json)->not->toHaveKey('newsletter.admin.title')
        ->and($json['enums/alerts.alert_type.denonciation'])->toBe('Signalement')
        ->and($arb)->toHaveKey('errors_419_public_text')
        ->and($arb)->toHaveKey('errors_419_member_text')
        ->and($arb['admin_datatable_showing'])->toBe('Affichage de {from} à {to} sur {total} entrées')
        ->and($arb['@admin_datatable_showing']['placeholders'])->toHaveKeys(['from', 'to', 'total']);
});
