# Xomiton — dossier `docs/` (skills de référence pour Claude Code)

Ce dossier rassemble les **instructions de référence** du projet Xomiton, rédigées comme des skills Claude Code : chaque fichier commence par un en-tête `name` / `description` qui dit **quand** le charger, puis donne des règles vérifiables. Le skill d'entrée `.claude/skills/xomiton/SKILL.md` indique à Claude Code quel document lire selon la tâche.

Méthode d'écriture : gabarit *PRD skill* (problème, objectifs, utilisateur·rices, exigences numérotées, critères d'acceptation, hors périmètre, mesures de succès), appliqué aux documents produit ; les documents techniques et éditoriaux suivent le même principe : **une règle = une exigence testable**.

## Ordre de lecture

1. [`MASTER_PROMPT.md`](MASTER_PROMPT.md) : rôle de Claude Code, méthodologie, règles de qualité, contraintes globales. **Toujours en premier.**
2. [`FINAL_DESIGN_DIRECTION.md`](FINAL_DESIGN_DIRECTION.md) : fidélité à 100 % au prototype `prototype_social`.
3. Le document de la tâche (tableau ci-dessous).

## Index

| Domaine | Fichier | Contenu |
|---|---|---|
| Cadre | [MASTER_PROMPT.md](MASTER_PROMPT.md) | Rôle, méthode, qualité, contraintes globales, définition de « terminé » |
| Cadre | [FINAL_DESIGN_DIRECTION.md](FINAL_DESIGN_DIRECTION.md) | Ce qui doit rester identique au prototype |
| Cadre | [ETAT_DES_LIEUX.md](ETAT_DES_LIEUX.md) | **État des lieux** : statut par phase, livré et vérifié, reste à faire (code, humain, serveur, comptes), chiffres, risques |
| Cadre | [PLAN_MOBILE_APP_UNIQUE.md](PLAN_MOBILE_APP_UNIQUE.md) | **Application mobile unique** (membre + pair + admin d'astreinte) : exigences, architecture, rôles, sécurité, arbitrages, lots P15-10 à P15-23, recette, risques |
| Cadre | [DECISIONS_A_VALIDER.md](DECISIONS_A_VALIDER.md) | **Décisions à valider** : questions Q ouvertes du plan, décision par défaut, qui tranche, urgence |
| Cadre | [IMPLEMENTATION_PLAN.md](IMPLEMENTATION_PLAN.md) | **Plan d'implémentation** : phases P0 à P17, tâches, dépendances, décisions techniques, suivi d'avancement |
| Produit | [BUSINESS_ANALYSIS_PRODUCT_SPECIFICATION.md](BUSINESS_ANALYSIS_PRODUCT_SPECIFICATION.md) | BRS, PRD, SRS, FRS, NFRS, règles métier |
| Produit | [MODULE_SPECIFICATIONS.md](MODULE_SPECIFICATIONS.md) | Chaque module, écran par écran, onglet par onglet |
| Produit | [ROLE_ET_PERMISSIONS.md](ROLE_ET_PERMISSIONS.md) | Rôles, matrice des permissions (§3), règles d'accès |
| Technique | [SOFTWARE_ARCHITECTURE.md](SOFTWARE_ARCHITECTURE.md) | Clean Architecture, Laravel, Livewire, Vue, Flutter, API, moteur de synchronisation |
| Technique | [DATABASE_ENGINEERING.md](DATABASE_ENGINEERING.md) | Modèle de données, ERD, dictionnaire, migrations, contraintes |
| Technique | [DATABASE_DICTIONARY.md](DATABASE_DICTIONARY.md) | Dictionnaire complet colonne par colonne, enums PHP, résolution des écarts avec le prototype, lots de migrations |
| Technique | [DEVELOPMENT_STANDARDS.md](DEVELOPMENT_STANDARDS.md) | Conventions Laravel, Livewire, Vue, JS, validation, sécurité, tests |
| Technique | [NOTIFICATIONS_INSTRUCTION.md](NOTIFICATIONS_INSTRUCTION.md) | Canaux, événements, toasts SweetAlert2, e-mails, push |
| Technique | [LOGIN_INSCRPTION_MULTISTEP_USER_INSTRUCTION.md](LOGIN_INSCRPTION_MULTISTEP_USER_INSTRUCTION.md) | Connexion, inscription en 4 phases, OTP e-mail, mot de passe oublié |
| Design | [UI_UX_DESIGN_SYSTEM.md](UI_UX_DESIGN_SYSTEM.md) | Charte, navigation, composants, dashboards, CRUD, DataTables, modales, formulaires (§4) |
| Design | [UX_GUIDELINES.md](UX_GUIDELINES.md) | Principes d'expérience, parcours, états, ergonomie mobile |
| Front | [FRONT_END_LAYOUT_INSTRUCTION.md](FRONT_END_LAYOUT_INSTRUCTION.md) | Gabarits public, membre, pair, admin |
| Front | [FRONT_END_PAGE_STRUCTRE_INSTRUCTION.md](FRONT_END_PAGE_STRUCTRE_INSTRUCTION.md) | Structure HTML d'une page |
| Front | [FRONT_END_SECTION_ORDER_INSTRUCTION.md](FRONT_END_SECTION_ORDER_INSTRUCTION.md) | Ordre des sections par page |
| Front | [FRONT_END_BLADE_STRUCTURE_INSTRUCTION.md](FRONT_END_BLADE_STRUCTURE_INSTRUCTION.md) | Arborescence Blade, composants `x-*`, Livewire, îlots Vue |
| Front | [FRONT_END_RESPONSIVE_BEHAVIOR_INSTRUCTION.md](FRONT_END_RESPONSIVE_BEHAVIOR_INSTRUCTION.md) | Comportements par point de rupture |
| Front | [FRONT_END_RESPONSIVE.md](FRONT_END_RESPONSIVE.md) | Desktop, laptop, tablette, mobile : ce qui doit rester identique |
| Front | [FRONT_END_COPYWRITING_INSTRUCTION.md](FRONT_END_COPYWRITING_INSTRUCTION.md) | Règles d'écriture des interfaces |
| Contenu | [BRAND_GUIDE_INSTRUCTION.md](BRAND_GUIDE_INSTRUCTION.md) | Identité, vision, mission, valeurs, personnalité, messages clés |
| Contenu | [VOICE_AND_TONE.md](VOICE_AND_TONE.md) | Voix, ton par contexte, tutoiement / vouvoiement, écriture inclusive |
| Contenu | [MICROCOPY.md](MICROCOPY.md) | Menus, boutons, formulaires, notifications, erreurs, états vides |
| Contenu | [CTA_LIBRARY.md](CTA_LIBRARY.md) | Bibliothèque d'appels à l'action |
| Contenu | [COPYWRITING_FRAMEWORK.md](COPYWRITING_FRAMEWORK.md) | Méthodes de rédaction (pages, e-mails, newsletter) |
| Contenu | [CONTENT_STRATEGY.md](CONTENT_STRATEGY.md) | Objectifs par page, parcours, tunnel, mots-clés |
| Contenu | [HOME_PAGE_COPY.md](HOME_PAGE_COPY.md) | Textes de la page d'accueil, section par section |
| SEO | [SEO_CONTENT_GUIDE_INSTRUCTION.md](SEO_CONTENT_GUIDE_INSTRUCTION.md) | Titres, métadonnées, mots-clés, maillage, `alt` |
| SEO | [FRONT_END_SEO_PERFECT_AUDIT.md](FRONT_END_SEO_PERFECT_AUDIT.md) | Grille d'audit par page (meta, Hn, FAQ, Schema.org, OG…) |
| Qualité | [ACCESSIBILITE.md](ACCESSIBILITE.md) | WCAG 2.2 AA, ARIA, clavier, focus, contraste |
| Qualité | [PERFORMANCE.md](PERFORMANCE.md) | Images, CSS, JS, chargement différé, polices, budgets |
| Mise en œuvre | [INSTALLATION_LOCALE.md](INSTALLATION_LOCALE.md) | **Installation locale complète** (Windows PowerShell, mention macOS et Linux) : prérequis, clonage de la branche, site, base SQLite ou MySQL, comptes de démonstration, code OTP, application mobile, tests, dépannage |
| Mise en œuvre | [GUIDE_DEPLOIEMENT.md](GUIDE_DEPLOIEMENT.md) | **Mise en production sur cPanel** : arborescence, `.env` de production, premier déploiement, cron, sauvegardes, supervision, pages légales, mise à jour et retour arrière, sécurité avant ouverture, Google Play |
| Mise en œuvre | [../web/.env.production.example](../web/.env.production.example) | **Fichier d'environnement de production** complet et commenté en français (`APP_ENV=production`, MySQL, SMTP, SMS, FCM, Gemini désactivé, `XOMITON_LEGAL_*`, heartbeat, sauvegardes), avec la procédure de mise en service en tête ; à copier en `web/.env` sur le serveur (voir [GUIDE_DEPLOIEMENT.md](GUIDE_DEPLOIEMENT.md) §6) |
| Mise en œuvre | [PLAN_FINALISATION.md](PLAN_FINALISATION.md) | **Plan de finalisation** : état réel (terminé et vérifié, codé mais jamais exécuté), reste à faire par priorité avec propriétaire et critère de sortie, échéancier par jalons |
| Qualité | [GUIDE_RECETTE.md](GUIDE_RECETTE.md) | **Guide de recette** : installer et lancer le web et le mobile en local (scripts `scripts/recette-local.sh` et `recette-lancer.sh`), comptes de démonstration, journaux, canaux réels, gabarit de bug |
| Qualité | [CHECKLIST_RECETTE.md](CHECKLIST_RECETTE.md) | **Checklist de recette humaine** : tableau cochable par parcours (visiteur·se, membre, pair, modération, admin, SOS, e-mails, notifications, mobile, accessibilité, performance, sécurité) et liste de ce qui n'est pas encore fonctionnel |
| Publication | [playstore/PUBLICATION_PLAY_STORE.md](playstore/PUBLICATION_PLAY_STORE.md) | **Dossier de publication Google Play** : fiche (nom, descriptions), sécurité des données, permissions, politiques à risque, checklist, visuels (`playstore/assets/`, générés par `playstore/build_playstore.py` ; PDF régénérés par `playstore/build_pdf.py`, qui échoue si un caractère corrompu U+FFFD est détecté) et ce que le client doit fournir |
| Publication | [playstore/Fiche-publication-Play-Store-Xomiton.docx](playstore/Fiche-publication-Play-Store-Xomiton.docx) | **Fiche de publication Google Play au format Word** (page de titre, sommaire, tableaux, visuels avec textes alternatifs), générée depuis le dossier Markdown par `playstore/build_fiche_docx.py` ; URL de la politique de confidentialité et de suppression de compte, décisions du client (âge 16-17 et 18+, gratuit, tous pays, français, un seul AAB) |
| Décisions | [FEUILLE_DE_DECISION.md](FEUILLE_DE_DECISION.md) | **Feuille de décision** : les questions qui bloquent la recette, avec une recommandation et une case à cocher |

## Sources de vérité (par ordre de priorité)

1. Décisions du client : `design-system/DECISIONS.md` (D2, D4 révisée par D4-bis, D5 à D9).
2. Design system : `design-system/xomiton/MASTER.md` puis `design-system/xomiton/pages/*.md` (les pages priment sur MASTER), jetons `design-system/tokens/`. Charte graphique : `design-system/Charte-graphique-Xomiton-v1.1.pdf` (v1.1 du 28 octobre 2026 ; `Charte-graphique-Xomiton-v1.0.pdf` en est la copie au nom historique) et sa version texte `design-system/charte-graphique/CHARTE_GRAPHIQUE.md` ; visuels fournis par le client : `design-system/assets/` (règles : §7.1).
3. Prototype de référence visuelle et fonctionnelle : `prototype_social/` (web) et `prototype_mobile/` (mobile).
4. Ces documents `docs/`.

En cas de conflit entre deux sources, **ne pas trancher seul** : signaler le conflit, proposer une option, demander une décision et la consigner dans `design-system/DECISIONS.md`.
