<?php

declare(strict_types=1);

namespace App\Application\Platform;

use App\Application\Shared\KnowledgeBaseAnswerer;
use App\Support\LocaleFormatter;
use DateTimeZone;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Vite;
use Throwable;

/**
 * Page publique de maintenance (P13-07) : données de la vue `maintenance` (document autonome, sans manifest Vite :
 * CSS en ligne resources/views/maintenance/*.css minifié, logo et illustration SVG en ligne, script en ligne avec nonce
 * CSP). Utilisée par EnsureNotInMaintenance (503), errors/503 (variante `generic` : panne, aucune promesse sur le SOS en
 * ligne), l'aperçu « voir comme un visiteur » et l'aperçu en direct de l'écran d'administration (P13-06 : même
 * contenu, `maintenance.content`).
 * Heures dans le fuseau d'affichage `xomiton.display_timezone` (Bénin, UTC+1 ; la base reste en UTC), en clair et
 * lisibles sans JavaScript : « aujourd’hui à 16 h 30 », « le lundi 2 novembre 2026 à 9 h » ; le compte à rebours, le
 * dépassement de la reprise annoncée et la vérification de réouverture (GET /api/v1/status, toutes les 60 s) sont un plus.
 */
final class MaintenancePage
{
    public const VIEW = 'maintenance';

    /** Intervalle de vérification de la réouverture (secondes). */
    public const POLL_SECONDS = 60;

    /** Seuils d'annonce du temps restant à la zone `aria-live` (minutes), puis dépassement et réouverture. */
    public const ANNOUNCE_MINUTES = [60, 30, 15, 5];

    /** Règle du thème sombre explicite de content.css, dont la page publique dérive la préférence du système. */
    private const DARK_SELECTOR = '[data-bs-theme="dark"] .xmm:not([data-xm-theme="light"]),.xmm[data-xm-theme="dark"]{';

    /** @var array<string, string> */
    private static array $styles = [];

    private function __construct() {}

    /**
     * Réponse 503 (Retry-After posé par l'appelant), jamais mise en cache.
     */
    public static function response(MaintenanceSnapshot $snapshot, bool $disconnected = false): Response
    {
        return response()
            ->view(self::VIEW, self::data($snapshot, $disconnected), 503)
            ->header('Cache-Control', 'no-store');
    }

    /**
     * @return array<string, mixed>
     */
    public static function data(MaintenanceSnapshot $snapshot, bool $disconnected = false, bool $preview = false, bool $generic = false): array
    {
        $startsAt = $generic ? null : $snapshot->startsAt;
        $endsAt = $generic ? null : $snapshot->endsAt;
        $overdue = ! $generic && $endsAt === null && $snapshot->overdue;
        $format = self::formatter();
        $progress = null;

        if ($startsAt !== null && $endsAt !== null && $endsAt->gt($startsAt)) {
            $elapsed = $startsAt->diffInSeconds(now(), false);
            $progress = (int) round(max(0, min(100, $elapsed / max(1, $startsAt->diffInSeconds($endsAt)) * 100)));
        }

        $numbers = array_map(static fn (array $entry): array => $entry + [
            'tel' => KnowledgeBaseAnswerer::telHref($entry['number']),
        ], MaintenanceStatus::emergencyNumbers());

        return [
            'message' => $snapshot->message,
            'disconnected' => $disconnected,
            'preview' => $preview,
            'generic' => $generic,
            'overdue' => $overdue,
            'pageTitle' => __($generic ? 'maintenance.generic.page_title' : 'maintenance.page_title'),
            'metaDescription' => __($generic ? 'maintenance.generic.meta_description' : 'maintenance.meta_description'),
            'eyebrow' => __($generic ? 'maintenance.generic.eyebrow' : 'maintenance.eyebrow'),
            'titleLead' => __($generic ? 'maintenance.generic.title_lead' : 'maintenance.title_lead'),
            'titleMark' => __($generic ? 'maintenance.generic.title_mark' : 'maintenance.title_mark'),
            'helpText' => $generic ? self::genericHelp($numbers) : __('maintenance.always_available.text'),
            'startsAt' => $startsAt,
            'endsAt' => $endsAt,
            'startsIso' => $startsAt?->toIso8601String(),
            'endsIso' => $endsAt?->toIso8601String(),
            'sinceText' => $startsAt === null ? null : __('maintenance.since', ['when' => self::when($startsAt)]),
            'untilText' => match (true) {
                $generic => __('maintenance.generic.until'),
                $overdue => __('maintenance.status.overdue'),
                $endsAt === null => __('maintenance.until_unknown'),
                default => __('maintenance.until', ['when' => self::when($endsAt)]),
            },
            'endsTime' => $endsAt === null ? null : $format->time($endsAt),
            'endsDay' => $endsAt === null ? null : self::day($endsAt),
            'startsTime' => $startsAt === null ? null : $format->time($startsAt),
            'progress' => $progress,
            'numbers' => $numbers,
            'statusUrl' => $generic || $preview ? null : route('api.status', absolute: false),
            'pollSeconds' => self::POLL_SECONDS,
            'announceMinutes' => self::ANNOUNCE_MINUTES,
            'reloadUrl' => $preview ? null : request()->fullUrl(),
            'fontUrl' => self::fontUrl(),
            'nonce' => Vite::cspNonce(),
            'css' => self::pageCss(),
        ];
    }

    /**
     * Fuseau d'affichage des heures publiques (`xomiton.display_timezone`, Bénin par défaut), repli `app.timezone` si
     * la valeur configurée n'est pas un fuseau connu.
     */
    public static function timezone(): string
    {
        $timezone = (string) config('xomiton.display_timezone', '');

        return in_array($timezone, DateTimeZone::listIdentifiers(), true) ? $timezone : (string) config('app.timezone', 'UTC');
    }

    /** Formateur des heures publiques (locale de l'application, fuseau d'affichage). */
    public static function formatter(): LocaleFormatter
    {
        return LocaleFormatter::make(null, self::timezone());
    }

    /** Date dans le fuseau d'affichage (champs datetime-local de l'administration, pastilles de date). */
    public static function local(Carbon $date): Carbon
    {
        return $date->copy()->setTimezone(self::timezone());
    }

    /**
     * CSS minifié d'une partie (`page` : document ; `content` : bloc `.xmm` seul, réutilisé par l'aperçu
     * d'administration). Lu sur le disque, jamais par Vite : la page s'affiche même sans build.
     */
    public static function css(string $part = 'content'): string
    {
        return self::$styles[$part] ??= self::minify((string) @file_get_contents(resource_path("views/maintenance/{$part}.css")));
    }

    /**
     * CSS complet du document public : document, bloc, et préférence sombre du système dérivée de la règle sombre
     * explicite (jetons écrits une seule fois ; l'aperçu d'administration et l'écran membre suivent le thème choisi).
     */
    public static function pageCss(): string
    {
        return self::$styles['__page'] ??= self::css('page').self::css('content').self::systemDark(self::css('content'));
    }

    /** « aujourd’hui à 16 h 30 », « demain à 9 h », « le lundi 2 novembre 2026 à 9 h ». */
    public static function when(Carbon $date): string
    {
        $local = self::local($date);
        $today = now()->setTimezone($local->getTimezone());
        $time = self::formatter()->time($local);

        return match (true) {
            $local->isSameDay($today) => __('maintenance.when.today', ['time' => $time]),
            $local->isSameDay($today->copy()->addDay()) => __('maintenance.when.tomorrow', ['time' => $time]),
            default => __('maintenance.when.date', ['date' => self::day($local), 'time' => $time]),
        };
    }

    /** « lundi 2 novembre 2026 », « dimanche 1er novembre 2026 ». */
    public static function day(Carbon $date): string
    {
        $format = self::formatter();
        $local = self::local($date);
        $day = $local->day === 1 ? (string) __('maintenance.day_first') : (string) $local->day;

        return $format->dayName($local->dayOfWeekIso).' '.$day.' '.$format->monthName($local->month).' '.$local->year;
    }

    /**
     * Bandeau d'annonce préalable (P13-08) pour les gabarits public, membre Vue et administration : textes neutres déjà
     * composés (« Une maintenance est prévue aujourd’hui de 14 h à 16 h… »), clé de fermeture par fenêtre.
     *
     * @return array{id: int, key: string, title: string, text: string, message: ?string, starts_at: string, ends_at: string}|null
     */
    public static function notice(): ?array
    {
        $notice = MaintenanceStatus::notice();

        if ($notice === null) {
            return null;
        }

        return [
            'id' => $notice['id'],
            'key' => 'maint-'.$notice['id'].'-'.$notice['starts_at']->getTimestamp(),
            'title' => (string) __('components.maintenance_notice.title'),
            'text' => (string) __('components.maintenance_notice.text', ['range' => self::range($notice['starts_at'], $notice['ends_at'])]),
            'message' => $notice['message'],
            'starts_at' => $notice['starts_at']->toIso8601String(),
            'ends_at' => $notice['ends_at']->toIso8601String(),
        ];
    }

    /** « aujourd’hui de 14 h à 16 h », « le lundi 2 novembre 2026 de 14 h à 16 h », « du … à 22 h au … à 2 h ». */
    public static function range(Carbon $from, Carbon $to): string
    {
        $format = self::formatter();
        $from = self::local($from);
        $to = self::local($to);

        if ($from->isSameDay($to)) {
            $today = now()->setTimezone($from->getTimezone());
            $day = match (true) {
                $from->isSameDay($today) => (string) __('components.maintenance_notice.today'),
                $from->isSameDay($today->copy()->addDay()) => (string) __('components.maintenance_notice.tomorrow'),
                default => (string) __('components.maintenance_notice.on_day', ['day' => self::day($from)]),
            };

            return (string) __('components.maintenance_notice.same_day', ['day' => $day, 'from' => $format->time($from), 'to' => $format->time($to)]);
        }

        return (string) __('components.maintenance_notice.span', [
            'from_day' => self::day($from), 'from' => $format->time($from), 'to_day' => self::day($to), 'to' => $format->time($to),
        ]);
    }

    /**
     * Bandeau prioritaire de la 503 générique (panne, base injoignable) : le SOS en ligne peut ne pas répondre, la page
     * renvoie d'abord vers les numéros d'urgence (« appelez directement le 117 ou le 118 »).
     *
     * @param  list<array{label: string, number: string, tel: string}>  $numbers
     */
    private static function genericHelp(array $numbers): string
    {
        $values = array_values(array_map(static fn (array $entry): string => $entry['number'], array_slice($numbers, 0, 2)));

        return match (count($values)) {
            0 => (string) __('maintenance.generic.help_text_none'),
            1 => (string) __('maintenance.generic.help_text_one', ['first' => $values[0]]),
            default => (string) __('maintenance.generic.help_text', ['first' => $values[0], 'second' => $values[1]]),
        };
    }

    /** Commentaires et espaces retirés (la page est servie telle quelle, sans build). */
    private static function minify(string $css): string
    {
        $css = (string) preg_replace('~/\*.*?\*/~s', '', $css);
        $css = (string) preg_replace('/\s+/', ' ', $css);

        return trim((string) preg_replace('/\s*([{};,>])\s*/', '$1', $css));
    }

    /** `@media (prefers-color-scheme:dark)` à partir de la règle sombre explicite (mêmes jetons, écrits une fois). */
    private static function systemDark(string $css): string
    {
        $start = strpos($css, self::DARK_SELECTOR);
        $end = $start === false ? false : strpos($css, '}', $start);

        if ($start === false || $end === false) {
            return '';
        }

        $body = substr($css, $start + strlen(self::DARK_SELECTOR), $end - $start - strlen(self::DARK_SELECTOR));

        return '@media (prefers-color-scheme:dark){:root:not([data-bs-theme="light"]) .xmm:not([data-xm-theme="light"]){'.$body.'}}';
    }

    /** Police Montserrat du build si le manifest existe ; sinon la page garde la pile système. */
    private static function fontUrl(): ?string
    {
        try {
            if (Vite::isRunningHot()) {
                return null;
            }

            return Vite::asset('resources/fonts/montserrat/Montserrat-latin-normal.woff2');
        } catch (Throwable) {
            return null;
        }
    }
}
