<?php

declare(strict_types=1);

namespace App\Application\Admin\Registrations;

use App\Application\Auth\Support\RegistrationGate;
use App\Models\Setting;
use App\Models\User;
use App\Support\Audit;
use App\Support\Settings;
use Illuminate\Support\Facades\Gate;

/**
 * Réglages des inscriptions (P12-02, FR-AUTH-06, Q26) lus par RegistrationGate (page des inscriptions fermées, P2-03) :
 * `registrations_open` (interrupteur), `reg_manual_validation` (`peers` : pair·es seulement, par défaut ; `all` : tous les
 * nouveaux comptes), `reg_closed_msg` (≤ 300 caractères), `reg_reopen_at` (date de réouverture annoncée, heure du Bénin,
 * informative), plage horaire quotidienne `reg_window_on` / `reg_window_start` / `reg_window_end` (heure du Bénin).
 * Les clés historiques du prototype sont gardées (le plan écrit `registration.open` : plan Q91). Écriture réservée à
 * `registrations.manage` ; chaque changement est journalisé (`registrations.opened`, `registrations.closed`,
 * `registrations.options_updated`). La validation des pair·es (RG-03) reste obligatoire quel que soit le mode.
 */
final class RegistrationSettings
{
    public const MESSAGE_MAX = 300;

    public const MANUAL_PEERS = 'peers';

    public const MANUAL_ALL = 'all';

    /**
     * @return array{open: bool, manual: string, message: string, reopen_at: string, window_on: bool, window_start: string, window_end: string}
     */
    public static function current(): array
    {
        $raw = trim(Settings::string('reg_reopen_at'));

        return [
            'open' => Settings::bool('registrations_open', true),
            'manual' => RegistrationGate::manualValidation(),
            'message' => Settings::string('reg_closed_msg'),
            'reopen_at' => $raw,
            'window_on' => Settings::bool('reg_window_on', false),
            'window_start' => Settings::string('reg_window_start', '08:00'),
            'window_end' => Settings::string('reg_window_end', '20:00'),
        ];
    }

    public function open(bool $open, User $by): void
    {
        Gate::forUser($by)->authorize('registrations.manage');

        if ($open === Settings::bool('registrations_open', true)) {
            return;
        }

        $this->put('registrations_open', Setting::TYPE_BOOL, $open, true, $by);
        Audit::log($open ? 'registrations.opened' : 'registrations.closed', null, null, $by);
    }

    /**
     * @param  array{manual: string, message: string, reopen_at: string, window_on: bool, window_start: string, window_end: string}  $options
     */
    public function saveOptions(array $options, User $by): void
    {
        Gate::forUser($by)->authorize('registrations.manage');

        $this->put('reg_manual_validation', Setting::TYPE_STRING, $options['manual'] === self::MANUAL_ALL ? self::MANUAL_ALL : self::MANUAL_PEERS, false, $by);
        $this->put('reg_closed_msg', Setting::TYPE_STRING, mb_substr(trim($options['message']), 0, self::MESSAGE_MAX), true, $by);
        $this->put('reg_reopen_at', Setting::TYPE_STRING, trim($options['reopen_at']) === '' ? null : trim($options['reopen_at']), true, $by);
        $this->put('reg_window_on', Setting::TYPE_BOOL, $options['window_on'], true, $by);
        $this->put('reg_window_start', Setting::TYPE_STRING, $options['window_start'], true, $by);
        $this->put('reg_window_end', Setting::TYPE_STRING, $options['window_end'], true, $by);

        Audit::log('registrations.options_updated', null, sprintf(
            'validation=%s ; plage=%s',
            $options['manual'],
            $options['window_on'] ? $options['window_start'].'-'.$options['window_end'] : 'non',
        ), $by);
    }

    private function put(string $key, string $type, mixed $value, bool $public, User $by): void
    {
        $setting = Setting::query()->firstOrNew(['key' => $key]);

        if (! $setting->exists) {
            $setting->group = Setting::GROUP_REGISTRATIONS;
            $setting->type = $type;
            $setting->is_public = $public;
        }

        $setting->value = $value;
        $setting->updated_by = $by->getKey();
        $setting->save();
    }
}
