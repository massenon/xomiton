<?php

declare(strict_types=1);

namespace App\Application\Legal;

use App\Support\Settings;

/**
 * Textes des pages légales (lang/fr/pages/legal.php) : remplace les jetons `{nom}` par les valeurs réelles de la
 * configuration, pour que les durées et les prestataires affichés ne divergent jamais du code.
 *
 *  - durées : lues dans `xomiton.accounts`, `xomiton.retention`, `session` ;
 *  - identité de l'éditeur : `xomiton.legal.*` (variables XOMITON_LEGAL_*) ; un champ vide devient le marqueur visible
 *    « [À COMPLÉTER PAR LE CLIENT : …] », mis en surbrillance par la vue ;
 *  - prestataires : SMS (`xomiton.sms.driver` : vonage, twilio, sinon aucun envoi réel), assistant (`xomiton.assistant.llm_enabled` :
 *    Gemini désactivé par défaut, la phrase change si l'équipe l'active).
 */
final class LegalTexts
{
    public const MARKER = 'À COMPLÉTER PAR LE CLIENT';

    /** Champs d'identité à fournir, dans l'ordre de la liste de la documentation (clé => clé de config). */
    public const FIELDS = [
        'publisher_name', 'legal_form', 'registration', 'address', 'director', 'editor_phone',
        'host_name', 'host_address', 'host_country', 'dpo_name', 'dpo_email', 'jurisdiction',
    ];

    /**
     * @param  array<array-key, mixed>  $content
     * @return array<array-key, mixed>
     */
    public static function resolve(array $content): array
    {
        $tokens = self::tokens();

        return self::replace($content, array_combine(
            array_map(static fn (string $key): string => '{'.$key.'}', array_keys($tokens)),
            array_values($tokens),
        ));
    }

    /** @return list<string> Clés d'identité encore vides (à compléter par le client). */
    public static function missing(): array
    {
        return array_values(array_filter(self::FIELDS, static fn (string $key): bool => self::field($key) === null));
    }

    /** @return array<string, string> */
    public static function tokens(): array
    {
        $tokens = [
            'site_name' => (string) __('seo.site_name'),
            'contact_email' => Settings::string('contact_email', (string) config('mail.from.address', 'contact@xomiton.bj')),
            'min_age' => (string) (int) config('xomiton.accounts.min_age', 15),
            'grace_days' => (string) (int) config('xomiton.accounts.deletion_grace_days', 30),
            'export_hours' => (string) (int) config('xomiton.accounts.export_link_hours', 48),
            'request_hours' => (string) (int) config('xomiton.accounts.deletion_request_hours', 24),
            'audit_days' => (string) (int) config('xomiton.retention.audit_log_days', 90),
            'mail_days' => (string) (int) config('xomiton.retention.mail_log_days', 180),
            'failed_mail_days' => (string) (int) config('xomiton.retention.mail_outbox_failed_days', 7),
            'otp_hours' => (string) (int) config('xomiton.retention.otp_hours', 24),
            'case_years' => (string) (int) config('xomiton.retention.sensitive_years_after_case', 5),
            'device_days' => (string) (int) config('xomiton.retention.device_inactive_days', 180),
            'sync_days' => (string) (int) config('xomiton.retention.sync_receipt_days', 90),
            'session_minutes' => (string) (int) config('session.lifetime', 120),
            'remember_days' => (string) \App\Application\Auth\DeviceTrust::TTL_DAYS,
            'events_days' => (string) (int) config('xomiton.content_events.retention_days', 30),
            'draft_days' => (string) (int) config('xomiton.alerts.draft_days', 7),
            'session_cookie' => (string) config('session.cookie', 'laravel_session'),
            'sms_clause' => self::smsClause(),
            'assistant_clause' => (string) __('pages/legal.clauses.assistant_'.((bool) config('xomiton.assistant.llm_enabled', false) ? 'llm' : 'kb')),
        ];

        foreach (self::FIELDS as $key) {
            $tokens[$key] = self::field($key) ?? self::marker((string) __('pages/legal.fields.'.$key));
        }

        return $tokens;
    }

    public static function marker(string $label): string
    {
        return '['.self::MARKER.' : '.$label.']';
    }

    private static function field(string $key): ?string
    {
        $value = config('xomiton.legal.'.$key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private static function smsClause(): string
    {
        $driver = strtolower((string) config('xomiton.sms.driver', 'log'));

        return (string) __('pages/legal.clauses.sms_'.(in_array($driver, ['vonage', 'twilio'], true) ? $driver : 'none'));
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @param  array<string, string>  $map
     * @return array<array-key, mixed>
     */
    private static function replace(array $value, array $map): array
    {
        foreach ($value as $key => $item) {
            $value[$key] = match (true) {
                is_array($item) => self::replace($item, $map),
                is_string($item) => strtr($item, $map),
                default => $item,
            };
        }

        return $value;
    }
}
