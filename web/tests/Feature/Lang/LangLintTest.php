<?php

declare(strict_types=1);

use App\Support\LangLinter;

/*
 * P16-06 : règles de lang:lint (App\Support\LangLinter) et état réel de lang/fr.
 */

function lintRules(string $file, string $key, string $value): array
{
    return array_column((new LangLinter)->check($file, $key, $value), 'rule');
}

it('signale l’apostrophe droite entre deux lettres et accepte ’', function (): void {
    expect(lintRules('a.php', 'a.k', "L'aide"))->toContain('apostrophe')
        ->and(lintRules('a.php', 'a.k', 'L’aide'))->toBe([]);
});

it('exige l’espace insécable avant : ; ! ? et ignore marqueurs, entités et URL', function (): void {
    $nbsp = LangLinter::NBSP;

    expect(lintRules('a.php', 'a.k', 'Bonjour : ça va ?'))->toContain('espace-avant-ponctuation')
        ->and(lintRules('a.php', 'a.k', 'Attention!'))->toContain('espace-manquant')
        ->and(lintRules('a.php', 'a.k', "Bonjour{$nbsp}: ça va{$nbsp}?"))->toBe([])
        ->and(lintRules('a.php', 'a.k', 'Bonjour :name, voir https://xomiton.test/a?b=1 &amp; 10:30'))->toBe([]);
});

it('exige l’espace insécable à l’intérieur de « »', function (): void {
    $nbsp = LangLinter::NBSP;

    expect(lintRules('a.php', 'a.k', 'Dites « oui » ici'))->toContain('guillemets')
        ->and(lintRules('a.php', 'a.k', "Dites «{$nbsp}oui{$nbsp}» ici"))->toBe([]);
});

it('remplace les trois points par … et le signale', function (): void {
    expect(lintRules('a.php', 'a.k', 'Chargement...'))->toContain('points-de-suspension')
        ->and(lintRules('a.php', 'a.k', 'Chargement…'))->toBe([]);
});

it('limite les boutons à 4 mots avec majuscule, les libellés à 5 mots et les badges à 2 mots', function (): void {
    expect(lintRules('buttons.php', 'buttons.a', 'Un deux trois quatre cinq'))->toContain('bouton-longueur')
        ->and(lintRules('buttons.php', 'buttons.a', 'ouvrir'))->toContain('bouton-majuscule')
        ->and(lintRules('buttons.php', 'buttons.a', 'Je suis en danger'))->toBe([])
        ->and(lintRules('a.php', 'a.label', 'un deux trois quatre cinq six'))->toContain('libelle-longueur')
        ->and(lintRules('a.php', 'a.aria_label', 'un deux trois quatre cinq six'))->toBe([])
        ->and(lintRules('a.php', 'a.badge', 'Un deux trois'))->toContain('badge-longueur');
});

it('corrige les violations typographiques d’un fichier sans toucher aux clés ni aux marqueurs', function (): void {
    $nbsp = LangLinter::NBSP;
    $source = "<?php\n\nreturn [\n    'a b' => 'Qu\\'est-ce : voir :name ... « oui » ?',\n];\n";
    $fixed = (new LangLinter)->fix($source);

    expect($fixed)->toContain("'a b' =>")
        ->toContain("Qu’est-ce{$nbsp}: voir :name … «{$nbsp}oui{$nbsp}»{$nbsp}?");

    $array = eval('?>'.$fixed);
    expect($array)->toHaveKey('a b');
});

it('la commande lang:lint échoue sur un dossier fautif puis réussit après --fix', function (): void {
    $dir = sys_get_temp_dir().'/lang-lint-'.bin2hex(random_bytes(4));
    mkdir($dir);
    file_put_contents($dir.'/a.php', "<?php\n\nreturn ['k' => 'Voir l\\'aide : ici...'];\n");

    $this->artisan('lang:lint', ['--path' => $dir])->assertFailed();
    $this->artisan('lang:lint', ['--path' => $dir, '--fix' => true])->assertSuccessful();

    unlink($dir.'/a.php');
    rmdir($dir);
});

it('lang/fr ne contient aucune violation typographique', function (): void {
    $typography = array_filter(
        (new LangLinter)->lintDirectory(lang_path('fr')),
        fn (array $v): bool => ! str_ends_with($v['rule'], '-longueur') && $v['rule'] !== 'bouton-majuscule',
    );

    expect(array_values($typography))->toBe([]);
});
