<?php

declare(strict_types=1);

/*
 * Réglages métier de Xomiton (DEVELOPMENT_STANDARDS.md §2 « Config »).
 * Aucun secret ici : seulement des valeurs de comportement, surchargeables par .env.
 */
return [

    /*
     * Alertes et SOS (FR-ALT-03 à FR-ALT-05, RG-04, RG-05).
     */
    'alerts' => [
        // Délai sans accusé de réception avant escalade au point focal national (minutes).
        'escalation_minutes' => (int) env('XOMITON_ESCALATION_MINUTES', 5),
        // Délai maximal par fournisseur pour les notifications envoyées dans la requête (secondes).
        'provider_timeout_seconds' => (float) env('XOMITON_PROVIDER_TIMEOUT', 3),
        // Nombre maximal de rejeux d'une notification SOS échouée par le cron.
        'max_retry_attempts' => (int) env('XOMITON_SOS_MAX_RETRIES', 10),
        // Longueur du code de suivi anonyme (alerts.tracking_code).
        'tracking_code_length' => 10,
    ],

    /*
     * Codes de vérification par e-mail (FR-AUTH-02, ADR-12).
     */
    'otp' => [
        'length' => 6,
        'ttl_minutes' => 10,
        'max_attempts' => 5,
        'resend_after_seconds' => 30,
        'max_sends_per_hour' => 5,
    ],

    /*
     * Limitation de débit (SOFTWARE_ARCHITECTURE.md §4, DEVELOPMENT_STANDARDS.md §7).
     */
    'rate_limits' => [
        'login_per_minute' => 5,
        'otp_per_hour' => 5,
        'report_per_hour_per_ip' => 10,
        'newsletter_seconds_between' => 20,
    ],

    /*
     * Comptes et profils.
     */
    'accounts' => [
        'password_min_length' => 10,
        'pseudo_min_length' => 3,
        'pseudo_max_length' => 40,
        // Effacement différé d'un compte supprimé, avant anonymisation (jours).
        'deletion_grace_days' => 30,
        'age_min' => 10,
        'age_max' => 99,
    ],

    /*
     * Réseau social.
     */
    'feed' => [
        'post_max_length' => 3000,
        'poll_question_min' => 5,
        'poll_question_max' => 200,
        'poll_options_min' => 2,
        'poll_options_max' => 6,
        'poll_option_max_length' => 80,
        'poll_durations_days' => [1, 3, 7, 14, 30, null],
        'image_max_kb' => 5120,
        // Contenus signalés masqués en attendant la modération (RG-16, proposée).
        'auto_hide_after_reports' => (int) env('XOMITON_AUTO_HIDE_REPORTS', 3),
        'messages_poll_seconds' => 4,
        'bell_poll_seconds' => 30,
        'duty_alerts_poll_seconds' => 10,
    ],

    /*
     * Dossiers de cas (RG-06 à RG-08).
     */
    'cases' => [
        'code_prefixes' => ['VBG', 'VDH'],
        'old_case_warning_years' => 5,
    ],

    /*
     * Newsletter (FR-COM-02, RG-11, RG-12, ADR-14).
     */
    'newsletter' => [
        'batch_size' => (int) env('XOMITON_NEWSLETTER_BATCH', 200),
        'confirmation_ttl_hours' => 48,
        'import_max_rows' => 5000,
    ],

    /*
     * Passerelles réseaux sociaux (D4-bis, ADR-15).
     */
    'gateways' => [
        'message_max_length' => 500,
        'batch_size' => 20,
        'max_attempts' => 5,
        // Réseaux sans API de publication : publication manuelle assistée.
        'manual_networks' => ['Roméo', 'GanXo', 'Tinder'],
    ],

    /*
     * Synchronisation hors connexion (SOFTWARE_ARCHITECTURE.md §5).
     */
    'sync' => [
        'push_batch_size' => 50,
        'device_stale_hours' => 24,
    ],

    /*
     * Rétention (DATABASE_ENGINEERING.md §6).
     */
    'retention' => [
        'audit_log_days' => (int) env('XOMITON_AUDIT_RETENTION_DAYS', 90),
        'mail_log_days' => 180,
        'otp_hours' => 24,
        'sensitive_years_after_case' => 5,
    ],

    /*
     * Rapports et exports (FR-DAT-04) : compatibles 30 s / 128 Mo.
     */
    'reports' => [
        'chunk_rows' => 500,
        'max_rows' => 20000,
    ],

    /*
     * Téléversements (MASTER_PROMPT.md §4 Sécurité).
     */
    'uploads' => [
        'image_mimes' => ['image/jpeg', 'image/png', 'image/webp'],
        'image_max_kb' => 5120,
        'document_mimes' => ['application/pdf'],
        'document_max_kb' => 20480,
        'reencode_images' => true,
    ],

    /*
     * Supervision du cron (D7, NFR-03).
     */
    'heartbeat' => [
        'url' => env('HEARTBEAT_URL'),
    ],

    /*
     * Assistant (garde-fous de crise : escalade vers un humain, numéros d'urgence).
     */
    'assistant' => [
        'provider' => env('ASSISTANT_PROVIDER'),
        'crisis_keywords' => ['suicide', 'me tuer', 'en danger', 'il va me tuer', 'frappée', 'violée'],
    ],
];
