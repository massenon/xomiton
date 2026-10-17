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
        // Suivi anonyme d'un signalement (GET signalements/{code}), réponse neutre au-delà (P2-08).
        'tracking_per_hour_per_ip' => 20,
        // API /api/v1 : par utilisateur·rice authentifié·e, sinon par IP.
        'api_per_minute' => 60,
    ],

    /*
     * En-têtes de sécurité HTTP (P2-08, P2-13, DEVELOPMENT_STANDARDS.md §7).
     */
    'security' => [
        // HSTS en production sur requête sécurisée : un an, sous-domaines compris.
        'hsts_max_age' => 31536000,
        // Alpine (public.js et Livewire) évalue ses expressions avec new Function : 'unsafe-eval' consigné (P2-13).
        // Passer à false le jour où le build CSP d'Alpine (@alpinejs/csp) est adopté.
        'csp_unsafe_eval' => true,
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
        // Accusés de réception de la synchronisation (sync_outbox_receipts) : fenêtre de rejeu dépassée.
        'sync_receipt_days' => 90,
        // Appareil de synchronisation sans contact au-delà : révoqué par sync:prune (jamais supprimé).
        'device_inactive_days' => 180,
        // Marge ajoutée à la durée de session (session.lifetime) avant purge des sessions en base.
        'session_grace_days' => 1,
        'otp_hours' => 24,
        'sensitive_years_after_case' => 5,
    ],

    /*
     * Rapports et exports (FR-DAT-04) : compatibles 30 s / 128 Mo.
     */
    'reports' => [
        'chunk_rows' => 500,
        'max_rows' => 20000,
        // Disque Laravel des fichiers de generated_reports (hors racine web) ; purgés par reports:purge après expires_at.
        'disk' => env('XOMITON_REPORTS_DISK', 'local'),
    ],

    /*
     * Téléversements (P3-04, MASTER_PROMPT.md §4 Sécurité, DEVELOPMENT_STANDARDS.md §7) : App\Application\Shared\MediaUploader.
     * Le type réel est lu dans le contenu (finfo), jamais dans l'extension ; les images sont ré-encodées par GD
     * (métadonnées EXIF / ICC supprimées) ; tout fichier va sur le disque privé (storage/app/private) sauf demande
     * explicite du disque public, réservé aux visuels non sensibles (avatars, couvertures, illustrations).
     */
    'uploads' => [
        // Collections : types MIME réels acceptés et poids maximal (Ko).
        'collections' => [
            'images' => ['mimes' => ['image/jpeg', 'image/png', 'image/webp'], 'max_kb' => 5120],
            'documents' => ['mimes' => ['application/pdf'], 'max_kb' => 20480],
            'audio' => ['mimes' => ['audio/mpeg', 'audio/mp4', 'audio/x-m4a', 'audio/ogg'], 'max_kb' => 20480],
            'video' => ['mimes' => ['video/mp4', 'video/webm'], 'max_kb' => 51200],
        ],
        'private_disk' => 'local',
        'public_disk' => 'public',
        // Disques qu'un lien signé (route media.signed) peut servir.
        'signed_disks' => ['local'],
        'signed_url_minutes' => 10,
        // Images : ré-encodage GD (orientation EXIF appliquée, métadonnées retirées, côté long ≤ 2048 px),
        // WebP + repli JPEG/PNG, variantes thumb / medium (côté long en px). Garde mémoire : surface maximale décodée.
        'reencode_images' => true,
        'image_max_dimension' => 2048,
        'image_max_pixels' => 25_000_000,
        'image_webp' => true,
        'image_keep_fallback' => true,
        'image_quality' => 82,
        'image_variants' => ['thumb' => 320, 'medium' => 1024],
    ],

    /*
     * Supervision du cron (D7, NFR-03).
     */
    'heartbeat' => [
        'url' => env('HEARTBEAT_URL'),
    ],

    /*
     * Langues (NFR-11, Q40, P3-05). `locales` ne liste que les locales dont lang/<locale> et l'ARB Flutter sont
     * complets : seules celles-là sont proposées par le sélecteur et acceptées par PATCH me/settings {locale}.
     * `locales_planned` : annoncées « bientôt » dans l'interface (lang/<locale>/README.md décrit la procédure).
     */
    'locales' => ['fr'],
    'locales_planned' => ['en', 'fon', 'yo'],
    'default_locale' => 'fr',

    // Locales intl (ICU) par langue, dans l'ordre de préférence : la première disponible sur le serveur est retenue
    // (App\Support\Locales::intlTag()). fr-BJ avec repli fr-FR (Q36) ; fon et yo retombent sur le français du Bénin.
    'intl_locales' => [
        'fr' => ['fr-BJ', 'fr-FR'],
        'en' => ['en-GB', 'en'],
        'fon' => ['fon-BJ', 'fr-BJ', 'fr-FR'],
        'yo' => ['yo-BJ', 'yo-NG', 'fr-BJ'],
    ],

    // Export des messages pour vue-i18n (JSON à plat « fichier.cle.souscle », placeholders {nom}) et Flutter
    // (ARB, placeholders ICU) : php artisan lang:export [--locale=fr] [--check].
    'lang_export' => [
        'json_dir' => resource_path('js/lang'),
        'arb_dir' => base_path('../mobile/lib/l10n'),
    ],

    /*
     * Assistant (P3-09 App\Application\Shared\KnowledgeBaseAnswerer, P8-11) : garde-fous de crise évalués avant toute
     * recherche (mots entiers, accents et casse ignorés), numéros d'urgence, seuil d'appariement de la base.
     */
    'assistant' => [
        'provider' => env('ASSISTANT_PROVIDER'),
        'crisis_keywords' => [
            // Idées suicidaires, détresse vitale
            'suicide', 'suicider', 'me suicider', 'me tuer', 'se tuer', 'en finir', 'mourir', 'envie de mourir', 'me faire du mal',
            // Danger immédiat, menaces
            'en danger', 'danger immédiat', 'il va me tuer', 'elle va me tuer', 'menace de mort', 'menacé de mort', 'menacée de mort', 'au secours',
            // Violences physiques et sexuelles en cours
            'viol', 'violé', 'violée', 'violer', 'frappe', 'frappé', 'frappée', 'frapper', 'me bat', 'battue', 'étranglé', 'étranglée',
            // Privation de liberté
            'enfermé', 'enfermée', 'séquestré', 'séquestrée',
        ],
        // Numéros affichés dans la réponse de crise ; surchargés par le paramètre `emergency_numbers` (settings) s'il existe.
        // Valeurs du prototype (urgences.php) : à vérifier avec l'association avant la mise en production.
        'emergency_numbers' => [
            ['label' => 'Police secours', 'number' => '117'],
            ['label' => 'Pompiers / secours', 'number' => '118'],
            ['label' => 'Ligne d’écoute Xomiton', 'number' => '+229 01 97 38 88 47'],
        ],
        // Score minimal (0 à 1) pour qu'une entrée de la base réponde ; en dessous, repli avec suggestions.
        'match_threshold' => 0.5,
        'suggestions_count' => 3,
    ],

    /*
     * Colonnes chiffrées (cast Eloquent `encrypted`, DATABASE_DICTIONARY.md « [chiffré] ») : liste exhaustive
     * table => colonnes, parcourue par `xomiton:reencrypt` lors d'une rotation d'APP_KEY (P1-15, D7).
     * Cas particulier : `settings.value` n'est chiffrée que pour les clés de type `secret` (App\Models\Setting),
     * sous la forme d'une chaîne JSON contenant la charge chiffrée. Un test compare cette liste aux casts
     * des modèles : toute colonne chiffrée ajoutée doit être déclarée ici.
     */
    'encrypted_columns' => [
        'profiles' => ['gender_identity', 'orientation', 'trusted_contact_phone'],
        'alerts' => ['description', 'lat', 'lng', 'contact'],
        'alert_notes' => ['body'],
        'cases' => ['notes'],
        'case_forms' => ['facts_author', 'facts_description', 'services_note', 'consent_signatory'],
        'case_followups' => ['comment'],
        'messages' => ['body'],
        'assistant_messages' => ['body'],
        'social_channels' => ['access_token', 'refresh_token'],
        'settings' => ['value'],
        'sync_conflicts' => ['server_value', 'device_value', 'resolved_value'],
    ],
];
