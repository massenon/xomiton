<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Locales;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;
use Symfony\Component\Finder\Finder;

/**
 * `php artisan lang:export [--locale=fr] [--check]` (P3-05) : exporte les messages de lang/<locale> pour les deux
 * autres clients de la plateforme, à partir d'une seule source de vérité (lang/<locale>/*.php et lang/<locale>.json).
 *
 * - `resources/js/lang/<locale>.json` : clés à plat « fichier.cle.souscle » pour vue-i18n (résolveur à plat),
 *   placeholders `:nom` → `{nom}`, pluriels Laravel « {0} a|{1} b|[2,*] c » → « a | b | c », `@` et accolades
 *   littérales échappés ; sans les préfixes de config('xomiton.lang_export.vue_exclude') (administration Livewire,
 *   pages publiques Blade, SEO, e-mails : catalogue attendu avant le montage de l'espace Vue, PERFORMANCE.md) ;
 * - `mobile/lib/l10n/app_<locale>.arb` : format ARB de Flutter (gen-l10n), clés « fichier_cle_souscle »,
 *   placeholders ICU `{nom}` déclarés dans les @-métadonnées, pluriels ICU `{count, plural, =0{…} =1{…} other{…}}`.
 *
 * `--check` ne réécrit rien et échoue (code 1) si un fichier exporté manque ou n'est plus à jour : la CI l'exécute
 * après chaque modification de lang/. Les dossiers viennent de config('xomiton.lang_export').
 */
final class LangExportCommand extends Command
{
    protected $signature = 'lang:export
        {--locale=fr : Locale(s) à exporter, séparées par des virgules, ou « all » pour toutes celles de config xomiton.locales et locales_planned}
        {--check : Ne rien écrire ; échouer (code 1) si un fichier exporté manque ou n’est pas à jour}';

    protected $description = 'Exporte lang/<locale> en JSON à plat pour vue-i18n et en ARB (placeholders ICU) pour Flutter';

    /** Placeholder Laravel « :nom » (jamais « :: », « :/ » ni un chiffre). */
    private const PLACEHOLDER = '/:([A-Za-z_][A-Za-z0-9_]*)/';

    public function handle(Filesystem $files): int
    {
        $check = (bool) $this->option('check');
        $jsonDir = rtrim((string) config('xomiton.lang_export.json_dir'), '/');
        $arbDir = rtrim((string) config('xomiton.lang_export.arb_dir'), '/');

        $stale = [];
        $exported = 0;

        foreach ($this->locales() as $locale) {
            $messages = $this->collect($locale);

            if ($messages === []) {
                $this->components->warn(sprintf('Aucun message pour la locale « %s » : lang/%s absent ou vide, export ignoré.', $locale, $locale));

                continue;
            }

            try {
                $targets = [
                    $jsonDir.'/'.$locale.'.json' => $this->toVueJson($this->forVue($messages)),
                    $arbDir.'/app_'.$locale.'.arb' => $this->toArb($locale, $messages),
                ];
            } catch (RuntimeException $exception) {
                $this->components->error($exception->getMessage());

                return self::FAILURE;
            }

            foreach ($targets as $path => $content) {
                if ($check) {
                    if (! $files->exists($path) || $files->get($path) !== $content) {
                        $stale[] = $path;
                    }

                    continue;
                }

                $files->ensureDirectoryExists(dirname($path));
                $files->put($path, $content);
            }

            $exported++;
            $this->components->twoColumnDetail(
                sprintf('%s · %d clés', $locale, count($messages)),
                $check ? 'vérifiée' : implode(' · ', array_keys($targets)),
            );
        }

        if ($exported === 0) {
            $this->components->error('Aucune locale exportable : vérifiez --locale et le contenu de lang/.');

            return self::INVALID;
        }

        if ($check) {
            if ($stale !== []) {
                $this->components->error('Exports obsolètes ou manquants : lancez « php artisan lang:export » puis commitez les fichiers générés.');

                foreach ($stale as $path) {
                    $this->line('  - '.$path);
                }

                return self::FAILURE;
            }

            $this->components->info('Exports à jour.');

            return self::SUCCESS;
        }

        $this->components->info(sprintf('%d locale(s) exportée(s).', $exported));

        return self::SUCCESS;
    }

    /** @return list<string> */
    private function locales(): array
    {
        $option = trim((string) $this->option('locale'));

        if ($option === '' || $option === 'all') {
            return Locales::all();
        }

        return array_values(array_unique(array_filter(array_map('trim', explode(',', $option)))));
    }

    /**
     * Toutes les lignes de lang/<locale> à plat : « fichier.cle.souscle » (les sous-dossiers gardent la barre
     * oblique de Laravel : « enums/alerts.alert_type.sos »), puis les chaînes JSON de lang/<locale>.json telles
     * quelles. Seules les valeurs chaîne sont exportées ; triées pour un export déterministe.
     *
     * @return array<string, string>
     */
    private function collect(string $locale): array
    {
        $messages = [];
        $directory = app()->langPath($locale);

        if (is_dir($directory)) {
            $finder = Finder::create()->files()->in($directory)->name('*.php')->sortByName();

            foreach ($finder as $file) {
                $group = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getRelativePathname(), 0, -4));
                $lines = require $file->getRealPath();

                if (is_array($lines)) {
                    $this->flatten($messages, $lines, $group);
                }
            }
        }

        $json = app()->langPath($locale.'.json');

        if (is_file($json)) {
            $decoded = json_decode((string) file_get_contents($json), true);

            if (is_array($decoded)) {
                foreach ($decoded as $key => $value) {
                    if (is_string($value) && is_string($key) && $key !== '') {
                        $messages[$key] = $value;
                    }
                }
            }
        }

        ksort($messages, SORT_STRING);

        return $messages;
    }

    /**
     * @param  array<string, string>  $messages
     * @param  array<int|string, mixed>  $lines
     */
    private function flatten(array &$messages, array $lines, string $prefix): void
    {
        foreach ($lines as $key => $value) {
            $flatKey = $prefix.'.'.$key;

            if (is_array($value)) {
                $this->flatten($messages, $value, $flatKey);
            } elseif (is_string($value)) {
                $messages[$flatKey] = $value;
            }
        }
    }

    // --- vue-i18n ----------------------------------------------------------------------------------------------------

    /**
     * Messages de l'espace Vue : une clé est retirée si elle vaut un préfixe exclu, ou commence par « préfixe. » (par
     * « préfixe » seul quand il finit par « / » : « pages/ » retire tous les fichiers de lang/<locale>/pages).
     *
     * @param  array<string, string>  $messages
     * @return array<string, string>
     */
    private function forVue(array $messages): array
    {
        $excluded = array_values(array_filter((array) config('xomiton.lang_export.vue_exclude', []), fn (mixed $prefix): bool => is_string($prefix) && $prefix !== ''));

        if ($excluded === []) {
            return $messages;
        }

        return array_filter($messages, function (string $key) use ($excluded): bool {
            foreach ($excluded as $prefix) {
                if ($key === $prefix || str_starts_with($key, str_ends_with($prefix, '/') ? $prefix : $prefix.'.')) {
                    return false;
                }
            }

            return true;
        }, ARRAY_FILTER_USE_KEY);
    }

    /** @param  array<string, string>  $messages */
    private function toVueJson(array $messages): string
    {
        $out = [];

        foreach ($messages as $key => $value) {
            $out[$key] = $this->toVueMessage($value);
        }

        return $this->encode($out);
    }

    /**
     * Pluriels Laravel → pluriels vue-i18n (« zéro | un | plusieurs », choix par position), `@`, `{` et `}` littéraux
     * échappés (`{'@'}`), placeholders `:nom` → `{nom}`.
     */
    private function toVueMessage(string $value): string
    {
        if ($this->isPlural($value)) {
            $segments = array_map(
                fn (string $segment): string => $this->escapeVue($this->stripPluralPrefix($segment)),
                explode('|', $value),
            );
            $value = implode(' | ', $segments);
        } else {
            $value = $this->escapeVue($value);
        }

        return (string) preg_replace(self::PLACEHOLDER, '{$1}', $value);
    }

    private function escapeVue(string $value): string
    {
        // strtr : une seule passe, le texte inséré n'est pas réexaminé.
        return strtr($value, ['{' => "{'{'}", '}' => "{'}'}", '@' => "{'@'}"]);
    }

    // --- ARB (Flutter gen-l10n) --------------------------------------------------------------------------------------

    /** @param  array<string, string>  $messages */
    private function toArb(string $locale, array $messages): string
    {
        $out = [
            '@@locale' => $locale,
            '@@x-source' => 'web/lang/'.$locale.' — généré par « php artisan lang:export », ne pas modifier à la main',
        ];
        $seen = [];

        foreach ($messages as $key => $value) {
            $arbKey = $this->arbKey($key);

            if (isset($seen[$arbKey])) {
                throw new RuntimeException(sprintf('Collision de clé ARB « %s » entre « %s » et « %s » : renommez l’une des deux clés.', $arbKey, $seen[$arbKey], $key));
            }

            $seen[$arbKey] = $key;
            $placeholders = [];
            $out[$arbKey] = $this->toArbMessage($value, $placeholders);

            $meta = ['description' => $key];

            if ($placeholders !== []) {
                ksort($placeholders);
                $meta['placeholders'] = array_map(static fn (string $type): array => ['type' => $type], $placeholders);
            }

            $out['@'.$arbKey] = $meta;
        }

        return $this->encode($out);
    }

    /**
     * Clé ARB = identifiant Dart : lettres, chiffres et tirets bas, sans commencer par un chiffre.
     * « enums/alerts.alert_type.sos » → « enums_alerts_alert_type_sos » ; « 50+ » → « 50plus ».
     */
    private function arbKey(string $key): string
    {
        $arb = str_replace('+', 'plus', $key);
        $arb = trim((string) preg_replace('/[^A-Za-z0-9]+/', '_', $arb), '_');

        if ($arb === '' || ctype_digit($arb[0])) {
            $arb = 'k_'.$arb;
        }

        return $arb;
    }

    /**
     * Message ICU : accolades littérales protégées (`'{'`, `'}'` ; `use-escaping: true` dans l10n.yaml), placeholders
     * `:nom` → `{nom}` (type String), pluriels Laravel → `{count, plural, =0{…} =1{…} other{…}}` (count : int).
     *
     * @param  array<string, string>  $placeholders
     */
    private function toArbMessage(string $value, array &$placeholders): string
    {
        $convert = function (string $text) use (&$placeholders): string {
            $text = strtr($text, ['{' => "'{'", '}' => "'}'"]);

            return (string) preg_replace_callback(self::PLACEHOLDER, static function (array $match) use (&$placeholders): string {
                $placeholders[$match[1]] ??= 'String';

                return '{'.$match[1].'}';
            }, $text);
        };

        if (! $this->isPlural($value)) {
            return $convert($value);
        }

        $forms = [];
        $positional = [];

        foreach (explode('|', $value) as $segment) {
            if (preg_match('/^\s*\{(\d+)\}\s*(.*)$/su', $segment, $match) === 1) {
                $forms['='.$match[1]] = $match[2];
            } elseif (preg_match('/^\s*\[(\d+),\s*(?:\d+|\*)\]\s*(.*)$/su', $segment, $match) === 1) {
                $forms['other'] = $match[2];
            } else {
                $positional[] = trim($segment);
            }
        }

        // « a|b » sans bornes : singulier puis pluriel.
        if ($positional !== []) {
            $forms['=1'] ??= $positional[0];
            $forms['other'] ??= $positional[1] ?? $positional[0];
        }

        if (! isset($forms['other'])) {
            $forms['other'] = end($forms) ?: '';
        }

        $placeholders['count'] = 'int';

        $body = [];

        foreach ($forms as $selector => $text) {
            $body[] = $selector.'{'.$convert($text).'}';
        }

        return '{count, plural, '.implode(' ', $body).'}';
    }

    // --- Communs -----------------------------------------------------------------------------------------------------

    /** Une chaîne Laravel est un pluriel si elle contient « | » (aucun texte de l'interface n'en contient autrement). */
    private function isPlural(string $value): bool
    {
        return str_contains($value, '|');
    }

    private function stripPluralPrefix(string $segment): string
    {
        return trim((string) preg_replace('/^\s*(\{\d+\}|\[\d+,\s*(?:\d+|\*)\])\s*/', '', $segment));
    }

    /** @param  array<string, mixed>  $data */
    private function encode(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
    }
}
