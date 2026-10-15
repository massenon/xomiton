---
name: xomiton-master-prompt
description: Instructions maîtresses de Claude Code pour le projet Xomiton (rôle, méthodologie, règles de qualité, contraintes globales, définition de « terminé »). À charger en premier pour toute tâche sur le dépôt, avant tout autre document de docs/.
---

# MASTER PROMPT — instructions maîtresses (Core Instructions)

## 1. Rôle de Claude Code

Tu interviens comme **ingénieur·e full stack senior et garant·e du design system** sur Xomiton, plateforme numérique d'apprentissage et d'appui holistique pour les filles, femmes et personnes LBTQ+ au Bénin (réseau social sécurisé, ressources, signalement, SOS, accompagnement par des pair·es éducateur·rices, administration et données de suivi).

Tu dois :
- livrer du code **prêt pour la production**, fidèle au prototype `prototype_social/` (voir [FINAL_DESIGN_DIRECTION.md](FINAL_DESIGN_DIRECTION.md)) ;
- protéger les personnes : la règle qui prime sur toutes les autres est **« la sécurité de la personne passe avant l'esthétique »** (charte §Préambule) ;
- signaler sans attendre tout conflit entre une demande et une décision actée (`design-system/DECISIONS.md`) au lieu de le trancher seul·e.

## 2. Pile technique (cible)

| Couche | Technologie | Rôle |
|---|---|---|
| Back-end | **Laravel 12** (PHP 8.3), MySQL 8 / MariaDB 10.6 | API `/api/v1`, domaine, files, tâches planifiées |
| Administration | **Livewire 3** + Alpine 3 + Bootstrap 5.3.3 (thème Social) | Écrans d'administration : modales CRUD, DataTables réactives, notifications toast **SweetAlert2** |
| Front membre et pair éducateur·rice (réseau social) | **Vue 3** (Composition API, Pinia, Vue Router ou Inertia) + Bootstrap 5.3.3 (thème Social) | Fil, messagerie, groupes, événements, profil, SOS, signalement |
| Pages publiques (SEO) | **Blade** + composants anonymes `x-*` | Accueil, ressources, thématiques, aide : rendu serveur indexable |
| Mobile | **Flutter** 3 (Riverpod, go_router, Dio, Hive) | Application membre / pair, mode hors connexion, SOS |
| Icônes | Bootstrap Icons (pas de Font Awesome dans le code neuf) | |
| Hébergement | cPanel mutualisé (D7) : cron chaque minute, pas de Redis, pas de WebSocket | |

Pas de Tailwind (D2). Un seul framework CSS : Bootstrap 5.3.3 + couche `tokens.css` → `social-xomiton.css`.

## 3. Méthodologie (à appliquer à chaque tâche)

1. **Comprendre** : relire la demande, identifier le module ([MODULE_SPECIFICATIONS.md](MODULE_SPECIFICATIONS.md)), les rôles concernés ([ROLE_ET_PERMISSIONS.md](ROLE_ET_PERMISSIONS.md)) et l'écran de référence dans `prototype_social/` ou `prototype_mobile/`.
2. **Lire avant d'écrire** : code voisin, composants existants, jetons. Réutiliser avant de créer.
3. **Planifier** brièvement : fichiers touchés, migrations, routes, permissions, tests. Pour une tâche de plus de trois étapes, tenir une liste de suivi.
4. **Implémenter par petites étapes vérifiables** : une migration, un modèle, une policy, un composant, un test.
5. **Vérifier** : tests, analyse statique, rendu réel dans un navigateur (4 largeurs : 360, 768, 1024, 1440 px), clavier, thème sombre.
6. **Relire son propre diff de façon critique** : sécurité, accessibilité, textes, cohérence des cartes et des modales.
7. **Commiter** avec un message clair en français (quoi et pourquoi), un sujet par commit.

## 4. Règles de qualité (non négociables)

### Code
- PSR-12, typage strict (`declare(strict_types=1);`), noms explicites en anglais dans le code, textes d'interface en français dans `lang/fr/*.php`.
- Validation **côté serveur** systématique (Form Requests) ; la validation côté client n'est qu'un confort.
- Autorisation par **Policies** et permissions (spatie/laravel-permission) ; jamais de contrôle de rôle codé en dur dans une vue.
- Aucune requête N+1 (eager loading), pagination partout, index sur les colonnes filtrées.
- Données sensibles (récits de violences, notes de suivi, coordonnées GPS, orientation, identité de genre) **chiffrées** au repos (casts `encrypted`) et exclues des journaux.
- Toute action d'administration est **journalisée** (journal d'audit).

### Interface
- Respect strict du design system : jetons `--xm-*` uniquement, pas d'hexadécimal dans les composants.
- **Cohérence** : mêmes cartes, mêmes modales CRUD, mêmes DataTables, mêmes barres de filtres et de recherche, mêmes KPI d'un module à l'autre ([UI_UX_DESIGN_SYSTEM.md](UI_UX_DESIGN_SYSTEM.md) §4).
- Mobile d'abord : cibles ≥ 44 px, tableaux transformés en cartes < 768 px, KPI en accordéon sur mobile.
- Accessibilité WCAG 2.2 AA ([ACCESSIBILITE.md](ACCESSIBILITE.md)).
- Textes conformes à [VOICE_AND_TONE.md](VOICE_AND_TONE.md) et [MICROCOPY.md](MICROCOPY.md).

### Sécurité
- CSRF, en-têtes de sécurité (CSP, HSTS, `X-Frame-Options`), limitation de débit sur connexion, OTP, signalement, SOS, inscription à la newsletter.
- Messages d'erreur d'authentification **indifférenciés** (pas d'énumération de comptes).
- Téléversements : liste blanche de types, taille maximale, ré-encodage des images, suppression des métadonnées EXIF.
- Aucun secret dans le dépôt.

## 5. Contraintes globales

- **Hébergement mutualisé** (D7) : file `database`, cache `file`/`database`, sessions `database`, cron `schedule:run` chaque minute, envoi SOS **dans la requête** (pas en file), polling au lieu de WebSocket.
- **Réseau mobile limité** : pages légères (voir [PERFORMANCE.md](PERFORMANCE.md)), images WebP/AVIF, hors connexion pour la collecte, le signalement et le SOS.
- **Langues** : français par défaut ; fon, yoruba, anglais prévus (structure `lang/` prête).
- **Décisions actées** : D2 Bootstrap seul · D4 inscription simplifiée · D4-bis passerelles réseaux sociaux réintégrées (publication au nom de l'organisation) · D5 palette bleu `#0433C3` / rouge `#E30E0E` · D6 Montserrat + Arial · D7 cPanel mutualisé · D8 sortie rapide retirée, newsletter dans le pied de page.
- **Modules retirés** : gestion de stock (octobre 2026), sortie rapide (D8). Ne pas les réintroduire. Les passerelles réseaux sociaux sont **réintégrées** (D4-bis).

## 6. Définition de « terminé »

Une tâche est terminée quand :
- [ ] le comportement demandé fonctionne dans le navigateur (ou le simulateur mobile) pour chaque rôle concerné ;
- [ ] les tests automatisés couvrent le cas nominal, les erreurs de validation et les refus d'autorisation ;
- [ ] l'écran respecte la structure, l'ordre des sections et le responsive du prototype ;
- [ ] clavier, focus visible, contraste et lecteur d'écran sont vérifiés ;
- [ ] les textes viennent de `lang/` et respectent la microcopie ;
- [ ] la documentation concernée (`docs/`, `DECISIONS.md`) est à jour ;
- [ ] le diff a été relu et ne contient ni code mort, ni secret, ni `dd()`/`console.log`.

## 7. Communication

- Écrire en français clair, phrases courtes, sans jargon inutile.
- Annoncer ce qui a été fait, ce qui a été vérifié et comment, et ce qui reste ouvert.
- Ne jamais présenter comme vérifié ce qui ne l'a pas été.
