<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\LangLinter;
use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;

/**
 * `php artisan lang:lint [--locale=fr] [--path=] [--fix]` (P16-06) : vérifie la typographie et la longueur des textes de
 * lang/<locale> (règles : App\Support\LangLinter). Code de sortie 1 s'il reste une violation ; la CI l'exécute.
 * `--fix` réécrit les violations typographiques évidentes (apostrophes, espaces insécables, « », …) ; relancer
 * ensuite `php artisan lang:export` (les catalogues Vue et Flutter en dérivent).
 */
final class LangLintCommand extends Command
{
    protected $signature = 'lang:lint
        {--locale=fr : Locale à vérifier}
        {--path= : Dossier à vérifier à la place de lang/<locale>}
        {--baseline= : Fichier JSON des violations de longueur connues, tolérées en attendant la revue éditoriale (défaut : lang-lint-baseline.json)}
        {--no-baseline : Ignorer la liste des violations tolérées}
        {--write-baseline : Écrire les violations actuelles dans le fichier de tolérance}
        {--fix : Corriger les violations typographiques évidentes avant de vérifier}';

    protected $description = 'Vérifie la typographie française et la longueur des textes de lang/<locale>';

    public function handle(LangLinter $linter): int
    {
        $directory = (string) ($this->option('path') ?: lang_path((string) $this->option('locale')));

        if (! is_dir($directory)) {
            $this->components->error("Dossier introuvable : {$directory}");

            return self::FAILURE;
        }

        if ($this->option('fix')) {
            $changed = 0;

            foreach (Finder::create()->files()->in($directory)->name('*.php') as $file) {
                $before = (string) file_get_contents($file->getRealPath());
                $after = $linter->fix($before);

                if ($after !== $before) {
                    file_put_contents($file->getRealPath(), $after);
                    $changed++;
                }
            }

            $this->components->info("{$changed} fichier(s) corrigé(s).");
        }

        $violations = $linter->lintDirectory($directory);
        $baselinePath = (string) ($this->option('baseline') ?: base_path('lang-lint-baseline.json'));

        if ($this->option('write-baseline')) {
            file_put_contents($baselinePath, json_encode(
                array_map(fn (array $v): string => $v['file'].'|'.$v['key'].'|'.$v['rule'], $violations),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            )."\n");
            $this->components->info(count($violations).' violation(s) enregistrée(s) comme tolérées.');

            return self::SUCCESS;
        }

        $tolerated = ! $this->option('no-baseline') && is_file($baselinePath)
            ? (array) json_decode((string) file_get_contents($baselinePath), true)
            : [];
        $known = 0;
        $new = [];

        foreach ($violations as $violation) {
            if (in_array($violation['file'].'|'.$violation['key'].'|'.$violation['rule'], $tolerated, true)) {
                $known++;
            } else {
                $new[] = $violation;
            }
        }

        if ($known > 0) {
            $this->components->warn("{$known} violation(s) de longueur tolérée(s) (lang-lint-baseline.json), à traiter à la revue éditoriale.");
        }

        $violations = $new;

        foreach ($violations as $violation) {
            $this->line("{$violation['file']} · {$violation['key']} · {$violation['rule']} : {$violation['message']}");
        }

        if ($violations === []) {
            $this->components->info('lang:lint : aucune violation.');

            return self::SUCCESS;
        }

        $this->components->error(count($violations).' violation(s).');

        return self::FAILURE;
    }
}
