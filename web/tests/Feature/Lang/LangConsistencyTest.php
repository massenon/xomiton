<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Lang;
use Symfony\Component\Finder\Finder;

/*
 * Cohérence des textes (P3-05) : toute clé référencée par __() / trans() / trans_choice() / @lang() dans
 * resources/views et app existe dans lang/fr (clés dynamiques ignorées) ; aucun « dénonciation » affiché (Q29) ;
 * aucune valeur vide ; deux clés « Session expirée » (Q36) ; aucune promesse « 24 h/24 » sans réponse humaine
 * annoncée ; lang/en ne contient que des clés connues de lang/fr ; config('xomiton.locales') = ['fr'] (Q40).
 */

/**
 * Toutes les lignes de lang/<locale> à plat (« fichier.cle.souscle » => valeur), fichiers JSON compris.
 *
 * @return array<string, mixed>
 */
function flattenLang(string $locale): array
{
    $lines = [];
    $directory = lang_path($locale);

    $flatten = function (array $array, string $prefix) use (&$lines, &$flatten): void {
        foreach ($array as $key => $value) {
            $flat = $prefix.'.'.$key;
            is_array($value) ? $flatten($value, $flat) : $lines[$flat] = $value;
        }
    };

    foreach (Finder::create()->files()->in($directory)->name('*.php')->sortByName() as $file) {
        $group = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getRelativePathname(), 0, -4));
        $flatten(require $file->getRealPath(), $group);
    }

    if (is_file(lang_path($locale.'.json'))) {
        foreach (json_decode((string) file_get_contents(lang_path($locale.'.json')), true) ?? [] as $key => $value) {
            $lines[(string) $key] = $value;
        }
    }

    return $lines;
}

/**
 * Clés littérales référencées dans les vues et le code ; les clés dynamiques (concaténation, variable, clé se
 * terminant par un point) sont ignorées.
 *
 * @return array<string, list<string>> clé => fichiers qui l'utilisent
 */
function referencedLangKeys(): array
{
    $keys = [];
    $pattern = '/(?:__|trans|trans_choice|@lang|Lang::get|Lang::has|Lang::choice)\(\s*([\'"])([^\'"\$]+)\1\s*[,)]/u';

    $finder = Finder::create()->files()
        ->in([resource_path('views'), app_path()])
        ->name(['*.php', '*.blade.php']);

    foreach ($finder as $file) {
        if (preg_match_all($pattern, $file->getContents(), $matches) === 0) {
            continue;
        }

        foreach ($matches[2] as $key) {
            if ($key === '' || str_ends_with($key, '.') || str_contains($key, '$')) {
                continue;
            }

            $keys[$key][] = $file->getRelativePathname();
        }
    }

    ksort($keys);

    return $keys;
}

it('trouve dans lang/fr toutes les clés référencées par __() dans les vues et le code', function (): void {
    $referenced = referencedLangKeys();
    $missing = [];

    foreach (array_keys($referenced) as $key) {
        if (! Lang::has($key, 'fr', false)) {
            $missing[$key] = implode(', ', array_unique($referenced[$key]));
        }
    }

    expect($referenced)->not->toBeEmpty()
        ->and($missing)->toBe([], 'Clés manquantes dans lang/fr : '.json_encode($missing, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
});

it('n’affiche jamais « dénonciation » : à l’écran, c’est un signalement (Q29)', function (): void {
    $offenders = collect(flattenLang('fr'))
        ->filter(fn (mixed $value): bool => is_string($value) && preg_match('/d[ée]nonciation/iu', $value) === 1)
        ->keys()
        ->all();

    expect($offenders)->toBe([])
        ->and(__('enums/alerts.alert_type.denonciation'))->toBe('Signalement')
        ->and(__('components.status.alert_type.denonciation.label'))->toBe('Signalement');
});

it('ne contient aucune clé vide ni valeur non textuelle inattendue', function (): void {
    $lines = flattenLang('fr');
    $empty = [];
    $wrongType = [];

    foreach ($lines as $key => $value) {
        if (is_string($value)) {
            if (trim($value) === '') {
                $empty[] = $key;
            }
        } elseif (! is_bool($value) && ! is_int($value)) {
            // components.status.* porte des drapeaux booléens (soft) ; tout le reste doit être du texte.
            $wrongType[$key] = get_debug_type($value);
        }
    }

    expect($lines)->not->toBeEmpty()
        ->and($empty)->toBe([], 'Valeurs vides : '.implode(', ', $empty))
        ->and($wrongType)->toBe([]);
});

it('porte exactement deux clés « Session expirée » : publique (vous) et connectée (tu) (Q36)', function (): void {
    $sessionKeys = collect(flattenLang('fr'))
        ->filter(fn (mixed $value): bool => is_string($value) && str_contains($value, 'session a expiré'))
        ->keys()
        ->sort()
        ->values()
        ->all();

    expect($sessionKeys)->toBe(['errors.419.member.text', 'errors.419.public.text'])
        ->and(__('errors.419.public.text'))->toStartWith('Votre session a expiré, veuillez réessayer.')
        ->and(__('errors.419.member.text'))->toStartWith('Ta session a expiré.')
        ->and(__('errors.419.public.title'))->toBe(__('errors.419.member.title'));
});

it('ne promet jamais « 24 h/24 » sans annoncer une réponse humaine sous 24 h (Q36)', function (): void {
    $offenders = collect(flattenLang('fr'))
        ->filter(fn (mixed $value): bool => is_string($value)
            && preg_match('/24\s?h\s?\/\s?24|24\s?\/\s?7|7\s?j\s?\/\s?7|7j\/7/iu', $value) === 1
            && ! str_contains($value, 'sous 24 h'))
        ->keys()
        ->all();

    expect($offenders)->toBe([])
        ->and(__('alerts.report.done.text'))->toBe('Une personne formée le lira sous 24 h.');
});

it('écrit le sigle LBTQ+ d’une seule façon', function (): void {
    $offenders = collect(flattenLang('fr'))
        ->filter(fn (mixed $value): bool => is_string($value) && preg_match('/\bL(?:G?B|B)TQ?I?A?\+?\b/u', $value) === 1 && ! str_contains($value, 'LBTQ+'))
        ->keys()
        ->all();

    expect($offenders)->toBe([]);
});

it('ne liste comme complète que la locale française et annonce en, fon et yo (Q40)', function (): void {
    expect(config('xomiton.locales'))->toBe(['fr'])
        ->and(config('xomiton.locales_planned'))->toBe(['en', 'fon', 'yo'])
        ->and(config('xomiton.default_locale'))->toBe('fr')
        ->and(is_dir(lang_path('fr')))->toBeTrue();

    foreach (config('xomiton.locales_planned') as $locale) {
        expect(is_dir(lang_path($locale)))->toBeTrue("lang/$locale absent")
            ->and(in_array($locale, config('xomiton.locales'), true))->toBeFalse();
    }

    expect(is_file(lang_path('fon/README.md')))->toBeTrue()
        ->and(is_file(lang_path('yo/README.md')))->toBeTrue()
        ->and(__('profile.settings.language.options.fon'))->toBe('Fɔngbe');
});

it('exige qu’une locale listée comme complète couvre toutes les clés de lang/fr (Q40)', function (): void {
    $fr = array_keys(flattenLang('fr'));

    foreach (config('xomiton.locales') as $locale) {
        if ($locale === 'fr') {
            continue;
        }

        $missing = array_values(array_diff($fr, array_keys(flattenLang($locale))));

        expect($missing)->toBe([], sprintf('lang/%s est listée dans xomiton.locales mais il lui manque %d clés : %s', $locale, count($missing), implode(', ', array_slice($missing, 0, 20))));
    }

    // Preuve que le contrôle mordrait : lang/en est incomplète, donc absente de xomiton.locales.
    expect(array_diff($fr, array_keys(flattenLang('en'))))->not->toBeEmpty()
        ->and(config('xomiton.locales'))->not->toContain('en');
});

it('garde lang/en comme sous-ensemble strict de lang/fr (mêmes clés, traductions non vides)', function (): void {
    $fr = flattenLang('fr');
    $en = flattenLang('en');
    $orphans = array_values(array_diff(array_keys($en), array_keys($fr)));
    $empty = array_keys(array_filter($en, fn (mixed $value): bool => is_string($value) && trim($value) === ''));

    expect($en)->not->toBeEmpty()
        ->and($orphans)->toBe([], 'Clés de lang/en absentes de lang/fr : '.implode(', ', $orphans))
        ->and($empty)->toBe([])
        ->and(array_keys(array_filter(array_keys($en), fn (string $key): bool => ! str_starts_with($key, 'errors.') && ! str_starts_with($key, 'common.'))))->toBe([])
        ->and(__('errors.404.title', [], 'en'))->toBe('Oops, this page cannot be found')
        ->and(__('feed.title', [], 'en'))->toBe(__('feed.title', [], 'fr'));
});

it('livre les fichiers attendus par P3-05 avec les textes de référence de MICROCOPY.md', function (): void {
    foreach (['microcopy', 'buttons', 'errors', 'notifications', 'alerts', 'feed', 'community', 'profile', 'admin', 'newsletter', 'dates'] as $file) {
        expect(is_file(lang_path("fr/$file.php")))->toBeTrue("lang/fr/$file.php manquant");
    }

    expect(__('admin.datatable.showing', ['from' => 1, 'to' => 10, 'total' => 57]))->toBe('Affichage de 1 à 10 sur 57 entrées')
        ->and(__('microcopy.empty.feed'))->toBe('Rien ici pour l’instant. Sois la première personne à publier dans cette catégorie.')
        ->and(__('microcopy.empty.comments'))->toBe('Aucun commentaire. Écris le premier, avec bienveillance.')
        ->and(__('microcopy.error.network'))->toBe('Tu es hors connexion. Ton message partira dès que le réseau revient.')
        ->and(__('alerts.sos.button_help'))->toBe('Maintiens le bouton 2 secondes pour envoyer ton alerte.')
        ->and(__('feed.toasts.published'))->toBe('Ta publication est en ligne.')
        ->and(__('feed.poll.voted'))->toBe('Merci, ton vote est enregistré.')
        ->and(__('newsletter.public.subscribed_short'))->toBe('Merci'."\u{00A0}".'! Un e-mail de confirmation vient de vous être envoyé.')
        ->and(__('profile.progressive.why'))->toBe('Pourquoi on demande ça'."\u{00A0}".'?')
        ->and(__('notifications.new_message.discreet'))->toBe('Nouveau message')
        ->and(trans_choice('feed.poll.votes', 1))->toBe('1 vote')
        ->and(trans_choice('feed.poll.votes', 9))->toBe('9 votes')
        ->and(trans_choice('feed.poll.participants', 3))->toBe('3 participant·es')
        ->and(__('dates.months.11'))->toBe('novembre');
});
