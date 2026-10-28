# Décisions à valider — questions Q ouvertes du plan

Source : `IMPLEMENTATION_PLAN.md` §7 (texte complet de chaque question, options et recommandation). Ce tableau ne recopie pas les textes longs. Le plan ne porte pas de colonne « décision par défaut appliquée » : la décision par défaut est celle de la colonne « Recommandation », marquée « Appliqué (par défaut) » dans le code. Une fois tranchée, une question se consigne dans `design-system/DECISIONS.md` (le plan en est l'index, pas la source).

Qui tranche : **Client** (produit, éditorial, juridique, terrain) ou **Technique** (équipe de développement). Urgence : **Bloquant recette** (empêche de tester ou de faire valider), **Avant production**, **Confort** (peut suivre la mise en production).

Les questions Q1 à Q9, Q11 à Q13, Q15, Q17, Q19 à Q49, Q51, Q52, Q55, Q61 à Q63, Q65, Q69, Q71 sont consignées avec une recommandation appliquée sans marque « à valider » dans le plan ; elles ne figurent pas ici, sauf Q7, Q9, Q37, Q38, Q61 qui dépendent d'un tiers ou du terrain.

## 1. Bloquant recette

| Q | Sujet | Décision par défaut appliquée | Qui | Urgence |
|---|---|---|---|---|
| Q7 | Fournisseurs : **tranchée le 28/10/2026** — push FCM (gratuit), SMS Vonage ou Twilio (`SMS_DRIVER`), assistant Gemini **désactivé** (`ASSISTANT_LLM_ENABLED=false`) | Pilotes codés et testés (HTTP simulé) ; défaut `log` / `kb`. **À fournir par le client** : projet Firebase + JSON du compte de service + `google-services.json` / `GoogleService-Info.plist` ; compte Vonage ou Twilio (clés, expéditeur autorisé au Bénin, devis local à confirmer) ; clé Gemini. **Conditions d'activation du LLM en production : avis juridique préalable** (questions pouvant contenir des données de santé ou de violences envoyées à un tiers hors UE ; accord de traitement (DPA) Google, région de traitement, durée de conservation, information des membres, base légale/consentement), puis recette des garde-fous | Client (comptes, avis juridique), Technique (activation) | Bloquant recette (push, SMS) ; LLM : jamais activé sans l'avis |
| Q38 | Périmètre v1 de l'app mobile, onglets, espace pair (P15-09) | 5 onglets dont SOS ; espace pair après maquettes | Client | Bloquant recette |
| Q53 | Accueil : sections « chiffres », « témoignages », texte membre connecté | Défaut du lot P4-01 | Client | Bloquant recette |
| Q54 | Accueil : bande rouge pleine largeur et promesses — **Tranché 2026-10-28** : bande b) fond rouge très clair (`--xm-alert-surface`) + bordure rouge, texte foncé AA clair et sombre, rouge plein réservé au SOS ; section #alerte (`alerte.blade.php`), captures `docs/captures/accueil/` | b) / a) | Client | Bloquant recette |
| Q16 | Accueil : CTA du hero (« Rejoignez-nous » ou « Créer un compte ») — **Tranché 2026-10-28** : « Créer un compte » (hero de l'accueil) | Ordre de la vitrine, CTA à trancher | Client | Bloquant recette |
| Q18 | Pages publiques et gabarit sobre vues par un·e membre connecté·e | Défaut du lot P4 | Client | Bloquant recette |
| Q14 / Q87 | Barre latérale admin : correspondance des 24 écrans vers 8 groupes | `AdminNavigation::GROUPS` | Client | Bloquant recette |
| Q59 | Contacts d'urgence hors connexion : drapeau `is_emergency` | a) appliqué (migration + admin + import) | Client | Bloquant recette |
| Q58 | 18 questions FAQ semées par `ThematiqueFaqSeeder` — **Tranché 2026-10-28** : FAQ maintenue (déjà validée) | Brouillon en production | Client (éditorial) | Bloquant recette |
| Q64 | Quiz vrai / faux sur `/violences` (contenu santé) — **Tranché 2026-10-28** : quiz maintenu (déjà validé) | Quiz de 5 affirmations écrit pour le lot | Client (santé) | Bloquant recette |
| Q56 | Registre (tu / vous) de l'offcanvas de l'assistant — **Tranché 2026-10-28** : tu dans l'assistant et le SOS, vous sur le site public | Vous (`VOICE_AND_TONE.md` §3) | Client (éditorial) | Bloquant recette |
| Q10 | Types de membre : allié·e / personne ressource | c) membre / pair, `ally_status` facultatif | Client | Bloquant recette |
| Q89 | Lien Google Maps dans le détail d'une alerte (position du SOS) — **Tranché 2026-10-28** : lien Google Maps gardé avec avertissement | Lien explicite au clic, signalé | Client | Bloquant recette |
| Q61 | Délai d'envoi d'un SOS sans GPS (case « Partager ma position ») — **Tranché 2026-10-28** : 8 s au total, puis envoi sans position, sans second délai | Délais du lot `sos` | Client (terrain) | Bloquant recette |

## 2. Avant production

| Q | Sujet | Décision par défaut appliquée | Qui | Urgence |
|---|---|---|---|---|
| Q68 | Âge minimal d'inscription (15 ans du prototype) — **Tranché 2026-10-28** : 15 ans, valeur unique `xomiton.accounts.min_age` | **Appliqué** : case obligatoire « J'ai 15 ans ou plus » (web + API `age_confirmed` + Flutter), aucune date de naissance, horodatage seul (consentement `age_confirmed`) | Client + conseil juridique | Avant production |
| Q67 | Textes légaux (confidentialité) et vote « utile » du centre d'aide | Textes du lot P4-05 / P4-08 | Client + conseil juridique | Avant production |
| Q9 | Stockage externe des sauvegardes | Disque configurable `BACKUP_DISK`, mysqldump, JSON en repli | Client (hébergeur), Technique | Avant production |
| Q57 | Notifications d'alerte : destinataires, SMS, `mail_outbox` en clair — **Tranché 2026-10-28** : purge du corps dès l'envoi ; échecs conservés 7 jours au plus | Appliqué par défaut (références relues à chaque tentative) | Technique | Avant production |
| Q50 | Accusé de réception par lien signé (POST, limiteur dédié, lien dans un SMS) | b) appliqué ; variable `{{acknowledge_link}}` à trancher | Technique | Avant production |
| Q66 | Newsletter : confirmation par bouton (un clic de plus) | POST de confirmation | Client | Avant production |
| Q37 | Code PIN local et mode discret (iOS : icône seulement) | PIN haché local, seul `pin_enabled` synchronisé | Client | Avant production |
| Q85 | Maintenance : permission dédiée, écart à `ROLE_ET_PERMISSIONS.md` §3 | Permission dédiée, admin en lecture | Technique | Avant production |
| Q74 | Messagerie : lecture du message signalé par la modération, filtre de mots | Lecture du seul message signalé ; pas de filtre | Client | Avant production |
| Q94 | Admin système : droits admin / superadmin, résolution des conflits | Admin lit, superadmin écrit ; conflit écrit selon les règles | Technique | Avant production |
| Q91 | Inscriptions : validation manuelle « tous » sans restriction d'accès | `reg_manual_validation` (`peers` par défaut) ; restrictions à définir (Q26) | Client | Avant production |
| Q92 | Registres : archivage logique, dossier créé au nom de l'admin | Appliqué | Client | Avant production |
| Q93 | Contenu : méta SEO 40 à 50 caractères, modérateur et réseau | Appliqué | Technique | Avant production |
| Q88 | Espace pair : « de garde », brouillon de collecte | Plage `focal` en cours ; brouillon 1 jour | Client | Avant production |
| Q86 | Synchro : clés d'idempotence, délai affiché à l'astreinte | uuid du lot rejoué | Technique | Avant production |
| Q90 | Indicateurs : IND-12 = SOS avec position | Appliqué | Client | Avant production |
| Q82 | Annuaire membre : préchargement hors ligne de `/annuaire` | Réservé aux membres | Technique | Avant production |
| Q84 | Recherche membre : périmètre, terme hors de l'adresse en mode discret | Ressources et contacts seulement | Client | Avant production |
| Q83 | Assistant : réponse de crise calculée dans la page | Aucun appel réseau pour la crise | Client | Avant production |
| Q81 | Mot de passe changé : déconnexion des autres appareils | Toujours | Client | Avant production |
| Q80 | Profil : visibilité, champ sensible jamais public sans choix | Appliqué | Client | Avant production |

## 3. Confort

| Q | Sujet | Décision par défaut appliquée | Qui | Urgence |
|---|---|---|---|---|
| Q70 | API `me/*` : thème `system`, newsletter sans fréquence, intérêts séparés | `system` enregistré `auto` ; fréquence absente | Client | Confort |
| Q72 | Fil : un nouveau vote remplace le précédent | Remplacement | Client | Confort |
| Q73 | Lecture mobile : `thematiques` et `ressources` en `conditional` | ETag, `private, no-cache` | Technique | Confort |
| Q75 | Groupes privés : invisibles mais demande possible | Conciliation FR-SOC-05 | Client | Confort |
| Q76 | « Signaler cette publication » ouvre la modale (pas `/signaler?post=`) | Modale de modération | Client | Confort |
| Q77 | Événements : une adresse, deux publics (Vue membre, Blade visiteur) | `InertiaForMembers` | Technique | Confort |
| Q78 | Forums : permissions | Aucune permission `forums.*` dédiée | Technique | Confort |
| Q79 | « Suivre » réservé aux profils ouverts au réseau | Appliqué | Client | Confort |
| Q60 | Ressources : `external_url` en lien seulement en `http(s)` | `url:https` à valider en P12-03 | Technique | Confort |

## 4. Lecture

- 14 questions bloquent la recette (surtout contenu et périmètre) ; 20 sont à trancher avant la mise en production, dont deux d'ordre juridique (Q67, Q68).
- Beaucoup de lignes « Appliqué, à valider » (Q70 à Q94) sont des choix techniques déjà livrés : sans réponse, ils restent en vigueur ; seule une contre-décision demande du travail.
- Pour chaque Q : le détail des options est dans le §7 du plan, à la ligne du même numéro.
