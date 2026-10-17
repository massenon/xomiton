<?php

declare(strict_types=1);

namespace App\Application\Platform;

use App\Models\MaintenanceWindow;
use App\Models\User;
use App\Support\Settings;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Carbon;

/**
 * État du mode maintenance applicatif (RG-14, P2-11, pas `artisan down`) : bascule manuelle `maintenance` des
 * paramètres **ou** fenêtre `maintenance_windows` active et en cours. Point d'accès unique, lu par le middleware
 * EnsureNotInMaintenance, par AuthenticateUser (MaintenanceActive) et par la page 503 (message, dates).
 */
final class MaintenanceStatus
{
    /** Clés des paramètres (settings, groupe maintenance) : bascule manuelle, message, dates annoncées. */
    public const SETTING_FLAG = 'maintenance';

    public const SETTING_MESSAGE = 'maint_msg';

    public const SETTING_START = 'maint_start';

    public const SETTING_END = 'maint_end';

    /** Rôles qui se connectent et travaillent pendant la maintenance (RG-14). */
    public const ALLOWED_ROLES = ['admin', 'superadmin'];

    private function __construct() {}

    /**
     * La maintenance est-elle active (bascule manuelle ou fenêtre en cours) ?
     */
    public static function active(?Carbon $at = null): bool
    {
        if (Settings::bool(self::SETTING_FLAG, false)) {
            return true;
        }

        return MaintenanceWindow::isUnderMaintenance($at);
    }

    /**
     * La personne peut-elle utiliser la plateforme malgré la maintenance ?
     */
    public static function isExempt(?User $user): bool
    {
        return $user !== null && $user->hasAnyRole(self::ALLOWED_ROLES);
    }

    /**
     * Fenêtre active en cours, la plus récemment commencée en premier (null si seule la bascule est levée).
     */
    public static function currentWindow(?Carbon $at = null): ?MaintenanceWindow
    {
        return MaintenanceWindow::query()->ongoing($at)->orderByDesc('starts_at')->orderByDesc('id')->first();
    }

    /**
     * Photographie complète (une seule lecture de la fenêtre en cours) : état, message, dates.
     */
    public static function snapshot(?Carbon $at = null): MaintenanceSnapshot
    {
        $window = self::currentWindow($at);
        $active = $window !== null || Settings::bool(self::SETTING_FLAG, false);

        return new MaintenanceSnapshot(
            active: $active,
            window: $window,
            message: self::message($window),
            startsAt: self::startsAt($window),
            endsAt: self::endsAt($window, $at),
        );
    }

    /**
     * Message affiché aux visiteur·ses : celui de la fenêtre en cours, sinon `maint_msg`, sinon le texte par défaut.
     */
    public static function message(?MaintenanceWindow $window): string
    {
        if ($window !== null && trim((string) $window->message) !== '') {
            return $window->displayMessage();
        }

        $custom = trim(Settings::string(self::SETTING_MESSAGE, ''));

        return $custom !== '' ? $custom : (string) __('maintenance.default_message');
    }

    /**
     * Début de la maintenance en cours (fenêtre, sinon paramètre `maint_start`), si connu.
     */
    public static function startsAt(?MaintenanceWindow $window): ?Carbon
    {
        return $window?->starts_at ?? self::parseDate(Settings::string(self::SETTING_START, ''));
    }

    /**
     * Reprise prévue (fenêtre, sinon paramètre `maint_end`), si connue et à venir.
     */
    public static function endsAt(?MaintenanceWindow $window, ?Carbon $at = null): ?Carbon
    {
        $end = $window?->ends_at ?? self::parseDate(Settings::string(self::SETTING_END, ''));

        return $end !== null && $end->gt($at ?? now()) ? $end : null;
    }

    private static function parseDate(string $value): ?Carbon
    {
        if (trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (InvalidFormatException) {
            return null;
        }
    }
}
