<?php

declare(strict_types=1);

namespace App\Application\Auth\Support;

use App\Support\Settings;
use Illuminate\Support\Carbon;

/**
 * Ouverture des inscriptions réglée par l'administration (FR-AUTH-06, Q26) : interrupteur `registrations_open`,
 * plage horaire quotidienne facultative (`reg_window_on`, `reg_window_start`, `reg_window_end`), message de fermeture
 * (≤ 300 caractères), date de réouverture (`reg_reopen_at`) et validation manuelle (`reg_manual_validation`, Q26).
 * Heures de la plage à l'heure du Bénin (`xomiton.display_timezone`, réglées par P12-02) ; la date de réouverture est affichée telle que saisie.
 */
final class RegistrationGate
{
    private function __construct() {}

    public static function isOpen(?Carbon $at = null): bool
    {
        if (! Settings::bool('registrations_open', true)) {
            return false;
        }

        if (! Settings::bool('reg_window_on', false)) {
            return true;
        }

        // La plage est saisie à l'heure du Bénin (P12-02) : la base et `app.timezone` restent en UTC.
        $at = ($at ?? now())->copy()->setTimezone(self::timezone());
        $start = self::minutes(Settings::string('reg_window_start', '00:00'));
        $end = self::minutes(Settings::string('reg_window_end', '23:59'));
        $current = $at->hour * 60 + $at->minute;

        // Plage qui enjambe minuit (22:00 → 06:00) : ouverte avant la fin ou après le début.
        return $start <= $end ? ($current >= $start && $current < $end) : ($current >= $start || $current < $end);
    }

    public static function closedMessage(): string
    {
        $message = trim(Settings::string('reg_closed_msg'));

        return mb_substr($message !== '' ? $message : (string) __('auth.registrations_closed'), 0, 300);
    }

    public static function reopensAt(): ?Carbon
    {
        $raw = trim(Settings::string('reg_reopen_at'));

        if ($raw === '') {
            return null;
        }

        try {
            // Date annoncée, affichée telle que saisie (heure murale du Bénin) : jamais convertie.
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Validation manuelle (Q26) : `peers` (pair·es seulement, par défaut) ou `all` (tous les nouveaux comptes). */
    public static function manualValidation(): string
    {
        return Settings::string('reg_manual_validation', 'peers') === 'all' ? 'all' : 'peers';
    }

    private static function timezone(): string
    {
        return (string) config('xomiton.display_timezone', 'UTC');
    }

    private static function minutes(string $time): int
    {
        if (preg_match('/^(\d{1,2}):(\d{2})$/', trim($time), $m) !== 1) {
            return 0;
        }

        return min(23, (int) $m[1]) * 60 + min(59, (int) $m[2]);
    }
}
