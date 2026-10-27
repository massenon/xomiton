# État des lieux — Xomiton au 27/10/2026

Photographie de l'avancement, tirée de `IMPLEMENTATION_PLAN.md` §3 et §8 (statuts remis d'équerre le 27/10/2026, vérifiés par grep et `ls` dans le code). Les décisions en attente sont dans [`DECISIONS_A_VALIDER.md`](DECISIONS_A_VALIDER.md).

Légende : **fait** = codé, testé, rien à ajouter côté code · **partiel** = codé, il reste un point nommé · **recette** = codé, attend la validation sur l'hébergement cible ou par le client · **à faire**.

## 1. Phases

| Phase | Statut | Reste (résumé) |
|---|---|---|
| P0 Socle | partiel | CI : jobs `e2e` et `lighthouse` absents ; `APP_PREVIOUS_KEYS` à documenter ; relecture du README |
| P1 Données | fait | — |
| P2 Identité et accès | fait | `verified` sur les routes sociales, Q43 à Q46 |
| P3 Noyau transverse | partiel | `notifications:digest` et `alerts:anonymize` n'existent pas (P3-07) |
| P4 Site public | recette | recette visuelle aux 4 largeurs, Lighthouse CI, textes à valider par le client |
| P5 Alertes, SOS, signalement | fait (recette) | fournisseurs SMS / push, clés VAPID / FCM, recette terrain du SOS |
| P6 API et coquille Vue | partiel | P6-03, P6-04 : comparaison aux 4 largeurs, réalignement du DS Blade (Q22) |
| P7 Fil et sondages | partiel | P7-03 à P7-05 : comparaison aux 4 largeurs, Playwright du fil |
| P8 Communauté et profil | partiel | P8-01 (filtre de mots), P8-09 (PIN web), P8-12 (médiathèque, `/ccc`, « Mes signalements ») |
| P9 Espace pair et synchro | partiel | P9-04 précache SW de `/collecte` (lot `pwa-pair`), P9-08 indicateurs pair et appareils synchronisés |
| P10 Admin socle et pilotage | partiel | P10-01 filtres globaux `saved_filters` ; P10-06 balise de contenu non câblée dans les pages publiques |
| P11 Admin données | partiel | P11-02 : édition d'une fiche déjà créée (absente du code) |
| P12 Admin plateforme | fait | conformité visuelle non contrôlée en navigateur |
| P13 Admin système | fait | idem |
| P14 Communication | recette | P14-04 : transport HTTP réel Facebook / Telegram à écrire ; TikTok sans connecteur |
| P15 Mobile Flutter | partiel | 8 modules codés sur 9 ; P15-09 (espace pair) sans code ; tests d'intégration, iOS, `sync/pull` |
| P16 Qualité | partiel | P16-05 et P16-07 à faire ; couverture, Firefox / WebKit / 320 px, Lighthouse CI |
| P17 Déploiement | partiel | tout ce qui exige le serveur, le DNS et les comptes de publication |

## 2. Livré et vérifié

- Back-end Laravel 12 : 94 migrations, 57 modèles, 47 enums, import du prototype, rétention et anonymisation, ré-encryption (`xomiton:reencrypt`), 24 policies, audit, OTP, API Sanctum v1 documentée (`API.md`).
- Alertes : routage RG-04, escalade RG-05 (`alerts:escalate` chaque minute), SOS, signalement anonyme, boîte d'envoi et service worker.
- Espace membre Vue/Inertia (fil, sondages, messagerie, groupes, pages, événements, forums, profil, paramètres, assistant, recherche), synchro `sync/pull` / `sync/push` avec conflits 409.
- Administration Livewire complète (D1 à D6) : tableau de bord, alertes, indicateurs, rapports PDF / Excel / Word, registres, fiches, utilisateurs, contenu, modération, passerelles, sauvegardes chiffrées, paramètres, maintenance, journal.
- Newsletter : campagnes, envoi par lots (200 / min), suivi HMAC, double opt-in.
- Mobile : auth, accueil, SOS natif, signalement, fil, messages, profil, PIN local, push FCM (repli sans Firebase), mode discret Android.
- Qualité mesurée : axe-core 0 violation (12 pages publiques, 3 pages membre, clair et sombre, 360 et 1280 px), `lang:lint` en CI, `composer audit` propre, budgets JS (`check-budgets.mjs`), Larastan niveau 6 en CI (paquet dans `composer.json` / `composer.lock`).
- Exploitation rédigée : `RUNBOOK.md`, `DEPLOIEMENT_CPANEL.md`, `SECURITE_CHECKLIST.md`, `PERFORMANCE.md`, scripts `deploy.sh` et `backup.sh`.

## 3. Chiffres (derniers résultats connus)

| Suite | Résultat |
|---|---|
| Pest (PHP) | 2755 tests |
| Vitest | 771 tests |
| Flutter | 151 tests (15 fichiers dans `mobile/test`) |
| Playwright | 4 specs (`a11y`, `parcours`, `offline`, `violences`), Chromium seulement, hors CI |
| Plan | 166 tâches P0-01 à P17-07 |

Pest 2663 était le dernier chiffre noté dans le plan (26/10) ; 2755 vient du dernier passage communiqué. Aucun test n'a été rejoué pour ce document.

## 4. Ce qui reste, classé

### 4.1 Code (sans dépendance externe)
1. P3-07 : `notifications:digest` (le réglage `daily` / `weekly` n'a aucun effet) et `alerts:anonymize`.
2. P11-02 : édition d'une fiche déjà créée.
3. P0-06 : jobs CI `e2e` (Playwright + axe) et `lighthouse` (`lighthouserc.json`) ; `grep fa-` en CI.
4. P14-04 : transport HTTP réel Graph API / Bot API (sous réserve de Q7 et de jetons).
5. P10-06 : émettre la balise `POST /evenements-contenu/{type}` depuis les pages publiques (5 lignes du tableau « Contenu » à 0).
6. P9-04 `pwa-pair`, P9-08 `espace-pair-indicateurs` et `parametres-appareils-sync`, P10-01 `saved_filters`, P8-12 médiathèque / `/ccc` / « Mes signalements », P8-09 PIN web.
7. P15 : tests d'intégration (SOS hors ligne, rejeu, PIN), `sync/pull` mobile, iOS (APNs, icône alternative), tests Playwright manquants (P16-04).
8. P0-05 : documenter `APP_PREVIOUS_KEYS` dans `.env.example`.

### 4.2 Humain (client, éditorial, juridique, design)
- Décisions Q ouvertes : voir `DECISIONS_A_VALIDER.md`.
- Textes à valider : accueil (Q53 à Q56), confidentialité et aide (Q67), âge minimal (Q68), quiz santé (Q64), 18 questions FAQ (Q58).
- P15-09 : maquettes de l'espace pair mobile à produire puis valider avant tout code.
- P16 : signature de la checklist sécurité, revue éditoriale et écriture inclusive, lecteurs d'écran (NVDA, VoiceOver, TalkBack), comparaison « à l'identique » (P16-05, captures 4 largeurs).
- P17-05 : relecture du runbook par la coordination, procédures rejouées une fois.

### 4.3 Serveur (hébergement cPanel, DNS)
- P17-01 assainissement de l'hébergement compromis (D7) avant tout déploiement ; P17-02 recette (PHP 8.3, extensions, `.cpanel.yml`) ; P17-03 cron et supervision ; P17-04 test de restauration ; P17-06 bascule DNS / HTTPS.
- Mesures sur l'hébergement cible : p95 < 600 ms (P16-02), Lighthouse, appels `proc_open` / `mysqldump`.

### 4.4 Comptes externes
- Firebase (FCM, `google-services.json`, `GoogleService-Info.plist`), Play Console, App Store Connect (P17-07).
- Fournisseurs SMS de secours et LLM de l'assistant (Q7) ; compte Healthchecks.io / UptimeRobot ; bot Telegram et page Facebook (P14-04) ; stockage externe des sauvegardes (Q9).

## 5. Risques et limites connus
- Aucun fournisseur SMS / push réel : les pilotes `log` tiennent lieu de canal ; le SOS n'a pas été éprouvé en conditions réelles.
- Rien n'a tourné sur l'hébergement cible : performances, extensions PHP, cron et zip chiffré sont des hypothèses.
- Couverture ≥ 70 % non mesurée (ni pcov ni xdebug) ; Playwright limité à Chromium et hors CI.
- Conformité visuelle au prototype aux 4 largeurs non vérifiée (P16-05) ; P12 et P13 non contrôlés en navigateur.
- `npm audit` : 4 alertes (chaîne de dev Vitest, `quill` basse).
- `mail_outbox` en clair (Q57) ; lecture des messages signalés par la modération (Q74) : à confirmer.
- Messagerie sans ETag (lectures `no-store`) : charge à surveiller sur cPanel.
- Budget JS serré : `app.ts` à environ 119 Ko gzip pour un plafond de 120 Ko.
- Mobile : aucune recette sur appareil consignée dans le plan ; iOS non configuré.
- La conformité légale (âge, confidentialité, santé) dépend de conseils extérieurs.
