---
name: xomiton-database-dictionary
description: Dictionnaire de données complet de Xomiton, table par table et colonne par colonne (types Laravel, nullabilité, clés, index, enums PHP, champs chiffrés), y compris les tables seulement nommées dans DATABASE_ENGINEERING.md et celles ajoutées pour couvrir le prototype. À charger avant d'écrire ou de relire une migration, un modèle, une factory, un seeder ou l'import du prototype.
---

# Dictionnaire de données

Complète [DATABASE_ENGINEERING.md](DATABASE_ENGINEERING.md) (qui prime en cas de divergence sur les tables qu'il détaille) : ce document fige les colonnes des tables que le document principal ne fait que nommer, consigne les écarts avec le prototype et leur résolution, et sert de spécification aux migrations de la phase P1 du [plan](IMPLEMENTATION_PLAN.md).

Conventions (rappel) : InnoDB, `utf8mb4_unicode_ci`, `id` `bigint unsigned` auto-incrémenté, `created_at` / `updated_at` sur toutes les tables sauf mention `[sans timestamps]`, `deleted_at` seulement où `[soft delete]` est indiqué. Types notés en vocabulaire Laravel (`string(40)`, `text`, `json`, `boolean`, `timestamp`, `date`, `decimal(9,6)`, `unsignedTinyInteger`…). `FK` = `foreignId()->constrained()` nommée ; `cascade` seulement pour les tables de liaison, `restrict` pour dossiers et alertes, `nullOnDelete` pour les auteurs facultatifs. `[chiffré]` = cast Eloquent `encrypted`, colonne `text`, jamais indexée. Les enums sont des **enums PHP** (`app/Enums`) stockés en `string` (pas d'`ENUM` SQL : MariaDB et SQLite de test) avec un `CHECK` applicatif par validation.

Identifiants publics (ADR-27) : les contenus exposés par URL portent une colonne `ulid char(26) unique` (`posts`, `conversations`, `groups`, `pages`, `events`, `forum_topics`, `resources`? non : les ressources, thématiques, FAQ et lois sont publiques et utilisent un `slug`).

## Résolution des écarts prototype ↔ document

| Sujet | Décision | Impact |
|---|---|---|
| `posts.visibility` `friends` (prototype) | enum du doc `public`, `followers`, `me` ; `friends` → `followers` à l'import | import |
| `case_followups.outcome` `En attente` (prototype) | enum du doc ; `En attente` → `Planifié` | import |
| `alerts.status` `status_at` (prototype) | enum du doc (`nouveau`, `pris_en_charge`, `en_cours`, `traite`, `clos`) ; `status_at` → `acknowledged_at` | import |
| `alerts.source = annuaire` (demande de rappel depuis l'annuaire) | valeur ajoutée à l'enum `AlertSource` : `web`, `app`, `sync-offline`, `annuaire` | enum |
| `alerts.notes` (notes internes écrites par l'admin) | table `alert_notes` (auteur, corps chiffré) plutôt qu'une colonne : plusieurs notes, traçables | nouvelle table |
| `mail_logs.transport = '-'` (adresse invalide) | enum `MailTransport` : `smtp`, `outbox`, `none` | enum |
| `mail_outbox` (e-mails en attente sans SMTP) | table `mail_outbox` (contenu complet, envoyé plus tard par `mail:flush-outbox`) | nouvelle table |
| Lois (`x_lois`, page `/lois`) | table `laws` | nouvelle table |
| Planification des rapports (`rapport_plans`) | table `report_schedules` | nouvelle table |
| Catégories de thématiques, ressources, FAQ (onglet « Catégories » de l'admin) | table `categories` (`kind`) | nouvelle table |
| Besoins exprimés (`x_needs`) et routage besoin → rôle | table `needs` (slug, libellé, rôle d'annuaire ciblé) ; `alerts.needs` reste un `json` de slugs | nouvelle table |
| Code dossier VDH (prototype : `VBG-00xx` même pour VDH) | RG-06 : préfixe selon `kind` (`VBG-0000`, `VDH-0000`), compteurs séparés, jamais réutilisés | import : renumérotation journalisée |
| `cases.pair` nom libre (Esther Koto, Mireille Tognifode absentes de `users`) | `collected_by` FK obligatoire ; l'import crée les comptes pair manquants (statut `actif`, mot de passe aléatoire) et journalise | import |
| `cases.referral` libre (`Hébergement` n'est pas un rôle) | colonne `referral string(80) null` conservée telle quelle (libellé de service) | — |
| Tags et centres d'intérêt newsletter (index multi-valeurs MySQL 8 seulement) | colonnes `json` d'affichage + tables de liaison `newsletter_subscriber_tags`, `newsletter_subscriber_interests` pour la segmentation (ADR-20) | nouvelles tables |
| `indicators` sans `id` dans le prototype, valeurs calculées à la volée | `indicators` garde `calc_key` et `is_builtin` ; `indicator_values` stocke valeurs manuelles et instantanés mensuels calculés par `indicators:snapshot` | — |
| `settings` : ~45 clés dont objets imbriqués, SMTP et secrets | table `settings` clé / valeur `json` typée, `is_secret` → valeur chiffrée ; `gateway_chat` → `peer_rooms`, `rules_off` / `plausibility` / `dup_*` → `validation_rules` | — |
| `audit_logs.user` = e-mail ou `anonyme` | `user_id` FK null + `actor string(190) null` (e-mail ou libellé) | — |
| FAQ en double (`faqs.json` et `x_faq()`) | seeder depuis `faqs.json` uniquement | seed |
| `moderation_reports.reporter` / `target` en texte libre | `reporter_user_id` FK null + `reporter_label string(80) null` ; cible typée (`subject_type`, `subject_id`) + `excerpt` | — |
| Avatars par index (0–15 vers 6 fichiers) | `profiles.avatar_path` ; l'import copie le fichier de démonstration dans `storage/app/public/avatars` | import |
| Événements, groupes, pages : compteurs `going`, `members`, `followers` | colonnes dénormalisées `*_count` recalculées par les actions (comme `posts.likes_count`) | — |
| Services des fiches (`admin/fiches.php` : 7 services dont `dignite`, kit de dignité) | `case_forms.services` admet `dignite` | enum applicatif |
| Fréquences des indicateurs et rapports (`indicateurs.json`, `admin/rapports.php` : semestrielle, annuelle) | `IndicatorFrequency` et `ReportKind` / `report_schedules.frequency` étendus (`semestrielle`, `annuelle` ; `semestriel`) | enum |
| Règle `required` verrouillée du catalogue de validation (`admin/validation.php`, 10 règles) | ligne `required` avec `is_locked = true` (non désactivable) | nouvelle colonne |
| Profil : visibilité `public` / `communaute` / `connexions`, `show_zone`, `searchable`, préférence GPS du SOS, 5 interrupteurs de notification + résumé (`settings.php`) | `ProfileVisibility` gagne `public` ; `user_settings` gagne `show_zone`, `searchable`, `sos_share_gps`, `notification_digest` (`never`, `daily`, `weekly`) | enum, colonnes |
| Type de membre à l'inscription (`sign-up.php` : pair, membre, allié·e, personne ressource) | `MemberType` : `membre`, `pair`, `allie`, `ressource` (les deux derniers affichés seulement si le client les confirme : question Q du plan) | enum |
| Médiathèque et albums de profil (`albums.php`, onglet Médias du profil) | tables `albums`, `album_media` (lot 4) | nouvelles tables |
| Plan de sécurité personnel (`urgences.php`, cases en `localStorage`) | aucune table : conservé localement par conception (MODULE_SPECIFICATIONS §A) | — |

## 1. Référentiels

### `zones` [sans soft delete]
`id` · `name string(60) unique` · `slug string(60) unique` · `position unsignedTinyInteger` · `is_active boolean default true`.
Seed : 12 départements (Alibori, Atacora, Atlantique, Borgou, Collines, Couffo, Donga, Littoral, Mono, Ouémé, Plateau, Zou).

### `communes`
`id` · `zone_id FK zones restrict` · `name string(80)` · `slug string(80)` · `position unsignedTinyInteger` · `is_active boolean default true`. Unique `(zone_id, slug)`. Seed : les 29 communes du prototype (`x_zones()`), extensible aux 77 communes du Bénin (à compléter avec le client).

### `violence_types`
`id` · `slug string(40) unique` (`physique`, `psychologique`, `sexuelle`, `economique`, `numerique`) · `label string(80)` · `icon string(40)` · `description string(255)` · `position unsignedTinyInteger` · `is_active boolean`.

### `populations`
`id` · `slug string(40) unique` · `label string(80)` · `position` · `is_active`. Seed : 7 populations du prototype.

### `needs`
`id` · `slug string(40) unique` (`soutien_psychologique`, `soutien_juridique`, `soutien_medical`, `hebergement`, `accompagnement`, `securite`, `autre`) · `label string(80)` · `routed_role string(40) null` (valeur de `DirectoryRole` ciblée : `psychologue`, `juriste`, `gynecologue` ; null = point focal) · `position` · `is_active`.

Convention des enums : les valeurs stockées sont des **slugs ASCII en snake_case** (`point_focal`, `temoignage`, `planifie`, `authentification`, `facebook`…), jamais les libellés accentués ; les libellés viennent de `lang/fr/enums/<domaine>.php`. `DirectoryRole::tryFromLabel()` convertit les libellés du prototype à l'import.

### `categories`
`id` · `kind string(20)` (`thematique`, `resource`, `faq`, `event`) · `slug string(60)` · `label string(80)` · `icon string(40) null` · `color string(20) null` (clé de variante Bootstrap : `danger`, `primary`…) · `position` · `is_active`. Unique `(kind, slug)`. Seed : les 6 catégories de thématiques (`vbg`, `regles`, `mental`, `droits`, `esc`, `corps`).

Enums PHP associés : `AgeRange` (`10-14`, `15-17`, `18-24`, `25-34`, `35-49`, `50+`), `Sex` (`femme`, `homme`, `autre`), `DirectoryRole` (8 rôles du doc).

## 2. Identité et accès

### `users` [soft delete]
`id` · `pseudo string(40) unique` · `name string(120) null` (jamais public, RG-02) · `email string(190) unique` · `phone string(30) null` · `password string(255)` · `status string(20) default actif` (`UserStatus` : `en_attente`, `actif`, `suspendu`) · `zone_id FK null nullOnDelete` · `commune_id FK null nullOnDelete` · `email_verified_at timestamp null` · `last_login_at timestamp null` · `last_seen_at timestamp null` · `suspended_at timestamp null` · `suspension_reason string(200) null` · `remember_token` · `deleted_at`. Index `(status)`.

### `profiles` (1–1)
`id` · `user_id FK unique cascade` · `pronouns string(30) null` · `age_range string(10) null` (`AgeRange`) · `member_type string(10) default membre` (`MemberType` : `membre`, `pair`, `allie`, `ressource`) · `gender_identity text null [chiffré]` · `orientation text null [chiffré]` · `bio string(500) null` · `completion unsignedTinyInteger default 0` (0–100, CHECK applicatif) · `avatar_path string(255) null` · `cover_path string(255) null` · `discreet_mode boolean default false` · `trusted_contact_phone text null [chiffré]` · `trusted_contact_name string(80) null` · `website string(190) null` · `visibility string(10) default community` (`ProfileVisibility` : `public`, `community`, `followers`, `private`) · `show_zone boolean default true` · `searchable boolean default true`.

### `user_settings` (1–1)
`id` · `user_id FK unique cascade` · `theme string(10) default auto` (`light`, `dark`, `auto`) · `language string(5) default fr` · `notification_prefs json` (par catégorie : `in_app`, `email`, `push` ; sécurité et SOS non désactivables) · `mask_sensitive_notifications boolean default false` · `notification_digest string(10) default never` (`never`, `daily`, `weekly`) · `sos_share_gps boolean default true` · `pin_enabled boolean default false` · `pin_hash string(255) null` · `neutral_tab_title boolean default false`.

### Tables spatie/laravel-permission
`roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` : migration publiée, inchangée. Rôles : `membre`, `pair`, `moderateur` (proposé), `admin`, `superadmin`. Permissions : `ROLE_ET_PERMISSIONS.md` §4 + `admin.access`.

### `personal_access_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`
Migrations Laravel / Sanctum publiées, inchangées (sessions en base, D7).

### `otp_codes` [sans timestamps : `created_at` seul]
`id` · `email string(190)` · `purpose string(10)` (`OtpPurpose` : `login`, `signup`, `reset`) · `code_hash char(64)` · `attempts unsignedTinyInteger default 0` · `expires_at timestamp` · `consumed_at timestamp null` · `ip string(45) null` · `created_at`. Index `(email, purpose)`. Purge 24 h.

### `consents` [sans updated_at]
`id` · `user_id FK null nullOnDelete` · `email string(190) null` (visiteur·se newsletter) · `kind string(20)` (`ConsentKind` : `cgu`, `privacy`, `sensitive_data`, `newsletter`) · `version string(10)` · `granted boolean` · `ip string(45) null` · `user_agent string(255) null` · `created_at`. Jamais mis à jour. Index `(user_id, kind)`.

### `user_blocks` (`blocks` du doc)
`id` · `user_id FK cascade` (qui bloque) · `blocked_user_id FK cascade` · `reason string(200) null` · `created_at`. Unique `(user_id, blocked_user_id)`.

## 3. Contenus

### `thematiques` [soft delete]
`id` · `category_id FK categories restrict` · `slug string(120) unique` · `title string(200)` · `summary string(500)` · `body longtext null` (HTML nettoyé) · `sections json null` (accordéon : `[{title, body}]`) · `content_warning string(255) null` · `image_path string(255) null` · `cover_path string(255) null` · `reading_minutes unsignedTinyInteger default 3` · `related_contact_roles json null` · `related_ids json null` · `status string(10) default publie` (`ContentStatus` : `brouillon`, `publie`, `masque`) · `published_at timestamp null` · `views_count unsignedInteger default 0` · `meta_title string(70) null` · `meta_description string(160) null` · `created_by FK users null`. Index `(status, published_at)`. Seed : 29 thématiques (`x_thematiques()`).

### `resources` [soft delete]
`id` · `category_id FK null` · `thematique_id FK null nullOnDelete` · `slug string(120) unique` · `title string(200)` · `kind string(20)` (`ResourceKind` : `fiche`, `guide`, `video`, `audio`, `pdf`, `article`, `lien`) · `summary string(500)` · `body longtext null` · `file_path string(255) null` · `file_size unsignedInteger null` · `external_url string(500) null` · `image_path string(255) null` · `duration_minutes unsignedSmallInteger null` · `status` (`ContentStatus`) · `published_at` · `downloads_count unsignedInteger default 0` · `views_count` · `is_offline_available boolean default false` · `meta_title`, `meta_description` · `created_by FK null`. Index `(status, published_at)`, `(kind)`. Les **actualités** (`/actualites`, Q1 du plan) sont des ressources `kind = article` tant que le client n'a pas tranché.

### `faqs`
`id` · `category_id FK null` · `question string(255)` · `answer text` · `position unsignedSmallInteger` · `is_published boolean default true` · `show_on_home boolean default false`. Seed : `faqs.json`.

### `laws`
`id` · `slug string(80) unique` · `title string(200)` · `subtitle string(200) null` · `summary text` · `category string(80)` · `official_url string(500) null` · `position` · `is_published boolean`. Seed : `x_lois()`.

### `ccc_tools`
`id` · `kind string(20)` (`CccKind` : `manuel`, `boite_images`, `bd`, `loi`) · `key string(40) unique` (`m1`, `bd1`…) · `title string(200)` · `description text null` · `image_path string(255) null` · `pages json null` (planches : `[{image, caption, alt}]`) · `chapters json null` (manuel : `[titre]`) · `duration_minutes unsignedSmallInteger null` · `position` · `is_published boolean default true`. Seed : `x_ccc_manuel()`, `x_ccc_boites()`, `x_ccc_bd()`.

### `assistant_kb`
`id` · `question string(255)` · `answer text` · `keywords json` · `is_active boolean default true` · `hits_count unsignedInteger default 0`.

### `assistant_conversations`, `assistant_messages`
`assistant_conversations` : `id` · `user_id FK null nullOnDelete` · `session_key string(64) null` · `is_flagged boolean default false` · `flag_reason string(200) null` · `escalated_at timestamp null`.
`assistant_messages` : `id` · `conversation_id FK cascade` · `role string(10)` (`user`, `assistant`, `system`) · `body text [chiffré]` · `matched_kb_id FK null` · `created_at`.

### `bad_words`
`id` · `word string(80) unique` · `severity string(10)` (`BadWordSeverity` : `bloquer`, `signaler`) · `is_active boolean default true`.

## 4. Réseau social

### `posts` [soft delete]
`id` · `ulid char(26) unique` · `user_id FK null nullOnDelete` · `group_id FK null nullOnDelete` · `page_id FK null nullOnDelete` · `is_anonymous boolean default false` · `body text` (≤ 3000) · `tag string(20)` (`PostTag` : `temoignage`, `question`, `ressource`, `evenement`, `violences`, `sondage`) · `visibility string(10) default public` (`PostVisibility` : `public`, `followers`, `me`) · `likes_count unsignedInteger default 0` · `comments_count unsignedInteger default 0` · `reports_count unsignedSmallInteger default 0` · `status string(10) default publie` (`PostStatus` : `publie`, `masque`, `supprime`) · `hidden_reason string(200) null` · `published_at timestamp`. Index `(status, created_at)`, `(user_id, status)`, `(tag, status)`.

### `post_media`
`id` · `post_id FK cascade` · `kind string(10)` (`image`, `video`, `audio`) · `path string(255)` · `alt string(255) null` · `width`, `height unsignedSmallInteger null` · `size unsignedInteger null` · `position unsignedTinyInteger default 0`.

### `post_likes` [sans updated_at]
`id` · `post_id FK cascade` · `user_id FK cascade` · `created_at`. Unique `(post_id, user_id)`.

### `post_saves` [sans updated_at]
`id` · `post_id FK cascade` · `user_id FK cascade` · `created_at`. Unique `(post_id, user_id)`. (« Enregistrer » du menu de publication.)

### `comments` [soft delete]
`id` · `post_id FK cascade` · `user_id FK null nullOnDelete` · `parent_id FK comments null cascade` · `body text` (≤ 1000) · `is_anonymous boolean default false` · `likes_count unsignedInteger default 0` · `status string(10) default publie` (`PostStatus`). Index `(post_id, created_at)`.

### `polls`, `poll_options`, `poll_votes`
`polls` : `id` · `post_id FK unique cascade` · `question string(200)` · `multiple boolean default false` · `results_before_vote boolean default false` · `ends_at timestamp null` · `votes_count unsignedInteger default 0` · `voters_count unsignedInteger default 0` · `closed_at timestamp null`.
`poll_options` : `id` · `poll_id FK cascade` · `label string(80)` · `position unsignedTinyInteger` · `votes_count unsignedInteger default 0`. Unique `(poll_id, label)`.
`poll_votes` [sans updated_at] : `id` · `poll_option_id FK cascade` · `poll_id FK cascade` (dénormalisé pour l'unicité en choix unique) · `user_id FK cascade` · `created_at`. Unique `(poll_option_id, user_id)` ; index `(poll_id, user_id)`.

### `follows` [sans updated_at]
`id` · `follower_id FK users cascade` · `followed_id FK users cascade` · `created_at`. Unique `(follower_id, followed_id)`.

### `invitations`
`id` · `from_user_id FK cascade` · `to_user_id FK cascade` · `status string(12) default en_attente` (`InvitationStatus` : `en_attente`, `acceptee`, `refusee`) · `responded_at timestamp null`. Unique `(from_user_id, to_user_id)`.

### `groups` [soft delete]
`id` · `ulid unique` · `name string(120)` · `slug string(140) unique` · `description text null` · `privacy string(10) default public` (`GroupPrivacy` : `public`, `prive`) · `zone_id FK null` (null = national) · `image_path string(255) null` · `cover_path null` · `owner_id FK users null nullOnDelete` · `members_count unsignedInteger default 0` · `posts_count unsignedInteger default 0` · `is_active boolean default true`.

### `group_members`
`id` · `group_id FK cascade` · `user_id FK cascade` · `role string(10) default membre` (`GroupRole` : `membre`, `moderateur`, `admin`) · `status string(10) default actif` (`MembershipStatus` : `demande`, `actif`, `banni`) · `joined_at timestamp null`. Unique `(group_id, user_id)`.

### `pages` [soft delete]
`id` · `ulid unique` · `name string(120)` · `slug unique` · `category string(60)` · `description text null` · `image_path`, `cover_path` · `website string(190) null` · `owner_id FK users null` · `followers_count unsignedInteger default 0` · `is_verified boolean default false` · `is_active boolean default true`.

### `page_followers` [sans updated_at]
`id` · `page_id FK cascade` · `user_id FK cascade` · `created_at`. Unique `(page_id, user_id)`.

### `events` [soft delete]
`id` · `ulid unique` · `title string(200)` · `slug unique` · `category_id FK null` · `starts_at timestamp` · `ends_at timestamp null` · `place string(200) null` · `is_online boolean default false` · `online_url string(500) null` · `description text null` · `image_path null` · `organizer_id FK users null nullOnDelete` · `organizer_label string(120) null` · `group_id FK null` · `page_id FK null` · `going_count unsignedInteger default 0` · `status string(10) default publie` (`ContentStatus`) · `reminder_sent_at timestamp null`. Index `(starts_at, status)`.

### `event_rsvps`
`id` · `event_id FK cascade` · `user_id FK cascade` · `status string(10) default going` (`RsvpStatus` : `going`, `interested`, `declined`) · `reminder_opt_in boolean default true`. Unique `(event_id, user_id)`.

### `forums`, `forum_topics`, `forum_replies`
`forums` : `id` · `key string(10) unique` (`f1`…) · `name string(120)` · `slug unique` · `description string(255)` · `icon string(40)` · `position` · `topics_count`, `posts_count unsignedInteger default 0` · `is_active`.
`forum_topics` [soft delete] : `id` · `ulid unique` · `forum_id FK restrict` · `user_id FK null nullOnDelete` · `title string(200)` · `body text` · `is_anonymous boolean default false` · `views_count unsignedInteger default 0` · `replies_count unsignedInteger default 0` · `last_reply_at timestamp null` · `is_pinned boolean default false` · `is_locked boolean default false` · `status` (`PostStatus`). Index `(forum_id, last_reply_at)`.
`forum_replies` [soft delete] : `id` · `topic_id FK cascade` · `user_id FK null nullOnDelete` · `body text` · `is_anonymous boolean` · `status` (`PostStatus`).

### `albums`, `album_media`
`albums` [soft delete] : `id` · `ulid unique` · `user_id FK cascade` · `kind string(10) default custom` (`profile`, `cover`, `custom`) · `title string(120)` · `description string(255) null` · `visibility string(10) default community` (`ProfileVisibility` : `public`, `community`, `followers`, `private` — l'enum `PostVisibility` initialement citée ne connaît pas la valeur par défaut `community` ; un album suit la logique de visibilité du profil dont il dépend) · `media_count unsignedInteger default 0` · `cover_media_id unsignedBigInteger null` (pas de FK : référence circulaire vers `album_media`, cohérence applicative).
`album_media` : `id` · `album_id FK cascade` · `path string(255)` · `kind string(10) default image` (`image`, `video`) · `alt string(255) null` · `width`, `height unsignedSmallInteger null` · `size unsignedInteger null` · `position unsignedTinyInteger default 0`.

### `conversations`, `conversation_participants`, `messages`
`conversations` : `id` · `ulid unique` · `kind string(10) default private` (`ConversationKind` : `private`, `group`) · `title string(120) null` · `group_id FK null` · `created_by FK users null` · `last_message_at timestamp null` · `last_message_preview string(120) null`.
`conversation_participants` : `id` · `conversation_id FK cascade` · `user_id FK cascade` · `role string(10) default member` · `last_read_message_id unsignedBigInteger null` · `muted_until timestamp null` · `left_at timestamp null`. Unique `(conversation_id, user_id)`.
`messages` : `id` · `conversation_id FK cascade` · `user_id FK null nullOnDelete` · `body text [chiffré]` · `media_path string(255) null` · `client_uuid char(36) null` (idempotence hors ligne) · `delivered_at`, `read_at timestamp null` · `deleted_for_everyone_at timestamp null`. Index `(conversation_id, id)` ; unique `(conversation_id, client_uuid)`.

## 5. Alertes et annuaire

### `directory_contacts` [soft delete]
`id` · `name string(120)` · `role string(40)` (`DirectoryRole`) · `org string(120) null` · `phone string(30) null` · `email string(190) null` · `whatsapp string(30) null` · `zone_id FK null` · `commune_id FK null` · `availability string(120) null` · `is_active boolean default true` · `is_public boolean default true` (visible dans l'annuaire membre) · `is_national_focal boolean default false` (point focal national, RG-04 / RG-05) · `user_id FK users null nullOnDelete` (compte lié pour les alertes « routées vers moi ») · `notes string(500) null`. Index `(role, zone_id, is_active)`.

### `alerts` [pas de soft delete ; `restrict`]
`id` · `ulid unique` · `type string(15)` (`AlertType` : `denonciation`, `besoin`, `sos`) · `user_id FK null nullOnDelete` · `is_anonymous boolean default true` · `pseudo string(40) null` · `category string(20) null` (slug de `violence_types` ou null) · `description text null [chiffré]` · `needs json null` (slugs de `needs`) · `zone_id FK null` · `commune_id FK null` · `lat text null [chiffré]` · `lng text null [chiffré]` · `location_accuracy unsignedSmallInteger null` · `contact text null [chiffré]` · `status string(15) default nouveau` (`AlertStatus`) · `priority string(10) default normale` (`AlertPriority` : `normale`, `haute`, `critique`) · `routed_to FK directory_contacts null nullOnDelete` · `routing_fallback string(20) null` (`role_zone`, `focal_zone`, `focal_national`, `first_active`) · `acknowledged_at timestamp null` · `acknowledged_by FK users null` · `escalated_at timestamp null` · `closed_at timestamp null` · `cancelled_at timestamp null` (SOS annulé) · `safe_at timestamp null` (« Je suis en sécurité ») · `tracking_code char(10) unique` · `source string(15) default web` (`AlertSource`) · `idempotency_key char(36) null unique` · `device_id FK sync_devices null`. Index `(status, created_at)`, `(type, status)`, `(routed_to, status)`.

### `alert_notes` [sans updated_at]
`id` · `alert_id FK restrict` · `user_id FK null` · `body text [chiffré]` · `created_at`.

### `alert_notifications`
`id` · `alert_id FK restrict` · `channel string(10)` (`NotificationChannel` : `push`, `sms`, `mail`, `database`) · `recipient string(190)` (numéro, e-mail ou `user:<id>`) · `directory_contact_id FK null` · `status string(10) default pending` (`DeliveryStatus` : `pending`, `sent`, `failed`) · `attempts unsignedTinyInteger default 0` · `last_error string(255) null` · `sent_at timestamp null` · `next_retry_at timestamp null`. Index `(status, next_retry_at)`.

### `alert_escalations` [sans updated_at]
`id` · `alert_id FK restrict` · `from_contact_id FK null` · `to_contact_id FK null` · `reason string(40)` (`no_ack_5min`, `manual`) · `created_at`.

### `alert_status_changes` [sans updated_at]
`id` · `alert_id FK restrict` · `user_id FK null` · `from_status string(15) null` · `to_status string(15)` · `created_at`. (Historique affiché par le suivi par code.)

## 6. Dossiers (pair·es)

### `validation_rules`
`id` · `key string(40) unique` (`required`, `email`, `phone_bj`, `date_not_future`, `age_range`, `min_len_20`, `positive_int`, `commune_in_zone`, `case_code`, `password_strong`, `plausibility`, `duplicates`) · `label string(120)` · `description string(255)` · `is_active boolean default true` · `is_locked boolean default false` (`required` est verrouillée : toujours active, non désactivable) · `params json null` (plausibilité : `age_min`, `age_max`, `max_past_years`, `lat`, `lng` ; doublons : `fields`) · `valid_example string(120) null` · `invalid_example string(120) null` · `position`.

### `cases` [pas de soft delete ; statut `archive`]
`id` · `code string(10) unique` (`VBG-0000` / `VDH-0000`) · `kind string(3)` (`CaseKind` : `VBG`, `VDH`) · `occurred_on date` · `zone_id FK restrict` · `commune_id FK null` · `population_id FK null` · `sex string(10) null` (`Sex`) · `age_range string(10) null` (`AgeRange` : tranche d'âge, jamais un âge exact) · `violence_type_id FK null` · `status string(10) default ouvert` (`CaseStatus` : `ouvert`, `en_cours`, `clos`, `archive`) · `collected_by FK users restrict` · `referral string(80) null` (service d'orientation : Point focal, Juriste, Psychologue, Gynécologue, Hébergement) · `notes text null [chiffré]` (≥ 20 caractères, règle `min_len_20`) · `source string(15) default web` (`AlertSource`) · `client_uuid char(36) null unique` (synchronisation) · `device_id FK sync_devices null` · `closed_at timestamp null` · `archived_at timestamp null` · `anonymized_at timestamp null`. Index `(zone_id, occurred_on)`, `(collected_by, status)`, `(status, created_at)`.

### `case_forms`
`id` · `case_id FK restrict` · `reference string(14) unique` (`FR-YYYY-0001`) · `created_by FK users null` · `identification json` (pseudo, sexe, âge, population, zone, commune — pseudonymisé) · `facts_date date` · `facts_place string(120) null` · `facts_type string(40) null` · `facts_author string(80) null [chiffré]` · `facts_description text [chiffré]` · `services json` (clés : `ecoute`, `medical`, `psy`, `juridique`, `hebergement`, `dignite`, `securite`, `autre`) · `services_note text null [chiffré]` · `referral_contact_id FK directory_contacts null` · `referral_label string(120) null` · `referral_date date null` · `referral_reason string(255) null` · `referral_urgency string(10) null` · `consent_given boolean` · `consent_mode string(10)` (`ecrit`, `oral`) · `consent_sharing boolean default false` · `consent_signatory string(80) null [chiffré]`.

### `case_followups`
`id` · `case_id FK restrict` · `date date` · `step string(80)` · `by_user_id FK users null nullOnDelete` · `outcome string(10)` (`FollowupOutcome` : `planifie`, `realise`, `reporte`, `annule`) · `next_on date null` · `comment text null [chiffré]` · `client_uuid char(36) null unique`. Index `(case_id, date)`.

## 7. Pilotage et modération

### `indicators`
`id` · `code string(10) unique` (`IND-01`…) · `label string(160)` · `definition text` · `formula string(255)` · `source string(80)` · `frequency string(15)` (`IndicatorFrequency` : `hebdomadaire`, `mensuelle`, `trimestrielle`, `semestrielle`, `annuelle`) · `target decimal(10,2) null` · `unit string(5)` (`nb`, `%`) · `calc_key string(40) null` (null = manuel) · `better string(4) default up` (`up`, `down`) · `is_builtin boolean default false` · `is_active boolean default true` · `position`.

### `indicator_values`
`id` · `indicator_id FK cascade` · `period_start date` · `period_end date` · `zone_id FK null` · `value decimal(12,2)` · `is_manual boolean default false` · `computed_at timestamp`. Unique `(indicator_id, period_start, period_end, zone_id)`.

### `generated_reports`
`id` · `title string(160)` · `kind string(20)` (`ReportKind` : `mensuel`, `trimestriel`, `semestriel`, `annuel`, `bailleur`, `personnalise`) · `period_start date` · `period_end date` · `dimensions json` · `format string(10)` (`ReportFormat` : `pdf`, `xlsx`, `docx`, `apercu`) · `filters json null` · `rows_count unsignedInteger null` · `file_path string(255) null` · `file_size unsignedInteger null` · `status string(10) default pending` (`JobStatus` : `pending`, `running`, `done`, `failed`) · `error string(255) null` · `recipients json null` · `generated_by FK users null` · `completed_at timestamp null` · `expires_at timestamp null`.

### `report_schedules`
`id` · `kind string(20)` (`ReportKind`) · `frequency string(15)` (`mensuel`, `trimestriel`, `semestriel`, `annuel`) · `day_of_period unsignedTinyInteger default 1` (1–28) · `formats json` · `recipients json` · `dimensions json` · `is_active boolean default true` · `last_run_at timestamp null` · `next_run_at timestamp null` · `created_by FK users null`.

### `moderation_reports`
`id` · `kind string(15)` (`ModerationKind` : `publication`, `commentaire`, `message`, `utilisateur`, `sujet`, `reponse`) · `subject_type string(80)` · `subject_id unsignedBigInteger` · `excerpt string(255) null` · `reported_user_id FK users null nullOnDelete` · `reporter_user_id FK users null nullOnDelete` · `reporter_label string(80) null` · `reason string(80)` · `details string(500) null` · `status string(10) default en_attente` (`ModerationStatus` : `en_attente`, `traite`, `rejete`) · `action string(40) null` (`masque`, `supprime`, `avertissement`, `suspension`, `aucune`) · `handled_by FK users null` · `handled_at timestamp null`. Index `(status, created_at)`, `(subject_type, subject_id)`.

### `user_warnings` [sans updated_at]
`id` · `user_id FK cascade` · `issued_by FK users null` · `reason string(200)` · `moderation_report_id FK null` · `created_at`.

## 8. Passerelles réseaux sociaux (D4-bis)

### `social_channels`
`id` · `network string(20) unique` (`SocialNetwork` : `facebook`, `tiktok`, `romeo`, `ganxo`, `telegram`, `tinder`) · `label string(40)` · `icon string(40)` · `color string(20)` · `supports_api boolean` · `is_connected boolean default false` · `account_handle string(60) null` · `access_token text null [chiffré]` · `refresh_token text null [chiffré]` · `token_expires_at timestamp null` · `external_id string(120) null` · `connected_at timestamp null` · `connected_by FK users null` · `last_error string(255) null`.

### `gateway_templates`
`id` · `title string(80)` · `body string(500)` · `category string(40) null` (`prevention`, `depistage`, `sos`…) · `is_active boolean default true` · `position`.

### `outbound_posts`
`id` · `kind string(10)` (`OutboundKind` : `bd`, `template`, `custom`) · `reference string(120) null` (clé de BD ou id de modèle) · `message string(500)` · `media_path string(255) null` · `scheduled_at timestamp` · `status string(10) default planifie` (`OutboundStatus` : `planifie`, `publie`, `annule`, `echec`) · `published_at timestamp null` · `created_by FK users null`. Index `(status, scheduled_at)`.

### `outbound_deliveries`
`id` · `outbound_post_id FK cascade` · `social_channel_id FK cascade` · `status string(10) default en_attente` (`GatewayDeliveryStatus` : `en_attente`, `publie`, `echec`, `manuel`) · `external_id string(120) null` · `external_url string(500) null` · `error string(255) null` · `attempts unsignedTinyInteger default 0` · `published_at timestamp null` · `marked_by FK users null` (publication manuelle assistée). Unique `(outbound_post_id, social_channel_id)`.

### `peer_rooms`
`id` · `name string(120)` · `slug unique` · `description string(255) null` · `is_active boolean default true` · `is_moderated boolean default true` · `allow_anonymous boolean default true` · `max_members unsignedSmallInteger default 50` · `conversation_id FK null` (salon = conversation de groupe) · `created_by FK users null`.

## 9. Communication

### `email_templates`
`id` · `name string(80)` · `slug string(60) unique` · `category string(20)` (`TemplateCategory` : `authentification`, `communaute`, `alertes`, `newsletter`, `autre`) · `is_system boolean default false` · `subject string(150)` · `preheader string(150) null` · `body longtext` (HTML nettoyé) · `variables json null` (liste des variables attendues) · `is_active boolean default true` · `updated_by FK users null`. Seed : 8 templates (`email_templates.json`).

### `mail_logs` [sans updated_at]
`id` · `to string(190)` · `subject string(160)` · `template_slug string(60) null` · `context string(40) null` · `transport string(10)` (`MailTransport` : `smtp`, `outbox`, `none`) · `ok boolean` · `error string(255) null` · `message_id string(190) null` · `created_at`. Index `(created_at)`, `(template_slug, created_at)`. Rétention 180 j.

### `mail_outbox`
`id` · `to string(190)` · `from_address string(190)` · `from_name string(80) null` · `reply_to string(190) null` · `subject string(160)` · `html longtext` · `text longtext null` · `template_slug string(60) null` · `attempts unsignedTinyInteger default 0` · `sent_at timestamp null` · `last_error string(255) null`.

### `newsletter_subscribers`
`id` · `email string(190) unique` · `name string(80) null` · `status string(12) default en_attente` (`SubscriberStatus` : `en_attente`, `actif`, `desabonne`, `rebond`) · `tags json null` · `interests json null` · `source string(10) default footer` (`SubscriberSource` : `footer`, `import`, `admin`, `app`) · `token char(32) unique` · `confirmed_at timestamp null` · `confirmation_sent_at timestamp null` · `unsubscribed_at timestamp null` · `unsubscribe_reason string(200) null` · `bounced_at timestamp null` · `bounce_reason string(200) null` · `consent_meta string(255) null` · `user_id FK users null nullOnDelete` · `last_opened_at timestamp null`. Index `(status)`.

### `newsletter_tags`, `newsletter_interests`, `newsletter_subscriber_tags`, `newsletter_subscriber_interests`
`newsletter_tags` : `id` · `slug string(40) unique` · `label string(60)`. `newsletter_interests` : idem. Liaisons [sans timestamps] : `subscriber_id FK cascade` + `tag_id` / `interest_id FK cascade`, unique sur le couple. Seed : tags (`membre`, `pair`, `bailleur`, `partenaire`, `presse`), intérêts (Santé, Droits, Bien-être, Événements, Ressources, Sécurité numérique).

### `newsletter_campaigns` [soft delete]
`id` · `subject string(150)` · `from_name string(60)` · `reply_to string(190)` · `preheader string(150) null` · `body longtext` · `segment json` (`{tags[], interests[], since, until}`) · `status string(12) default brouillon` (`CampaignStatus` : `brouillon`, `planifiee`, `en_cours`, `envoyee`, `annulee`) · `scheduled_at timestamp null` · `started_at`, `sent_at timestamp null` · `recipients_count unsignedInteger default 0` · `sent_count unsignedInteger default 0` · `test_sent_to string(190) null` · `created_by FK users null`. Index `(status, scheduled_at)`.

### `newsletter_campaign_recipients`
`id` · `campaign_id FK cascade` · `subscriber_id FK cascade` · `sent_at timestamp null` · `opened_at timestamp null` · `bounced boolean default false` · `unsubscribed boolean default false` · `error string(255) null`. Unique `(campaign_id, subscriber_id)`. Index `(campaign_id, sent_at)`.

### `newsletter_clicks` [sans updated_at]
`id` · `recipient_id FK cascade` · `url string(2048)` · `url_hash char(64)` · `clicked_at timestamp` · `clicks_count unsignedSmallInteger default 1`. Unique `(recipient_id, url_hash)`.

### `notifications`
Migration Laravel (`uuid`, `type`, `notifiable`, `data json`, `read_at`). Colonne ajoutée : `category string(30) null` indexée (préférences par catégorie), `is_sensitive boolean default false`.

### `push_subscriptions`
`id` · `user_id FK cascade` · `kind string(10)` (`webpush`, `fcm`) · `endpoint text` · `endpoint_hash char(64) unique` · `public_key string(255) null` · `auth_token string(255) null` · `device_label string(80) null` · `last_used_at timestamp null`.

## 10. Plateforme

### `settings`
`id` · `key string(80) unique` · `group string(30)` (`general`, `securite`, `smtp`, `identite`, `newsletter`, `synchro`, `assistant`, `maintenance`, `inscriptions`, `optimisation`) · `type string(10)` (`bool`, `int`, `string`, `json`, `secret`) · `value json null` (chiffré si `secret`) · `is_public boolean default false` · `updated_by FK users null`.

### `app_versions`
`id` · `version string(20) unique` · `kind string(20)` (`correctif`, `fonctionnalite`, `securite`) · `notes text null` · `status string(12)` (`planifiee`, `deployee`, `annulee`) · `scheduled_for timestamp null` · `deployed_at timestamp null` · `created_by FK null`.

### `maintenance_windows`
`id` · `starts_at timestamp` · `ends_at timestamp` · `message string(255) null` · `is_active boolean default true` · `created_by FK null`.

### `backups`
`id` · `name string(40) unique` · `kind string(20)` (`BackupKind` : `manuel`, `auto`, `avant_restauration`) · `size_bytes unsignedBigInteger` · `tables json` (`[{name, rows}]`) · `storage_disk string(20)` · `storage_path string(255)` · `checksum char(64) null` · `status string(10) default done` (`JobStatus`) · `created_by FK null` · `restored_at timestamp null` · `restored_by FK null`.

### `audit_logs` [sans updated_at ; aucune route de modification]
`id` · `user_id FK null nullOnDelete` · `actor string(190) null` · `action string(80)` · `subject_type string(80) null` · `subject_id unsignedBigInteger null` · `detail text null` · `ip string(45) null` · `user_agent string(255) null` · `created_at`. Index `(created_at)`, `(user_id, created_at)`, `(subject_type, subject_id)`.

### `sync_devices`
`id` · `user_id FK cascade` · `device_uuid char(36) unique` · `label string(80)` · `platform string(40) null` (`Android 13 · Redmi 10`) · `app_version string(20) null` · `last_seen_at timestamp null` · `last_sync_at timestamp null` · `pending_count unsignedInteger default 0` · `conflicts_count unsignedInteger default 0` · `is_revoked boolean default false`.

### `sync_outbox_receipts` [sans updated_at]
`id` · `device_id FK cascade` · `idempotency_key char(36)` · `kind string(20)` (`case`, `followup`, `alert`, `sos`, `message`) · `subject_type string(80) null` · `subject_id unsignedBigInteger null` · `result string(10)` (`created`, `duplicate`, `conflict`, `rejected`) · `payload_hash char(64)` · `created_at`. Unique `(device_id, idempotency_key)`.

### `sync_conflicts`
`id` · `device_id FK null` · `subject_type string(80)` · `subject_id unsignedBigInteger` · `field string(40)` · `server_value text null [chiffré]` · `device_value text null [chiffré]` · `status string(12) default a_resoudre` (`ConflictStatus` : `a_resoudre`, `resolu`) · `resolution string(10) null` (`serveur`, `appareil`, `fusion`) · `resolved_value text null [chiffré]` (les valeurs peuvent reproduire des notes de dossier) · `resolved_by FK users null` · `resolved_at timestamp null`. Index `(status, created_at)`.

### `on_call_shifts` (planning de garde, P1-17)
`id` · `user_id FK users cascade` · `starts_at timestamp` · `ends_at timestamp` · `kind string(20) default admin` (`admin` : admin de garde ; `focal` : remplaçant·e du point focal) · `zone_id FK zones null nullOnDelete` (null = national) · `notes string(200) null`. Index `(starts_at, ends_at)`. Lu par `App\Application\Alerts\OnCallRoster` (repli : admins et superadmins actifs ayant `alerts.take`).

### `sync_cursors` (côté serveur, par appareil et par collection)
`id` · `device_id FK cascade` · `collection string(40)` · `cursor unsignedBigInteger default 0` · `pulled_at timestamp null`. Unique `(device_id, collection)`.

## 11. Enums PHP (`app/Enums`)

`UserStatus`, `MemberType`, `ProfileVisibility`, `AgeRange`, `Sex`, `DirectoryRole`, `OtpPurpose`, `ConsentKind`, `ContentStatus`, `ResourceKind`, `CccKind`, `BadWordSeverity`, `PostTag`, `PostVisibility`, `PostStatus`, `InvitationStatus`, `GroupPrivacy`, `GroupRole`, `MembershipStatus`, `RsvpStatus`, `ConversationKind`, `AlertType`, `AlertStatus`, `AlertPriority`, `AlertSource`, `NotificationChannel`, `DeliveryStatus`, `CaseKind`, `CaseStatus`, `FollowupOutcome`, `IndicatorFrequency`, `ReportKind`, `ReportFormat`, `JobStatus`, `ModerationKind`, `ModerationStatus`, `SocialNetwork`, `OutboundKind`, `OutboundStatus`, `GatewayDeliveryStatus`, `TemplateCategory`, `MailTransport`, `SubscriberStatus`, `SubscriberSource`, `CampaignStatus`, `BackupKind`, `ConflictStatus`.

Chaque enum : `string` backed, méthodes `label(): string` (texte depuis `lang/fr/enums/<domaine>.php` : `reference`, `identity`, `content`, `feed`, `community`, `alerts`, `cases`, `steering`, `gateways`, `communication`, `platform`) et `badge(): string` (variante Bootstrap : `success`, `warning`, `danger`, `secondary`, `info`, `primary`, `violet`). Le modèle de la table `cases` s'appelle `CaseRecord` (`case` est un mot réservé de PHP).

## 12. Ordre des migrations (lots)

Identique à `DATABASE_ENGINEERING.md` §4, avec les tables ajoutées :

1. Référentiels : `zones`, `communes`, `violence_types`, `populations`, `needs`, `categories`.
2. Identité : `users` (modifiée : colonnes ajoutées à la migration Laravel), `profiles`, `user_settings`, spatie, `personal_access_tokens`, `otp_codes`, `consents`, `user_blocks`.
3. Contenus : `thematiques`, `resources`, `faqs`, `laws`, `ccc_tools`, `assistant_kb`, `assistant_conversations`, `assistant_messages`, `bad_words`.
4. Réseau social : `groups`, `pages`, `posts`, `post_media`, `post_likes`, `post_saves`, `comments`, `polls`, `poll_options`, `poll_votes`, `follows`, `invitations`, `group_members`, `page_followers`, `albums`, `album_media`, `events`, `event_rsvps`, `forums`, `forum_topics`, `forum_replies`, `conversations`, `conversation_participants`, `messages`.
5. Alertes : `sync_devices` (avancé ici car référencé par `alerts` et `cases`), `directory_contacts`, `alerts`, `alert_notes`, `alert_notifications`, `alert_escalations`, `alert_status_changes`.
6. Dossiers : `validation_rules`, `cases`, `case_forms`, `case_followups`.
7. Pilotage et modération : `indicators`, `indicator_values`, `generated_reports`, `report_schedules`, `moderation_reports`, `user_warnings`.
8. Passerelles : `social_channels`, `gateway_templates`, `outbound_posts`, `outbound_deliveries`, `peer_rooms`.
9. Communication : `email_templates`, `mail_logs`, `mail_outbox`, `newsletter_tags`, `newsletter_interests`, `newsletter_subscribers`, `newsletter_subscriber_tags`, `newsletter_subscriber_interests`, `newsletter_campaigns`, `newsletter_campaign_recipients`, `newsletter_clicks`, `notifications`, `push_subscriptions`.
10. Plateforme : `settings`, `app_versions`, `maintenance_windows`, `backups`, `audit_logs`, `sync_outbox_receipts`, `sync_conflicts`, `sync_cursors` (`jobs`, `failed_jobs`, `cache` : migrations Laravel existantes).
11. Compléments (lot 12) : `on_call_shifts`.

La liste des colonnes chiffrées est tenue dans `config/xomiton.php` → `encrypted_columns` (vérifiée par test contre les casts des modèles) et sert à `xomiton:reencrypt` (rotation d'`APP_KEY`).

Nommage des fichiers : `2026_10_18_<lot sur 2 chiffres>0<ordre>_create_<table>_table.php` pour garantir l'ordre. Implémentation : les lots 4 et 5 ci-dessus sont découpés en « 04 réseau social » et « 05 communauté (événements, forums, conversations) », d'où les préfixes réels `06` alertes, `07` dossiers, `08` pilotage et modération, `09` passerelles, `10` communication, `11` plateforme. La migration `users` reste celle de Laravel (`0001_01_01_000000`) complétée par `2026_10_18_020000_add_foreign_keys_to_users_table.php` (zones et communes sont créées après `users`).
