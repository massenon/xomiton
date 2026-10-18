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
        // P5-04 : durée de vie du brouillon local de /signaler (IndexedDB, sans contact, position ni preuves), en jours.
        'draft_days' => 7,
        // RG-05 et Q27 (P5-07) : délais par type depuis la création, en minutes ; null = pas de rappel / pas
        // d'escalade. SOS : escalade au point focal national à 5 min ; signalement et besoin : rappel au contact
        // routé à 24 h, escalade à 48 h (décision par défaut, IMPLEMENTATION_PLAN §7). Lus par App\Support\EscalationPolicyFactory,
        // surchargeables par le réglage `alerts.escalation.<type>.<clé>` (P13-04).
        'escalation' => [
            'sos' => [
                'remind_after_minutes' => null,
                'escalate_after_minutes' => (int) env('XOMITON_ESCALATION_MINUTES', 5),
            ],
            'denonciation' => [
                'remind_after_minutes' => (int) env('XOMITON_REPORT_REMIND_MINUTES', 1440),
                'escalate_after_minutes' => (int) env('XOMITON_REPORT_ESCALATE_MINUTES', 2880),
            ],
            'besoin' => [
                'remind_after_minutes' => (int) env('XOMITON_REPORT_REMIND_MINUTES', 1440),
                'escalate_after_minutes' => (int) env('XOMITON_REPORT_ESCALATE_MINUTES', 2880),
            ],
        ],
        // Alertes examinées au plus par exécution d'alerts:escalate (D7 : 30 s par requête / tâche).
        'escalation_batch' => 200,
        // Marge au-delà du délai d'escalade : une alerte plus ancienne n'est plus rappelée ni escaladée (alertes
        // historiques importées, cron arrêté longtemps), en minutes.
        'escalation_grace_minutes' => (int) env('XOMITON_ESCALATION_GRACE_MINUTES', 1440),
        // Repentir après l'envoi d'un SOS (P5-02, Q39), en secondes.
        'cancel_window_seconds' => 60,
        // Rythme de sondage de GET sos/{id} conseillé aux clients tant que le SOS est ouvert (P5-06, secondes).
        'sos_poll_seconds' => 10,
        // Validité du lien signé d'accusé de réception envoyé à un contact d'annuaire sans compte (heures).
        'acknowledge_link_hours' => 48,
        // P5-03 (D7) : budget mural global des remises externes (push, SMS) d'une requête, en secondes ; le délai par
        // fournisseur reste provider_timeout_seconds. Le cron notifications:retry dispose de retry_budget_seconds.
        'dispatch_budget_seconds' => (float) env('XOMITON_DISPATCH_BUDGET', 8),
        'retry_budget_seconds' => 50,
        // Délai avant la n-ième reprise d'une notification échouée (minutes) ; le dernier se répète jusqu'au plafond.
        'retry_backoff_minutes' => [1, 1, 2, 5, 10],
        // Lignes examinées au plus par exécution de notifications:retry.
        'retry_batch_size' => 100,
        // Au rejeu : SMS de repli au compte dont tous les push d'un SOS ont échoué au moins autant de fois.
        'sms_fallback_after_push_failures' => 2,
        // Au plus N admins de garde reçoivent un SMS de repli par SOS (coût) ; les autres gardent cloche et push.
        'sos_admin_sms_max' => 2,
        // Durée du sondage de GET sos/{id} (sos_poll_seconds ci-dessus) après la création, en minutes (P5-03).
        'sos_poll_minutes' => 30,
        // Bouton SOS (P5-05, P5-08, FR-ALT-03, Q39) : maintien (ms), décompte annulable (s), attente de la position sur
        // /sos (s) puis envoi automatique du repli département / commune sans GPS (s ; délai total à trancher, Q61).
        'sos_hold_ms' => 2000,
        'sos_countdown_seconds' => 5,
        'sos_locate_seconds' => 12,
        'sos_manual_seconds' => 15,
        // Suivi par code : une alerte close depuis plus de N jours répond « code inconnu ».
        'tracking_visible_days' => 90,
        'description_min' => 20,
        'description_max' => 4000,
        'sos_message_max' => 1000,
        'callback_message_max' => 1000,
        'note_max' => 1000,
        'attachments_max' => 5,
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
        // Page d'accusé de réception par lien signé (P5-02) : par IP + alerte + fiche, limiteur distinct de `tracking`.
        'acknowledge_per_hour_per_link' => 60,
        // Annulation et « Je suis en sécurité » d'un SOS (POST sos/{id}/cancel|safe, P5-06) : par IP + alerte.
        'sos_owner_per_minute' => 10,
        // Sondage de l'état d'un SOS (GET sos/{id}, toutes les 10 s, P5-06) : par IP + alerte, hors limiteur `api`.
        'sos_status_per_minute' => 30,
        // API /api/v1 : par utilisateur·rice authentifié·e, sinon par IP.
        'api_per_minute' => 60,
        // Assistant public de l'accueil (POST assistant/question, P4-01) : par IP, en plus de api_per_minute.
        'assistant_per_minute' => 20,
        // Téléchargement et lecture d'une ressource (P4-03, limiteur `resource_download`) : par IP, adresse et ressource.
        'resource_download_per_minute' => 60,
        // SOS (ARCH §4 : « sans limite bloquante, journalisé ») : limiteur `sos`, jamais de 429 ; au-delà de ce nombre
        // d'envois par minute et par IP, chaque envoi est journalisé et l'administration prévenue (P2-08, P5-09).
        'sos_log_per_minute_per_ip' => 5,
    ],

    /*
     * Idempotence de l'API (P5-09, ADR-32) : middleware `idempotent`, table `idempotency_keys`, purge `idempotency:purge`.
     */
    'idempotency' => [
        // Durée pendant laquelle une réponse est rejouée à l'identique ; au-delà, la déduplication métier
        // (alerts.idempotency_key, client_uuid) prend le relais.
        'ttl_hours' => (int) env('XOMITON_IDEMPOTENCY_TTL_HOURS', 48),
        // Attente maximale d'une requête concurrente portant la même clé avant 429 idempotency_in_progress (202 processing
        // sur POST sos) ; sondages à partir de poll_milliseconds, doublés jusqu'à 500 ms (worker PHP-FPM mutualisé).
        'wait_seconds' => 2,
        'poll_milliseconds' => 100,
        // Réservation non complétée depuis ce délai (processus interrompu) : reprise par le rejeu suivant.
        'lock_seconds' => 60,
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
     * Notifications (P3-02, NOTIFICATIONS_INSTRUCTION.md, ADR-25) : App\Notifications\XomitonNotification.
     * `categories` : valeurs par défaut de `user_settings.notification_prefs` par catégorie (`in_app` = cloche,
     * `email` = template en base, `push` = Web Push et FCM) ; `locked` = catégorie que la personne ne peut pas
     * désactiver (sécurité du compte et SOS, §4.3). `push.*.driver` : `log` tant que les paquets ne sont pas installés
     * (`vapid` → minishlink/web-push ; `firebase` → compte de service FIREBASE_CREDENTIALS hors racine web) ; un pilote
     * inconnu ou non installé retombe sur `log` avec un avertissement (App\Infrastructure\Notifications\NotificationDrivers).
     */
    'notifications' => [
        'categories' => [
            'securite' => ['in_app' => true, 'email' => true, 'push' => true, 'locked' => true],
            'sos' => ['in_app' => true, 'email' => true, 'push' => true, 'locked' => true],
            'alertes' => ['in_app' => true, 'email' => true, 'push' => true, 'locked' => false],
            'messages' => ['in_app' => true, 'email' => false, 'push' => true, 'locked' => false],
            'communaute' => ['in_app' => true, 'email' => false, 'push' => true, 'locked' => false],
            'evenements' => ['in_app' => true, 'email' => true, 'push' => false, 'locked' => false],
            'moderation' => ['in_app' => true, 'email' => false, 'push' => false, 'locked' => false],
            'systeme' => ['in_app' => true, 'email' => false, 'push' => false, 'locked' => false],
        ],
        'push' => [
            'webpush' => [
                'driver' => env('WEBPUSH_DRIVER', 'log'),
                'vapid' => [
                    'subject' => env('VAPID_SUBJECT', 'mailto:bonjour@xomiton.org'),
                    'public_key' => env('VAPID_PUBLIC_KEY'),
                    'private_key' => env('VAPID_PRIVATE_KEY'),
                ],
            ],
            'fcm' => [
                'driver' => env('FCM_DRIVER', 'log'),
                // Chemin absolu du fichier JSON du compte de service Firebase, hors racine web (jamais de clé serveur héritée).
                'credentials' => env('FIREBASE_CREDENTIALS'),
            ],
        ],
    ],

    /*
     * SMS de secours (NOTIFICATIONS_INSTRUCTION.md §1 : SOS uniquement, Q7) : App\Domain\Notifications\Contracts\SmsSender.
     * `provider` : `log` (journal) tant que le fournisseur n'est pas choisi ; un fournisseur inconnu retombe sur `log`.
     */
    'sms' => [
        'provider' => env('SMS_PROVIDER', 'log'),
        'api_key' => env('SMS_API_KEY'),
        'sender' => env('SMS_SENDER', 'Xomiton'),
        // P5-03 (D6, Q50) : bornes en segments, pas en caractères (App\Domain\Notifications\SmsText : texte ramené à
        // GSM-7, 160 caractères pour un segment, 153 par segment au-delà ; 70 / 67 si un caractère reste en UCS-2).
        'max_segments' => 1,
        // Un SMS qui se termine par un lien (lien signé d'accusé d'un contact sans compte) n'est jamais tronqué dans le
        // lien ; il peut alors compter jusqu'à ce nombre de segments (306 caractères GSM-7).
        'max_segments_with_link' => 2,
    ],

    /*
     * Référentiels en cache (P3-06, PERFORMANCE.md) : App\Application\Shared\Referentials. Durée en secondes ;
     * l'invalidation passe par une clé de version incrémentée à chaque écriture (observer sur les modèles).
     */
    'referentials' => [
        'ttl_seconds' => 86400,
        // Cache-Control public (secondes) des réponses GET /api/v1/zones, violence-types, needs.
        'http_max_age' => 3600,
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
        // WebP + repli JPEG/PNG, variantes thumb / medium / large (côté long en px ; `large` = largeur maximale affichée par
        // <x-img>, PERFORMANCE.md §2). Garde mémoire : surface maximale décodée.
        'reencode_images' => true,
        'image_max_dimension' => 2048,
        'image_max_pixels' => 25_000_000,
        'image_webp' => true,
        'image_keep_fallback' => true,
        'image_quality' => 82,
        'image_variants' => ['thumb' => 320, 'medium' => 1024, 'large' => 1600],
    ],

    /*
     * Référencement (P4-11) : SEO_ALLOW_INDEXING=false interdit toute exploration dans robots.txt (recette publique,
     * mise en ligne progressive) ; surchargeable par le paramètre `seo.allow_indexing` (settings). Hors production
     * (local, testing, recette comprise), robots.txt interdit toujours tout et sitemap.xml répond 404.
     */
    'seo' => [
        'allow_indexing' => (bool) env('SEO_ALLOW_INDEXING', true),
        // Durée de vie du cache du sitemap (secondes).
        'sitemap_cache_seconds' => 3600,
    ],

    /*
     * Pages publiques de contenu (P4-02 thématiques ; repris par P4-03 ressources) : pas du « Charger plus »
     * (prototype x_paginate($list, 6)), fiches liées, contacts d'annuaire publics par fiche (Q19), bornes de la FAQ
     * d'une fiche (FRONT_END_SEO_PERFECT_AUDIT.md : 3 à 5 questions, sinon ni section ni FAQPage).
     */
    'content' => [
        'thematiques_per_load' => 6,
        // P4-03 : même pas que les thématiques (grille 2 / 3 colonnes sans rangée orpheline) ; « Fiches à télécharger ».
        'ressources_per_load' => 6,
        'ressources_files_count' => 6,
        'max_per_load' => 300,
        'related_count' => 3,
        'contacts_max' => 4,
        'faq_min' => 3,
        'faq_max' => 5,
        // P4-03 : catégories dont les ressources portent la mention « informations éducatives, pas un conseil juridique ».
        'legal_notice_categories' => ['droits'],
    ],

    /*
     * Bibliothèque hors connexion des ressources (P4-03, ADR-26) : interrupteur « Hors ligne » par ressource, marquage
     * local par appareil (localStorage + événement `xm:offline-resources`) et badge « Disponible hors connexion » des
     * ressources marquées par l'équipe. La mise en cache effective revient au service worker (P5-10) : désactivée par
     * défaut, aucune promesse hors connexion n'est affichée tant que P5-10 ne passe pas XOMITON_OFFLINE_LIBRARY à true.
     */
    'offline' => [
        'library_enabled' => (bool) env('XOMITON_OFFLINE_LIBRARY', false),
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
        'idempotency_keys' => ['response_headers', 'response_body'],
    ],
];
