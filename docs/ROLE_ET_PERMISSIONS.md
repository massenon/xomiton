---
name: xomiton-roles-permissions
description: Rôles et permissions de Xomiton — définition des rôles, matrice module × rôle (§3), permissions spatie/laravel-permission, règles d'accès aux données sensibles et cas particuliers (anonyme, pair, annuaire). À charger avant toute route, policy, menu, bouton conditionnel ou requête qui filtre des données par utilisateur·rice.
---

# Rôles et permissions

Implémentation : **spatie/laravel-permission** (rôles + permissions), Policies Laravel par modèle, middleware `can:` sur les routes, `@can` / `v-if="can('…')"` dans les vues. Aucun test de rôle codé en dur.

## 1. Rôles

| Rôle | Code | Qui | Création |
|---|---|---|---|
| Visiteur·se | (non connecté) | Toute personne | — |
| Membre | `membre` | Filles, femmes, personnes LBTQ+, allié·es | Inscription libre (si ouverte) : **la seule chose que crée l'inscription publique** |
| Pair éducateur·rice | `pair` | Animateur·rices formé·es | **Jamais auto-attribué.** Donné à un·e membre existant·e par l'administration (`peers.assign`), **sur demande du membre** (`POST me/role-requests`, décision de l'admin, D-MOB-15) **ou** par invitation à l'équipe (lien / code signé à usage unique, `peers.invite`) puis validation du compte (RG-03) |
| Modérateur·rice | `moderateur` | Équipe de modération | Attribué par superadmin (`roles.assign_admin`, RG-15), **depuis le site web uniquement** (D-MOB-16) |
| Administrateur·rice | `admin` | Équipe de coordination | Attribué ou invité par superadmin, **depuis le site web uniquement** (D-MOB-16, tranché 2026-10-29) : l'API mobile répond 403 |
| Super-administrateur·rice | `superadmin` | Responsable technique / direction | Attribué ou invité par superadmin |

### 1 bis. Un seul compte, un seul login, rôles cumulables (décisions client du 2026-10-29)

Membre, pair éducateur·rice et admin se connectent par **les mêmes écrans et endpoints** (`/connexion`, `auth/login`, code OTP compris). Les rôles **se cumulent** sur un même compte :

- **`membre` est toujours conservé** : un·e pair·e et un·e admin gardent le réseau social (fil, messages, groupes, événements, forums). Le niveau d'administration (`moderateur`, `admin`, `superadmin`) s'ajoute ; `pair` se conserve quand on devient admin (ses dossiers lui restent) et ne tombe que si l'on repasse à « membre » ou que l'on retire le rôle pair.
- **Espaces** (`AccountSpaces`, props Inertia `auth.user.spaces` et `GET me` → `spaces`) : `member` (tout compte), `peer` (rôle `pair` + compte actif, RG-03), `admin` (web : `admin.access` ; mobile : `admin_mobile.access`). C'est le sélecteur d'espace du mobile et les menus du web ; **un compte multi-rôle arrive toujours sur le fil** (`/fil`), l'espace pair (`/espace-pair`) et l'administration (`/admin`) s'ouvrent par les menus. Ergonomie seulement : chaque route revérifie permission, policy et scope.
- **Aucun rôle n'est auto-attribuable** : `member_type = pair` n'est plus un choix du profil (inscription, `PATCH me/profile` : 422). Un membre peut seulement **demander** le rôle pair (`POST me/role-requests`, D-MOB-15, **Tranché 2026-10-29 (recommandation retenue)** : motif court facultatif, `Idempotency-Key`, limiteur, une demande en cours à la fois) ; la décision (approuver, ou refuser avec motif) revient à un compte détenant `peers.assign`, jamais à lui-même, avec `step-up` sur le mobile. La seule voie sans administrateur est une **invitation** créée par l'administration, qui mène à l'inscription ordinaire (case 15 ans comprise) ; le rôle suit à la validation du compte (e-mail confirmé, ou approbation si la validation manuelle est active) et seulement si la personne qui a invité détient encore le droit.
- **Gestion par l'administration** (écran Utilisateurs & rôles et Inscriptions, **et** API `admin/*`, API.md §23) : donner / retirer le rôle pair à un membre (`peers.assign`), inviter un·e pair·e (`peers.invite`) ou un·e admin (`roles.assign_admin`, super-admin), valider ou refuser (avec **motif**) les inscriptions et demandes de pair en attente (`users.validate_peers`). Chaque changement est **journalisé** (`roles.peer_granted`, `roles.peer_revoked`, `roles.admin_granted`, `users.role_changed`, `users.peer_refused`, `invitations.*`) et **notifié** à l'intéressé·e (`RoleNotice` : cloche + push).
- **Effacement à distance** (P15-11, `device.wipe`, admin et superadmin) : l'administration (écran *Hors connexion & synchro*, API `admin/devices/{id}/wipe` avec `step-up`) marque un appareil à effacer ; le **retrait du rôle pair** marque d'office tous les appareils actifs de la personne ; la personne peut aussi effacer elle-même un de ses autres appareils (`POST me/devices/{uuid}/wipe`, mot de passe ressaisi, sans permission particulière : c'est le sien). L'appareil est révoqué dans le même geste (403 `device_revoked` partout, SOS et signalement jamais bloqués), lit l'ordre dans `GET me` / `heartbeat`, vide ses données et accuse (`POST me/devices/{uuid}/wiped`). Journal `device.wipe_requested`, `device.wiped`.
- **Interrupteurs d'espace et jeton** (P15-22, D-MOB-9) : le super-admin ou l'exploitation peut couper l'espace Pair ou Admin de l'application (`php artisan xomiton:mobile-space`, API.md §24 bis) sans toucher au site web ; le jeton de l'application expire après 7 jours d'inactivité pour un compte qui a l'espace Admin, 30 jours pour un compte pair.
- **Rôles d'administration : web seulement** (D-MOB-16, **Tranché 2026-10-29 (recommandation retenue)**) : `moderateur`, `admin` et `superadmin` ne s'attribuent, ne se retirent et ne s'invitent que depuis le site web par un·e super-admin ; l'API `admin/*` répond 403 même au super-admin.
- **Destinataires des alertes** (D-MOB-10, **Tranché 2026-10-29 (recommandation retenue)**) : à la création, admins **de garde** + pair·es **de la zone** de l'alerte ; à 5 minutes sans accusé de réception, **escalade nationale** à tous les admins et tous les pair·es (+ point focal national) ; l'accusé de l'un·e arrête l'escalade. Position du SOS pour l'admin : toujours **arrondie** (D-MOB-4).
- **Inscriptions** (D-MOB-17, **Tranché 2026-10-29 (recommandation retenue)**) : seules les inscriptions **signalées** (e-mail jetable, inscriptions répétées) attendent la validation `users.validate_peers` ; le réglage `reg_manual_validation = all` valide tout le monde.
- **Non-escalade** (tests `tests/Feature/Admin/TeamRolesTest.php`, `tests/Feature/Api/Admin/AdminMobileApiTest.php`, `tests/Feature/Api/Me/RoleRequestsApiTest.php`) : un·e pair·e ne peut pas se donner admin, un membre ne peut pas appeler l'API admin (403 sur chaque route), un·e admin ne peut ni se donner ni donner un rôle d'administration (RG-15).
| Intervenant·e de l'annuaire | (contact, pas un compte obligatoire) | Points focaux, juristes, psychologues… | Fiche annuaire ; peut avoir un compte `pair` ou `admin` |

## 2. Principes

1. **Moindre privilège** : chaque rôle n'a que ce dont il a besoin.
2. **Cloisonnement des données sensibles** : un pair ne voit que ses dossiers et les alertes routées vers lui ; seuls admin et superadmin voient tous les dossiers.
3. **Actions anonymes** : signalement, SOS, contacts d'urgence, lecture des ressources et inscription à la newsletter ne demandent pas de compte (RG-01).
4. **Traçabilité** : toute action d'administration est journalisée.
5. Les permissions sont nommées `module.action` : `alerts.view`, `alerts.take`, `cases.create`, `newsletter.send`, `email_templates.delete_system`…

## 3. Matrice des permissions

Légende : ✅ accès complet · 👁 lecture / accès limité · ❌ aucun accès.

| Module | Visiteur·se | Membre | Pair | Modérateur·rice | Admin | Superadmin |
|---|---|---|---|---|---|---|
| Pages publiques, ressources, thématiques | 👁 | 👁 | 👁 | 👁 | ✅ | ✅ |
| Signalement anonyme, SOS, contacts d'urgence | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Inscription à la newsletter (pied de page) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Réseau social (fil, sondages, chat, groupes, événements, forums) | ❌ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Ressources & outils CCC (lecture) | 👁 | ✅ | ✅ | ✅ | ✅ | ✅ |
| Espace pair (tâches, outils d'animation) | ❌ | ❌ | ✅ | ❌ | ✅ | ✅ |
| Administration mobile (API `admin/*`, espace Admin de l'application) | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ |
| Collecte de données & registres | ❌ | ❌ | ✅ (ses dossiers) | ❌ | ✅ | ✅ |
| Fiches VBG / VDH | ❌ | ❌ | 👁 (ses dossiers) | ❌ | ✅ | ✅ |
<!-- API : la fiche de référence en lecture (`sheet` de GET pair/cases/{code}, lieu précis compris) suit cette ligne : `case_forms.view_own` sur ses dossiers, jamais l'auteur·e des faits ni le signataire ; `pair/collect-options` et `meta.on_duty` de `pair/alerts` : accès `peer_space.access`, sans donnée de dossier. -->
| Suivi des victimes | ❌ | ❌ | 👁 (ses dossiers) | ❌ | ✅ | ✅ |
| Alertes & signalements (administration) | ❌ | ❌ | 👁 (routées vers lui·elle **ou dont l'équipe l'a prévenu·e**) | ❌ | ✅ | ✅ |
| Annuaire dynamique | ❌ | 👁 (contacts publics) | 👁 | 👁 | ✅ | ✅ |
| Règles de validation | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ |
| Indicateurs & rapports automatisés | ❌ | ❌ | 👁 (sa zone) | ❌ | ✅ | ✅ |
| Modération & contenu | ❌ | ❌ | ❌ | ✅ | ✅ | ✅ |
| Contenu : thématiques & ressources (édition) | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ |
| Outils CCC (édition) | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ |
| Assistant LLM (base de connaissances) | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ |
| **Newsletter** (abonné·es, campagnes, envoi) | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ |
| **Passerelles réseaux sociaux** (connexion des comptes, file de publication) | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ |
| **Templates e-mails** | ❌ | ❌ | ❌ | ❌ | ✅ (sauf suppression système) | ✅ |
| Utilisateurs (membres, pair·es) | ❌ | ❌ | ❌ | 👁 (suspendre) | ✅ | ✅ |
| Inscriptions (ouvrir / fermer) | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ |
| Rôle pair : donner / retirer, inviter | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ |
| Rôles d'administration (attribuer, inviter) | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ |
| Hors connexion & synchro (conflits, effacement à distance des appareils) | ❌ | ❌ | 👁 (ses appareils) ; **tranche lui·elle-même** ses conflits sur ses dossiers (serveur ou appareil) | ❌ | ✅ | ✅ |
| Mises à jour, maintenance, **sauvegardes** | ❌ | ❌ | ❌ | ❌ | 👁 (créer, aperçu, télécharger) | ✅ (restaurer, supprimer) |
| Paramètres système, SMTP, purge du journal | ❌ | ❌ | ❌ | ❌ | 👁 | ✅ |
| Journal d'audit | ❌ | ❌ | ❌ | ❌ | 👁 | ✅ |

## 4. Permissions détaillées (extrait)

| Permission | Membre | Pair | Mod. | Admin | Super |
|---|---|---|---|---|---|
| `posts.create`, `polls.create`, `comments.create` | ✅ | ✅ | ✅ | ✅ | ✅ |
| `posts.moderate` | | | ✅ | ✅ | ✅ |
| `cases.create`, `cases.update_own` | | ✅ | | ✅ | ✅ |
| `cases.view_all`, `cases.export` | | | | ✅ | ✅ |
| `alerts.take`, `alerts.close` | | routées ou notifiées | | ✅ | ✅ |
| `peers.assign` (donner / retirer le rôle pair) | | | | ✅ | ✅ |
| `peers.invite` (inviter un·e pair·e) | | | | ✅ | ✅ |
| `admin_mobile.access` (API `admin/*`, retirable par le super-admin) | | | | ✅ | ✅ |
| `sync.resolve_own` (trancher ses propres conflits 409) | | ✅ | | ✅ | ✅ |
| `device.wipe` (effacement à distance d'un appareil de l'application, P15-11) | | | | ✅ | ✅ |
| `directory.manage` | | | | ✅ | ✅ |
| `newsletter.manage_subscribers`, `newsletter.send` | | | | ✅ | ✅ |
| `gateways.connect`, `gateways.publish` | | | | ✅ | ✅ |
| `email_templates.manage` | | | | ✅ | ✅ |
| `email_templates.delete_system` | | | | | ✅ |
| `platform.maintenance` (écran `/admin/maintenance`, P13-06 ; remplace `maintenance.manage`, Q85) | | | | ✅ | ✅ |
| `backups.create`, `backups.download` | | | | ✅ | ✅ |
| `backups.restore`, `backups.delete` | | | | | ✅ |
| `roles.assign_admin` (attribuer ou inviter modération / admin / super-admin) | | | | | ✅ |

## 5. Règles d'accès aux données

- Requêtes « pair » toujours portées par un scope : `Case::visibleTo($user)` → `where('collected_by', $user->id)` ; `Alert::routedTo($user)` (`AlertScope`) → contact d'annuaire lié au compte **ou alerte dont l'équipe a notifié le compte** (SOS, alerte routée, rappel, escalade : les admins **et les pair·es** sont destinataires, décision client du 2026-10-29 ; une alerte jamais notifiée à un·e pair·e reste fermée).
- Conflit de synchronisation : le pair ne tranche (serveur ou appareil) que les conflits **de ses appareils** sur **ses dossiers** (`SyncConflictPolicy`) ; la fusion à la main est réservée à `sync.manage`.
- Position d'une alerte : **arrondie** (≈ 1 km) pour l'admin mobile ; la position exacte reste réservée au contact routé / pair notifié et à l'écran web d'administration.
- Les exports (CSV, PDF) appliquent le même scope que l'écran.
- Les votes de sondage, les adresses IP de consentement et les jetons de newsletter ne sont **jamais** exposés hors de l'administration.
- La maintenance (RG-14) bloque la connexion de tous les rôles sauf admin et superadmin ; SOS, signalement, urgences et leur API restent servis (RG-01, Q28), ainsi que les référentiels du signalement (zones, types de violence, besoins) et « mot de passe oublié » / réinitialisation (anti-verrouillage : la réinitialisation ne connecte personne). Seuls ces deux rôles détiennent `platform.maintenance` (migration de données depuis `maintenance.manage`, Q85) : personne ne peut activer une maintenance qui l'exclurait (garde-fou anti-verrouillage, P13-06) ; une maintenance déjà active n'est jamais réactivée.

## 6. Tests obligatoires

Pour chaque route protégée : un test « autorisé » et un test « refusé (403) » par rôle concerné ; pour chaque scope de données : un test prouvant qu'un pair ne voit pas le dossier d'un autre pair.
