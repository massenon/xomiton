<?php

declare(strict_types=1);

/*
 * Réglages métier de Xomiton (DEVELOPMENT_STANDARDS.md §2 « Config »).
 * Aucun secret ici : seulement des valeurs de comportement, surchargeables par .env.
 */
return [

    /*
     * Fuseau d'affichage des heures publiques (Bénin, UTC+1) : la base et `app.timezone` restent en UTC, les heures
     * montrées aux personnes (page de maintenance, bandeau d'annonce, écran /admin/maintenance, GET /api/v1/status
     * `timezone` pour l'écran membre et le mobile) sont converties dans ce fuseau (revue du lot maintenance, Q85).
     */
    'display_timezone' => env('XOMITON_DISPLAY_TIMEZONE', 'Africa/Porto-Novo'),

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
        // Moyen de contact d'une demande de rappel (téléphone ou e-mail, RequestCallbackRequest et modale de l'annuaire).
        'callback_contact_max' => 190,
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
        // Inscriptions newsletter par IP et par heure (empreinte), toutes sessions confondues (P4-07).
        'newsletter_per_hour_per_ip' => 10,
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
        // Vote « Cet article vous a-t-il aidé·e ? » du centre d'aide (POST /aide/{slug}/avis, P4-05, limiteur
        // `faq_feedback`) : par session (par IP sans cookie), plus un plafond large par IP (CGNAT mobile), en empreinte.
        'faq_feedback_per_hour' => 30,
        'faq_feedback_per_hour_per_ip' => 600,
        // SOS (ARCH §4 : « sans limite bloquante, journalisé ») : limiteur `sos`, jamais de 429 ; au-delà de ce nombre
        // d'envois par minute et par IP, chaque envoi est journalisé et l'administration prévenue (P2-08, P5-09).
        'sos_log_per_minute_per_ip' => 5,
        // Actions sensibles de mon compte (DELETE me, POST me/export, P6-06 : limiteur `account_sensitive`) : par compte.
        'account_sensitive_per_hour' => 5,
        // Fil (P7-02) : publications et commentaires par compte et par minute (limiteur `feed_write`), signalements de
        // contenu par compte et par heure (limiteur `content_report`).
        'feed_write_per_minute' => 10,
        'content_report_per_hour' => 20,
        // Messagerie (P8-01) : créations de conversation, envois, ajouts de participant·es par compte et par minute
        // (limiteur `messages_write` ; un rejeu de la boîte d'envoi compte, d'où une marge pour vider une file).
        'messages_write_per_minute' => 30,
        // Groupes et pages (P8-02, P8-03) : créations, adhésions, départs, décisions sur les demandes, abonnements aux
        // pages, par compte et par minute (limiteur `community_write`).
        'community_write_per_minute' => 20,
        // Qui suivre (P8-06, Q79) : invitations envoyées par compte et par jour (limiteur `network_invite`, en plus de
        // `community_write` : chacune notifie l'autre personne), aperçus `/qui-suivre?invite=<id>` par compte et par
        // minute (limiteur `network_preview`).
        'network_invites_per_day' => 30,
        'network_preview_per_minute' => 30,
        // Profil (P8-07) : téléversements d'avatar et de couverture par compte et par heure (limiteur `profile_image`).
        'profile_image_per_hour' => 20,
        // Paramètres (P8-09) : enregistrements de la section « Profil » (PATCH me/identity) par compte et par heure
        // (limiteur `identity_update`).
        'identity_update_per_hour' => 20,
        // Recherche membre (P8-13) : `GET search` et `search/suggestions` par compte et par minute (limiteur `search`,
        // hors `api_per_minute` : les suggestions de l'en-tête n'épuisent pas le quota du reste de l'espace).
        'search_per_minute' => 60,
        // Recherche globale de l'administration (P10-08, Livewire `/admin/recherche`) : recherches par compte et par minute
        // (limiteur `admin-search`, appliqué dans le composant : un appel Livewire n'est pas une route).
        'admin_search_per_minute' => 60,
        // Balise anonyme des compteurs de contenu (POST /evenements-contenu/{type}, P10-06, limiteur `content-event`) : par IP
        // (empreinte) et par minute, sans session ni cookie.
        'content_event_per_minute' => 60,
        // Annuaire membre (P8-12) : demandes de rappel (POST directory/{id}/callback) par compte et par heure (limiteur
        // `directory_callback`, après la vérification de la clé : un rejeu ne compte pas), jamais le quota `report` par
        // IP des signalements anonymes (CGNAT mobile).
        'directory_callback_per_hour' => 10,
        // Synchronisation (P9-06) : lots `POST sync/push` par compte et par minute (limiteur `sync_push`, rejeux non
        // comptés) ; un lot qui porte un SOS n'est jamais limité (RG-01).
        'sync_push_per_minute' => 20,
    ],

    /*
     * API /api/v1 (P6-01, docs/API.md §1.1) : taille de page maximale demandable par `?per_page=`.
     * P6-08 (tableau de bord mobile, `GET dashboard`) : fiches « À la une » et alertes routées listées pour un pair.
     */
    'api' => [
        'max_per_page' => 50,
        'dashboard_featured' => 5,
        'dashboard_routed_alerts' => 5,
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
        // Changement de pseudonyme (P8-09, Q81) : délai minimal entre deux changements (heures) et réservation de
        // l'ancien pseudonyme pour son compte (jours), pour qu'un autre compte ne le reprenne pas aussitôt.
        'pseudo_change_cooldown_hours' => 24,
        'pseudo_reservation_days' => 30,
        // Effacement différé d'un compte supprimé, avant anonymisation (jours).
        'deletion_grace_days' => 30,
        // Export RGPD (POST me/export, P6-06) : validité du lien de téléchargement envoyé par e-mail, puis fichier effacé.
        'export_link_hours' => 48,
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
        // Contenus signalés masqués en attendant la modération (RG-16, proposée) : nombre de signalements en attente de
        // personnes distinctes ; 0 désactive. Surcharge par l'administration : Settings `moderation_auto_hide_reports`.
        'auto_hide_after_reports' => (int) env('XOMITON_AUTO_HIDE_REPORTS', 3),
        // API du fil (P7-02) : taille de page par défaut (curseur), durée des liens signés des images (disque privé),
        // cache de la liste des mots filtrés (oubliée à chaque écriture de bad_words).
        'per_page' => 20,
        'comments_per_page' => 20,
        'media_url_minutes' => 60,
        // Fenêtre d'alignement des liens signés des médias (MediaUploader::stableExpiry) : même lien, même ETag, 304.
        'media_url_window_minutes' => 15,
        'bad_words_cache_seconds' => 600,
        'messages_poll_seconds' => 4,
        'bell_poll_seconds' => 30,
        // Gabarit membre Vue (P6-03, MemberShell::layout) : département et compteurs de la carte profil gardés en cache
        // (secondes, par compte) plutôt que recomptés à chaque visite Inertia ; 0 désactive.
        'shell_stats_cache_seconds' => 60,
        'duty_alerts_poll_seconds' => 10,
    ],

    /*
     * Messagerie (P8-01, docs/API.md §12) : corps chiffré au repos (`encrypted_columns.messages`), aperçu de la liste
     * calculé à la lecture (ADR-33 : `conversations.last_message_preview` reste nul), sondage court sans WebSocket (D7) :
     * conversation ouverte `feed.messages_poll_seconds` (4 s, DECISIONS.md), liste `list_poll_seconds`.
     */
    'messaging' => [
        'body_max_length' => 1000,
        'preview_length' => 80,
        'per_page' => 30,
        'conversations_per_page' => 30,
        'poll_max_messages' => 100,
        'list_poll_seconds' => 15,
        // Repli du sondage de la liste après 3 réponses inchangées (`meta.unchanged`, empreinte), retour à 15 s au
        // premier changement (D7 : réponses légères sur cPanel).
        'list_poll_idle_seconds' => 30,
        // Sondage `?after=` sans `since` : suppressions pour tout le monde rapportées sur cette fenêtre (minutes).
        'deleted_window_minutes' => 15,
        'group_title_min' => 3,
        'group_max_participants' => 50,
        'recipients_limit' => 10,
        'recipient_search_min' => 2,
        // « Nouveau message » : une seule notification non lue par conversation ; un message reçu moins de N secondes
        // après la précédente met à jour son compteur sans nouvel envoi push.
        'notify_cooldown_seconds' => 60,
        // Sourdine : durées proposées (minutes ; null = jusqu'à réactivation).
        'mute_durations' => ['8h' => 480, '1w' => 10080, 'always' => null],
        'attachment_collections' => ['images', 'documents'],
        'attachment_url_minutes' => 60,
    ],

    /*
     * Groupes et pages (P8-02, P8-03 ; prototype groups.php, pages.php, create-page.php) : bornes des formulaires
     * (groups.php l.111-116 : nom 3–80, description ≤ 300 ; create-page.php : nom 3–80, description ≤ 400), tailles de
     * page (prototype : 6 groupes par « Charger plus »), catégories de pages (create-page.php $cats).
     */
    'community' => [
        'group_name_min' => 3,
        'group_name_max' => 80,
        'group_description_max' => 300,
        'groups_per_page' => 6,
        'members_per_page' => 24,
        // P8-02 : une demande d'adhésion annulée puis renouvelée ne renotifie l'équipe qu'après ce délai.
        'join_request_renotify_hours' => 24,
        'page_name_min' => 3,
        'page_name_max' => 80,
        'page_description_max' => 400,
        'pages_per_page' => 12,
        'page_categories' => ['Organisation', 'Santé', 'Juridique', 'Réseau', 'Éducation', 'Autonomisation'],
        'search_max' => 60,
    ],

    /*
     * Annuaire membre `/annuaire` (P8-12, lot annuaire-membre ; prototype annuaire.php) : fiches par page (« Charger
     * plus ») et longueur de la recherche (nom, organisation, rôle, commune). Le message de la demande de rappel est
     * borné par `alerts.callback_message_max`.
     */
    'directory' => [
        'per_page' => 24,
        'search_max' => 80,
    ],

    /*
     * Espace pair (P9-03, P9-04, P9-08, lot espace-pair ; écrans `/espace-pair`, `/collecte`,
     * `/espace-pair/dossiers`) : intervalle d'interrogation des alertes routées quand le compte est de garde (10 s,
     * prototype espace-pair.php ; sinon aucune interrogation), dossiers par page de la liste, derniers dossiers montrés
     * sous le formulaire de collecte.
     */
    'peer' => [
        'alerts_poll_seconds' => 10,
        'cases_per_page' => 15,
        'recent_cases' => 10,
        // Brouillon de la collecte sur l'appareil (champs quasi-identifiants d'une victime, sans les notes) : durée de
        // vie plus courte que `alerts.draft_days` (7 j), jamais plus longue (Q88 3).
        'collecte_draft_days' => 1,
    ],

    /*
     * Recherche membre de l'en-tête (P8-13, lot recherche-membre ; page `/recherche`, API `search/`) : longueur du terme
     * (en deçà de `min_length`, aucune requête ; au-delà de `max_length`, tronqué), résultats par famille dans la vue
     * d'ensemble et dans les suggestions instantanées, taille de page d'une famille (« Charger plus »). Aucun
     * historique : le terme n'est ni enregistré, ni journalisé, ni mis en cache.
     */
    'search' => [
        'min_length' => 2,
        'max_length' => 80,
        'overview_per_type' => 4,
        'suggestions_per_type' => 3,
        'per_page' => 10,
    ],

    /*
     * Qui suivre et invitations (P8-06, lot qui-suivre ; prototype who-to-follow.php, carte « Qui suivre » de fil.php) :
     * tailles de page (suggestions par « Charger plus », invitations), nombre de suggestions de la carte du fil
     * (inc/render.php x_render_who_to_follow(5)), délai avant qu'une invitation annulée puis renvoyée renotifie la
     * personne invitée, et délai pendant lequel une invitation refusée ne peut pas être renvoyée par la même personne.
     */
    'network' => [
        'suggestions_per_page' => 12,
        'invitations_per_page' => 20,
        'card_suggestions' => 5,
        'invite_renotify_hours' => 24,
        'reinvite_after_refusal_days' => 30,
        'search_max' => 60,
    ],

    /*
     * Événements (P8-04, lot evenements) : bornes de la modale « Créer un événement » (events.php, partials/modals.php
     * l.166-190), pagination des listes (prototype : 6 par « Charger plus »), heure proposée par défaut, fenêtre du rappel
     * de la veille (`events:remind`, toutes les quinze minutes) et durée d'un événement au plus.
     */
    'events' => [
        'title_min' => 3,
        'title_max' => 200,
        'place_max' => 120,
        'address_max' => 255,
        'description_max' => 2000,
        'per_page' => 6,
        'sidebar_others' => 3,
        'default_time' => '15:00',
        'reminder_hours' => 24,
        'max_duration_days' => 31,
    ],

    /*
     * Forums (P8-05, lot forums) : bornes de la modale « Nouveau sujet » (forums.php l.13 : titre ≥ 5, message ≥ 10 ;
     * champ titre maxlength 140) et de la réponse (forum-topic.php l.13 : ≥ 2), pagination (prototype : 6 sujets par
     * « Charger plus »), recherche, et fenêtre du compteur de vues pour un client sans session (jeton Bearer du mobile :
     * une vue par compte et par sujet dans cette fenêtre ; navigateur : une par session, forum-topic.php l.46).
     */
    'forums' => [
        'title_min' => 5,
        'title_max' => 140,
        'body_min' => 10,
        'body_max' => 5000,
        'reply_min' => 2,
        'reply_max' => 2000,
        'topics_per_page' => 6,
        'replies_per_page' => 30,
        'search_max' => 60,
        'view_window_minutes' => 120,
    ],

    /*
     * Profil (P8-07, P8-08, lot profil) : connexions par page (onglet Connexions, `?q=` borné), médias affichés
     * (prototype my-profile-media.php : 15 + lien Médiathèque), cartes de la colonne (x_profile_side() : 6 médias,
     * 6 connexions), événements de l'onglet, entrées du journal d'activité (my-profile-activity.php : 40).
     */
    'profile' => [
        'connections_per_page' => 24,
        'media_limit' => 24,
        'side_media' => 6,
        'side_connections' => 6,
        'events_limit' => 12,
        'activity_limit' => 40,
        'search_max' => 60,
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
        // P4-07 : renvoi de l'e-mail de confirmation à une adresse en attente au plus toutes les N minutes ; durée
        // plancher de l'inscription publique (anti-énumération : même délai pour une adresse connue ou inconnue).
        'confirmation_resend_minutes' => 10,
        'response_floor_ms' => (int) env('XOMITON_NEWSLETTER_FLOOR_MS', 600),
        // Clé HMAC des liens de suivi (pixel, clic) ; vide = dérivée d'APP_KEY. Rotation : RUNBOOK (P17-05).
        'signing_key' => env('XOMITON_NEWSLETTER_KEY'),
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
        // P9-06 `GET sync/pull` : lignes par collection et par page ; lignes plus récentes que le retard gardées pour le
        // tirage suivant (transaction validée en retard) ; rétention des pierres tombales (`sync:prune`) : un appareil
        // silencieux plus longtemps refait un tirage complet.
        'pull_page_size' => 200,
        'pull_lag_seconds' => 2,
        'tombstone_days' => 90,
        // `POST sync/push` : budget de temps d'un lot (sous les 30 s de D7) ; au-delà, les éléments restants repartent
        // en 503 `retry_later` sans reçu (PushBatch).
        'push_time_budget_seconds' => 20,
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
                // P6-09 : hôtes des services push des navigateurs (Chrome / Edge Chromium / Opera, Firefox, Edge hérité,
                // Safari). Un endpoint d'un autre hôte est refusé (422) : le pilote VAPID ne poste jamais ailleurs (SSRF).
                'allowed_hosts' => ['fcm.googleapis.com', 'updates.push.services.mozilla.com', '*.notify.windows.com', 'web.push.apple.com'],
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
        // Lignes du registre détaillé d'un rapport : plafond par rapport (50 000, Excel par jobs successifs) et lignes
        // collectées par job GenerateReport (P10-07, ADR-23 : moins de 30 s par morceau).
        'max_rows' => 50000,
        'rows_per_job' => 5000,
        // Durée de conservation d'un fichier généré (jours) avant `reports:purge`.
        'retention_days' => 30,
        // Disque Laravel des fichiers de generated_reports (hors racine web) ; purgés par reports:purge après expires_at.
        'disk' => env('XOMITON_REPORTS_DISK', 'local'),
    ],

    /*
     * Exports CSV des écrans d'administration (P10-02, ADR-33, revue admin-socle) : l'action Livewire délivre un lien
     * signé de courte durée vers App\Http\Controllers\Admin\CsvExportController, qui diffuse le fichier par paquets
     * (Livewire mettrait toute la réponse en mémoire, puis en base64). Au-delà de `max_rows` lignes, l'export est
     * refusé : affiner les filtres (30 s / 128 Mo sur cPanel).
     */
    'exports' => [
        'max_rows' => (int) env('XOMITON_EXPORT_MAX_ROWS', 20000),
        'chunk_rows' => 500,
        'link_minutes' => 2,
        // ADR-23 : PDF et Word se construisent en mémoire ; au-delà de ce nombre de lignes de registre, l'export est
        // refusé (422 « choisir Excel »), l'Excel étant écrit en flux.
        // Mesuré (dompdf, 9 colonnes) : 100 lignes ≈ 70 Mo, 200 ≈ 100 Mo, 300 ≈ 130 Mo de pic, 2 000 ≈ 1,4 Go : 200 tient sous
        // les 128 Mo d'un mutualisé.
        'pdf_max_rows' => (int) env('XOMITON_PDF_MAX_ROWS', 200),
    ],

    /*
     * Compteurs d'événements de contenu (P10-06) : rétention des compteurs quotidiens sans donnée personnelle.
     */
    'content_events' => [
        'retention_days' => 30,
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
        // P4-10 : pas du « Charger plus » de /actualites (prototype blog.php) ; « À lire aussi » de la fiche (ShowNews).
        'actualites_per_load' => 6,
        'news_related_count' => 4,
        'max_per_load' => 300,
        'related_count' => 3,
        'contacts_max' => 4,
        'faq_min' => 3,
        'faq_max' => 5,
        // P4-03 : catégories dont les ressources portent la mention « informations éducatives, pas un conseil juridique ».
        'legal_notice_categories' => ['droits'],
        // P4-04 : fiches de /do-glegbe = thématiques publiées de cette catégorie (slug), les plus récentes d'abord.
        'do_glegbe_category' => 'regles',
        'do_glegbe_sheets' => 4,
        // P4-08 : « Dernière mise à jour » de /confidentialite (Y-m-d), à changer avec les textes de
        // lang/fr/pages/confidentialite.php (et la version des consentements, ConsentVersion, si l'engagement change).
        'legal_updated_at' => '2026-10-18',
    ],

    /*
     * Bibliothèque hors connexion des ressources (P4-03, ADR-26) : interrupteur « Hors ligne » par ressource, marquage
     * local par appareil (localStorage + événement `xm:offline-resources`) et badge « Disponible hors connexion » des
     * ressources marquées par l'équipe. La mise en cache effective revient au service worker (P5-10, livré) : activée par
     * défaut ; XOMITON_OFFLINE_LIBRARY=false retire interrupteurs et badges (aucune promesse hors connexion). Sans service
     * worker dans le navigateur, l'interrupteur n'est pas affiché (ressources.js, `supported`).
     */
    'offline' => [
        // P5-10 livré : le service worker met en cache les ressources enregistrées et marquées (désactivable).
        'library_enabled' => (bool) env('XOMITON_OFFLINE_LIBRARY', true),
        /*
         * Service worker (P5-10, ADR-26) servi par GET /sw.js (ServiceWorkerScript) : script construit par Vite
         * (vite-plugin-pwa, injectManifest) ; `version` est injectée dans le script avec la révision que la tâche
         * « Mise à jour du cache hors connexion » (P13-02) incrémente (ServiceWorkerScript::bump()) : un changement
         * fait installer la nouvelle version et recharger les pages gardées. `pages` : préchargées à l'installation
         * (sans cookie) et gardées à chaque consultation ; `fallback` : page servie sans réseau quand la page demandée
         * n'est pas en cache ; `timeout_ms` : délai réseau au-delà duquel une copie en cache est servie.
         */
        'service_worker' => [
            'script' => env('XOMITON_SW_SCRIPT', 'build/sw.js'),
            'version' => env('XOMITON_SW_VERSION', '1'),
            'pages' => ['/urgences', '/sos', '/signaler', '/annuaire', '/ressources'],
            'fallback' => '/urgences',
            'timeout_ms' => 6000,
            // Liste des ressources marquées injectée dans /sw.js, gardée en cache applicatif (secondes ; clé liée à la
            // révision : la tâche P13-02 la relit) : /sw.js est revalidé à chaque navigation.
            'marked_cache_seconds' => 300,
        ],
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
        // Catalogue Vue (P6-05) : textes jamais affichés par l'espace Vue (administration Livewire, pages publiques et SEO
        // Blade, maintenance, e-mails ; erreurs et noms de champs de l'API du fil, rendus par le serveur, P7-02) retirés de
        // resources/js/lang/<locale>.json ; l'ARB mobile reste complet.
        'vue_exclude' => ['admin', 'newsletter.admin', 'newsletter.campaign_defaults', 'notifications.admin', 'pages/', 'seo', 'maintenance', 'mail', 'feed.api', 'feed.moderation', 'mobile.api', 'community.messages.api', 'community.api', 'community.events.api'],
    ],

    /*
     * Assistant (P3-09 App\Application\Shared\KnowledgeBaseAnswerer, P8-11) : garde-fous de crise évalués avant toute
     * recherche (mots entiers, accents et casse ignorés), numéros d'urgence, seuil d'appariement de la base.
     */
    'assistant' => [
        // Fournisseur de l'assistant membre (App\Infrastructure\Assistant\AssistantProviders, Q7 non tranchée) : `kb`
        // (base de connaissances, sans LLM ni clé d'API) seul pilote livré ; vide, `null` ou inconnu → `kb`.
        'provider' => env('ASSISTANT_PROVIDER', 'kb'),
        // « Parler à un humain » (P8-11, App\Application\Assistant\EscalateToHuman) : besoin de l'alerte créée (slug de
        // `needs`, routage RG-04 ; repli `autre` s'il n'existe plus) et longueur maximale du message.
        'escalation_need' => 'soutien_psychologique',
        'escalation_message_max' => 1000,
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
        'profiles' => ['gender_identity', 'orientation', 'trusted_contact_phone', 'ally_status'],
        'alerts' => ['description', 'lat', 'lng', 'contact'],
        'alert_notes' => ['body'],
        'cases' => ['notes'],
        'case_forms' => ['facts_author', 'facts_description', 'services_note', 'consent_signatory'],
        'case_followups' => ['comment'],
        'messages' => ['body'],
        // P8-04 : adresse exacte et lien de connexion d'un événement, servis aux seul·es participant·es confirmé·es.
        'events' => ['address', 'online_url'],
        'assistant_messages' => ['body'],
        'social_channels' => ['access_token', 'refresh_token'],
        'settings' => ['value'],
        'sync_conflicts' => ['server_value', 'device_value', 'resolved_value'],
        'idempotency_keys' => ['response_headers', 'response_body'],
    ],
];
