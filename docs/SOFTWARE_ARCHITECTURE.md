---
name: xomiton-software-architecture
description: Architecture logicielle de Xomiton — Clean Architecture, Laravel 12 (API et domaine), Livewire 3 (administration), Vue 3 (espace membre et pair), Blade (pages publiques), Flutter (mobile), API /api/v1, moteur de synchronisation hors connexion et contraintes de l'hébergement mutualisé. À charger avant de créer un module, une route, un service, un job ou un écran Flutter.
---

# Architecture logicielle

## 1. Vue d'ensemble

```
                 ┌──────────────────────── Laravel 12 (un seul déploiement cPanel) ────────────────────────┐
 Navigateur ───► │ Blade x-*  (pages publiques SEO)   Vue 3 (espace membre/pair)   Livewire 3 (admin)       │
                 │        │                                 │                             │                  │
                 │        └──────────── Application (cas d'usage, services) ◄─────────────┘                  │
 Flutter  ─────► │ /api/v1 (Sanctum) ──► Domaine (entités, règles métier, événements)                         │
                 │                        Infrastructure (Eloquent, Mail SMTP, FCM, stockage, cron)          │
                 └───────────────────────────────────────────────────────────────────────────────────────────┘
                                   MySQL 8 · stockage local chiffré · cron chaque minute
```

## 2. Clean Architecture (Laravel)

Dépendances **vers l'intérieur** uniquement : `Presentation → Application → Domain`, et `Infrastructure` implémente les interfaces du domaine.

```
app/
├─ Domain/<Module>/            Entités et objets valeur (CaseCode, Zone, PollOption), règles (AlertRouter),
│                              événements (AlertCreated, SosTriggered), interfaces de dépôts
├─ Application/<Module>/       Cas d'usage (CreateReport, TriggerSos, SendCampaign, RestoreBackup),
│                              DTO, services applicatifs, politiques transverses
├─ Infrastructure/             Dépôts Eloquent, Mailer SMTP, PushNotifier FCM, BackupStorage, SyncResolver
├─ Http/
│  ├─ Controllers/Api/V1/      Contrôleurs fins : valident (Form Request), appellent un cas d'usage, renvoient une Resource
│  ├─ Controllers/Web/         Pages publiques Blade et points d'entrée Vue
│  ├─ Requests/                Validation serveur (une classe par action)
│  └─ Resources/               Transformation JSON (jamais de modèle brut en sortie)
├─ Livewire/Admin/<Module>/    Composants d'administration (Index, Form, Show), aucun accès SQL direct
├─ Policies/                   Autorisations par modèle
├─ Jobs/  Console/  Listeners/ Notifications/  Mail/
└─ Models/                     Modèles Eloquent (casts chiffrés, scopes)
```

Règles :
- un contrôleur ou un composant Livewire ne contient **aucune règle métier** : il délègue à un cas d'usage ;
- les règles métier (`RG-xx`) vivent dans le domaine et sont testées unitairement sans base de données ;
- un module = un dossier dans chaque couche (`Alerts`, `Cases`, `Directory`, `Feed`, `Messaging`, `Newsletter`, `EmailTemplates`, `Backups`…).

## 3. Couches de présentation

| Public | Technologie | Pourquoi |
|---|---|---|
| Pages publiques (accueil, thématiques, ressources, aide, signalement, SOS) | Blade + composants `x-*` du design system, Alpine pour l'interactivité légère | Rendu serveur, SEO, poids minimal, fonctionne sans JS |
| Espace membre et pair éducateur·rice (réseau social) | **Vue 3** (Composition API, Pinia) servi par Laravel via **Inertia.js** (D9), HTML du thème Social | Interactions riches (fil, sondages, messagerie, notifications) ; mêmes appels `/api/v1` que Flutter |
| Administration | **Livewire 3** + Alpine + Bootstrap Social | CRUD en modales, DataTables réactives, toasts **SweetAlert2**, validation en temps réel |
| Mobile | **Flutter** | Hors connexion, SOS natif, notifications push |

> **Décision actée D9** (`design-system/DECISIONS.md`) : l'espace membre et pair éducateur·rice est en Vue 3 + Inertia.js. Les composants Vue reproduisent exactement le HTML des composants `x-*` et du prototype (mêmes classes) pour garder un seul design ; les pages publiques restent en Blade, l'administration en Livewire.

## 4. API `/api/v1`

- REST JSON, authentification **Sanctum** (jeton pour Flutter, cookie de session pour Vue).
- Versionnée dans l'URL ; dépréciation annoncée 6 mois à l'avance.
- Réponses : `{ data, meta, links }` (Resources), erreurs `{ message, errors }` avec codes HTTP standard (401, 403, 404, 409 conflit de synchronisation, 422 validation, 429 limite).
- Pagination par curseur pour le fil et les messages, par page pour les listes d'administration.
- Limitation de débit : connexion 5/min, OTP 5/h, signalement 10/h par IP, SOS sans limite bloquante (journalisé).
- Idempotence : en-tête `Idempotency-Key` obligatoire pour `POST /signalements`, `POST /sos`, `POST /sync/push`.

Familles de routes :
```
auth/        login, register, otp/send, otp/verify, password/forgot, password/reset, logout
me/          profile, settings, security, newsletter, sessions, export, (DELETE) compte
feed/ posts/ likes, comments, poll/vote
conversations/ messages, block
groups/ pages/ events/ rsvp/ forums/
thematiques/ ressources/ urgences/ zones/
signalements/ sos/ (cancel, safe)
pair/        cases, followups, alerts (routées vers moi)
sync/        pull, push, conflicts
notifications/ read-all, unread-count
```

## 5. Moteur de synchronisation (hors connexion)

Objectif : collecte de cas, signalements et SOS **sans réseau**, puis envoi fiable.

1. **Côté appareil** (Flutter : Hive chiffré ; web : IndexedDB) : chaque écriture produit une entrée de **boîte d'envoi** `{uuid, type, payload, created_at, attempts}`.
2. **Envoi** : au retour du réseau (et toutes les 5 min), `POST /api/v1/sync/push` par lots de 50, avec `Idempotency-Key = uuid`. Le serveur rejoue sans doublon.
3. **Réception** : `GET /api/v1/sync/pull?since=<curseur>` renvoie les changements (annuaire, contacts d'urgence, thématiques, dossiers du pair) avec un nouveau curseur.
4. **Conflits** : stratégie par champ. Le serveur gagne pour le statut et l'attribution ; l'appareil gagne pour les notes du pair s'il est l'auteur ; sinon conflit `409` stocké dans `sync_conflicts` et résolu dans *Administration › Hors connexion & synchro* (valeur serveur / appareil / fusion, choix journalisé).
5. **Priorité** : le SOS n'attend jamais la boîte d'envoi si le réseau est disponible ; hors réseau, l'application propose l'appel direct aux numéros d'urgence en cache.

## 6. Tâches planifiées (cron chaque minute, D7)

```
* * * * * php artisan schedule:run
* * * * * php artisan queue:work --stop-when-empty --max-time=50
```
| Tâche | Fréquence | Rôle |
|---|---|---|
| `alerts:escalate` | 1 min | Escalade des SOS sans accusé (RG-05) |
| `notifications:retry` | 1 min | Rejoue les envois SOS échoués |
| `newsletter:process` | 1 min | Envoie les campagnes programmées par lots de 200 |
| `gateways:publish` | 1 min | Publie la file des passerelles (D4-bis) par réseau, rejoue les échecs, marque « manuel » les réseaux sans API |
| `heartbeat:ping` | 1 min | Signal de vie vers la supervision externe |
| `backups:run` | quotidien 02:00 | `mysqldump` + fichiers vers stockage externe chiffré |
| `logs:purge` | quotidien | Journaux de plus de 90 jours |
| `reports:generate` | à la demande, par morceaux | Exports volumineux |

## 7. Envoi d'e-mails

- `App\Mail\TemplateMail` (Mailable) + `App\Services\EmailTemplateService::send(string $slug, string $to, array $vars)` : cherche le template par slug, remplace les `{{variables}}` (échappées), applique la mise en page Xomiton, envoie par SMTP.
- Newsletter : `SendCampaignBatch` (job) personnalise chaque e-mail, réécrit les liens en liens de suivi signés (HMAC), ajoute le pixel d'ouverture, `List-Unsubscribe` et `List-Unsubscribe-Post`.
- Prototype équivalent : `prototype_social/inc/x_mailer.php`, `inc/x_newsletter.php`.

## 8. Flutter

```
lib/
├─ core/         thème (jetons du design system), routeur go_router, client Dio, stockage Hive, sync
├─ features/
│  ├─ auth/      splash (onboardingProvider), login, signup, otp, forgot, reset
│  ├─ home/      dashboard, thematiques, ressources, notifications
│  ├─ sos/       sighub, sos, signalement, urgences
│  ├─ feed/      fil, post, composer, sondage
│  ├─ community/ messages, chat, groupes, événements, assistant
│  └─ profile/   profil, complétion, paramètres, sécurité, newsletter
│     └─ <feature>/{data, domain, presentation}
└─ main.dart
```
Riverpod pour l'état (un provider par écran, voir le panneau du simulateur), go_router avec redirection selon `onboardingProvider.seen` et la session.

## 9. Sécurité et confidentialité (architecture)

- Champs chiffrés : `alerts.description`, `alerts.lat/lng`, `cases.notes`, `case_followups.comment`, `profiles.orientation`, `profiles.gender_identity`.
- Séparation des droits : un pair ne voit que ses dossiers et les alertes routées vers lui.
- Journal d'audit en écriture seule (aucune route de modification).
- Sauvegardes chiffrées hors serveur ; restauration précédée d'une sauvegarde automatique (RG-13).

## 10. Décisions d'architecture (ADR)

| ADR | Décision |
|---|---|
| ADR-03 | Bootstrap seul, pas de Tailwind (D2) |
| ADR-16 | Espace connecté membre et pair en Vue 3 + Inertia.js, pages publiques en Blade, administration en Livewire (D9) |
| ADR-11 | Pilotes (file, cache, session, diffusion) par configuration : passage à Redis + workers + Reverb sans changer le code applicatif |
| ADR-12 | OTP par e-mail plutôt que SMS (coût, couverture) |
| ADR-13 | Templates e-mails stockés en base, éditables par l'administration, slug stable référencé par le code |
| ADR-15 | Passerelles réseaux sociaux (D4-bis) : un connecteur par réseau derrière une interface `SocialPublisher` (Facebook Graph API, Telegram Bot API ; Roméo, GanXo et Tinder sans API : publication manuelle assistée), jetons chiffrés, comptes de l'organisation uniquement |
| ADR-14 | Newsletter interne (pas de service tiers), envoi par lots via cron, suivi signé HMAC |
