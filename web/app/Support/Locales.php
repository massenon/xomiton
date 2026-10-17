<?php

declare(strict_types=1);

namespace App\Support;

use Locale;
use ResourceBundle;

/**
 * Langues de la plateforme (NFR-11, Q40) : locales complètes (`config('xomiton.locales')`), locales prévues
 * (`locales_planned`) et résolution de la locale intl (ICU) avec repli — `fr-BJ` puis `fr-FR` (Q36).
 * Usage : `Locales::isAvailable('fon')`, `Locales::intlTag()` → `fr_BJ`, `Locales::bcp47()` → `fr-BJ`.
 */
final class Locales
{
    /** @var list<string>|null Locales ICU disponibles sur le serveur (chargées une fois). */
    private static ?array $icuLocales = null;

    /** @var array<string, string> Cache des résolutions locale → tag ICU. */
    private static array $resolved = [];

    private function __construct() {}

    /** @return list<string> */
    public static function available(): array
    {
        return array_values(array_map('strval', (array) config('xomiton.locales', ['fr'])));
    }

    /** @return list<string> */
    public static function planned(): array
    {
        return array_values(array_map('strval', (array) config('xomiton.locales_planned', [])));
    }

    /** @return list<string> Locales complètes puis prévues, sans doublon. */
    public static function all(): array
    {
        return array_values(array_unique([...self::available(), ...self::planned()]));
    }

    public static function default(): string
    {
        $default = (string) config('xomiton.default_locale', 'fr');

        return in_array($default, self::available(), true) ? $default : (self::available()[0] ?? 'fr');
    }

    public static function isAvailable(string $locale): bool
    {
        return in_array($locale, self::available(), true);
    }

    public static function isPlanned(string $locale): bool
    {
        return in_array($locale, self::planned(), true);
    }

    /**
     * Locale courante de l'application si elle est complète, sinon la locale par défaut.
     */
    public static function current(): string
    {
        $locale = app()->getLocale();

        return self::isAvailable($locale) ? $locale : self::default();
    }

    /**
     * Tag ICU (`fr_BJ`) de la première locale intl disponible pour la langue donnée, parmi
     * `config('xomiton.intl_locales.<locale>')`. Si aucune n'est connue d'ICU, le dernier candidat est retourné
     * canonisé (ICU retombera lui-même sur la langue racine).
     */
    public static function intlTag(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        if (isset(self::$resolved[$locale])) {
            return self::$resolved[$locale];
        }

        $candidates = array_values(array_filter(array_map(
            'strval',
            (array) config('xomiton.intl_locales.'.$locale, [$locale]),
        )));

        if ($candidates === []) {
            $candidates = [$locale];
        }

        foreach ($candidates as $candidate) {
            $match = Locale::lookup(self::icuLocales(), $candidate, true, '');

            if (is_string($match) && $match !== '') {
                return self::$resolved[$locale] = (string) Locale::canonicalize($match);
            }
        }

        return self::$resolved[$locale] = (string) Locale::canonicalize((string) end($candidates));
    }

    /**
     * Tag BCP 47 (`fr-BJ`) pour `Intl.*` côté JavaScript et `lang`/`hreflang` HTML.
     */
    public static function bcp47(?string $locale = null): string
    {
        return str_replace('_', '-', self::intlTag($locale));
    }

    /**
     * Valeur de `<html lang>` : la langue seule (`fr`), le pays n'apporte rien aux lecteurs d'écran.
     */
    public static function htmlLang(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return (string) (Locale::getPrimaryLanguage($locale) ?: $locale);
    }

    /** @return list<string> */
    private static function icuLocales(): array
    {
        if (self::$icuLocales === null) {
            $locales = ResourceBundle::getLocales('');
            self::$icuLocales = is_array($locales) ? array_values(array_map('strval', $locales)) : [];
        }

        return self::$icuLocales;
    }
}
