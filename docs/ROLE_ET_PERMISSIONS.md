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
| Membre | `membre` | Filles, femmes, personnes LBTQ+, allié·es | Inscription libre (si ouverte) |
| Pair éducateur·rice | `pair` | Animateur·rices formé·es | Inscription puis **validation** par l'administration (RG-03) |
| Modérateur·rice | `moderateur` | Équipe de modération | Attribué par admin *(rôle proposé, à valider)* |
| Administrateur·rice | `admin` | Équipe de coordination | Attribué par superadmin |
| Super-administrateur·rice | `superadmin` | Responsable technique / direction | Attribué par superadmin |
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
| Collecte de données & registres | ❌ | ❌ | ✅ (ses dossiers) | ❌ | ✅ | ✅ |
| Fiches VBG / VDH | ❌ | ❌ | 👁 (ses dossiers) | ❌ | ✅ | ✅ |
| Suivi des victimes | ❌ | ❌ | 👁 (ses dossiers) | ❌ | ✅ | ✅ |
| Alertes & signalements (administration) | ❌ | ❌ | 👁 (routées vers lui·elle) | ❌ | ✅ | ✅ |
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
| Rôles d'administration | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ |
| Hors connexion & synchro (conflits) | ❌ | ❌ | 👁 (ses appareils) | ❌ | ✅ | ✅ |
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
| `alerts.take`, `alerts.close` | | routées | | ✅ | ✅ |
| `directory.manage` | | | | ✅ | ✅ |
| `newsletter.manage_subscribers`, `newsletter.send` | | | | ✅ | ✅ |
| `gateways.connect`, `gateways.publish` | | | | ✅ | ✅ |
| `email_templates.manage` | | | | ✅ | ✅ |
| `email_templates.delete_system` | | | | | ✅ |
| `platform.maintenance` (écran `/admin/maintenance`, P13-06 ; remplace `maintenance.manage`, Q85) | | | | ✅ | ✅ |
| `backups.create`, `backups.download` | | | | ✅ | ✅ |
| `backups.restore`, `backups.delete` | | | | | ✅ |
| `roles.assign_admin` | | | | | ✅ |

## 5. Règles d'accès aux données

- Requêtes « pair » toujours portées par un scope : `Case::visibleTo($user)` → `where('collected_by', $user->id)` ; `Alert::routedTo($user)` → contact d'annuaire lié au compte.
- Les exports (CSV, PDF) appliquent le même scope que l'écran.
- Les votes de sondage, les adresses IP de consentement et les jetons de newsletter ne sont **jamais** exposés hors de l'administration.
- La maintenance (RG-14) bloque la connexion de tous les rôles sauf admin et superadmin ; SOS, signalement, urgences et leur API restent servis (RG-01, Q28), ainsi que les référentiels du signalement (zones, types de violence, besoins) et « mot de passe oublié » / réinitialisation (anti-verrouillage : la réinitialisation ne connecte personne). Seuls ces deux rôles détiennent `platform.maintenance` (migration de données depuis `maintenance.manage`, Q85) : personne ne peut activer une maintenance qui l'exclurait (garde-fou anti-verrouillage, P13-06) ; une maintenance déjà active n'est jamais réactivée.

## 6. Tests obligatoires

Pour chaque route protégée : un test « autorisé » et un test « refusé (403) » par rôle concerné ; pour chaque scope de données : un test prouvant qu'un pair ne voit pas le dossier d'un autre pair.
