# Feuille de décision — les 14 questions qui bloquent la recette (+ 4 juridiques)

Comment l'utiliser : pour chaque ligne, coche **OK** (on garde la recommandation, c'est déjà le comportement actuel) ou écris ta décision. Sans réponse, la recommandation reste appliquée. Détail des options : `IMPLEMENTATION_PLAN.md` §7, même numéro.

## A. À trancher en premier (changent le travail à faire)

| Q | Question | Ma recommandation | Pourquoi | Ta décision |
|---|---|---|---|---|
| Q7 | Fournisseur SMS, IA et clés push | **SMS** : un fournisseur local ou régional avec numéro d'expéditeur au Bénin (à confirmer par devis) ; **push** : Firebase (FCM), gratuit ; **IA** : ne pas brancher avant l'avis juridique, garder la base de connaissances | Le SOS n'avertit personne de façon réelle tant que SMS et push ne sont pas branchés : c'est le vrai bloquant de mise en service | **Tranché 2026-10-28** (FCM, SMS Vonage ou Twilio, assistant désactivé) ; **précisé 2026-10-29** : le push SOS et alertes part aux **admins ET aux pairs éducateurs** ; comptes Firebase et SMS toujours à fournir par le client |
| Q38 | Périmètre du mobile v1 | ~~Sans espace pair en v1~~ **Recommandation corrigée le 2026-10-29 : l'espace pair est dans l'application v1.** **Une seule application, un seul login** ; trois espaces selon les rôles (Membre, Pair, Admin) avec sélecteur ; barre du bas du mode membre : Accueil, Fil, SOS, Messages, Profil, plus un onglet « Espace pair » ou « Admin » ; admin mobile élargi (alertes, inscriptions, rôles et invitations, modération essentielle, maintenance, notifications, astreinte). Détail : `PLAN_MOBILE_APP_UNIQUE.md` | Le client a tranché : la collecte hors ligne (NFR-08) est nécessaire dès la v1 ; la recommandation initiale (« pair sur le web en v1 ») est abandonnée. **Les maquettes sont à valider par le client avant tout code mobile** | **Tranché 2026-10-29** : application unique, espace pair en v1, périmètre admin élargi, une seule version Play. Maquettes (`prototype_mobile/`) à valider avant tout code |
| Q16 | Bouton principal de l'accueil | **« Créer un compte »** | Plus clair que « Rejoignez-nous » pour une personne qui cherche de l'aide ; c'est le libellé du prototype et de `CTA_LIBRARY.md` | **Tranché 2026-10-28** : « Créer un compte » (hero de l'accueil) |
| Q61 | Attente du GPS avant un SOS sans position | **8 secondes** au total (puis envoi sans position avec repli département / commune) au lieu de 12 + 15 s | Dans une situation de danger, 27 s est trop long ; on prévient quand même la personne de confiance | **Tranché 2026-10-28** : 8 s au total, puis envoi sans position, sans second délai |
| Q89 | Lien Google Maps dans le détail d'une alerte | **Le garder, avec l'avertissement affiché** (la position part chez Google au clic) | Les agents en ont besoin pour localiser ; le clic est volontaire | **Tranché 2026-10-28** : lien Google Maps gardé avec avertissement |

## B. Contenu et ton (ne changent pas le code, bloquent la validation)

| Q | Question | Ma recommandation | Ta décision |
|---|---|---|---|
| Q53 | Sections « chiffres », « témoignages » de l'accueil | **Ne pas les afficher** tant qu'il n'y a pas de vraies données (pas de faux chiffres) | ☐ OK ☐ ___ |
| Q54 | Bande rouge pleine largeur de l'accueil | **Fond rouge très clair + bordure** (le rouge plein est réservé au SOS) | **Ouverte** — Reste ouverte, en attente. Précision : section #alerte de l'accueil, resources/views/public/home/alerte.blade.php |
| Q18 | Pages sobres (SOS, signalement, urgences) pour un membre connecté | **Même gabarit sobre** connecté ou non (parcours critique, rien ne distrait) | ☐ OK ☐ ___ |
| Q56 | « Vous » ou « tu » dans l'assistant | **« Tu »** dans l'assistant et le SOS (ton de soutien), « vous » sur le site public | **Tranché 2026-10-28** : tu dans l'assistant et le SOS, vous sur le site public |
| Q58 | 18 questions de FAQ semées | **Relecture éditoriale obligatoire** avant la production ; les laisser en brouillon d'ici là | **Tranché 2026-10-28** : FAQ maintenue (déjà validée) |
| Q64 | Quiz vrai/faux de `/violences` (santé) | **Faire relire par une personne de santé** ; sinon le retirer | **Tranché 2026-10-28** : quiz maintenu (déjà validé) |
| Q10 | Allié·e / personne ressource | **Garder membre / pair**, statut allié·e facultatif | ☐ OK ☐ ___ |
| Q14/Q87 | Classement des 24 écrans admin en 8 groupes | **Garder le classement actuel**, ajuster après un essai avec un vrai admin | ☐ OK ☐ ___ |
| Q59 | Contacts d'urgence hors connexion | **Garder l'indicateur « contact d'urgence »** (liste courte et fiable, sans les contacts privés) | ☐ OK ☐ ___ |

## C. Juridique et avant la production

| Q | Question | Ma recommandation | Ta décision |
|---|---|---|---|
| Q68 | Âge minimal d'inscription | **16 ans**, ou 15 ans seulement si le conseil juridique le valide (consentement des mineur·es au Bénin) | **Tranché 2026-10-28** : 15 ans, valeur unique `xomiton.accounts.min_age` |
| Q67 | Textes légaux (confidentialité, charte, conditions) | **Relecture par un juriste** avant la mise en ligne ; les textes actuels sont des propositions | ☐ OK ☐ ___ |
| Q9 | Où sauvegarder hors du serveur | **Stockage d'objets compatible S3 chez un autre hébergeur**, chiffré, rétention 30 jours | ☐ OK ☐ ___ |
| Q57 | E-mails en file stockés en clair | **Chiffrer le corps** de `mail_outbox` ou le purger dès l'envoi (je peux le faire) | **Tranché 2026-10-28** : purge du corps dès l'envoi ; échecs conservés 7 jours au plus |

## D. Décision hors du plan : l'assistant IA

| Sujet | Ma recommandation |
|---|---|
| Brancher Gemini ou OpenAI ? | **Pas avant** : (1) avis juridique sur l'envoi de questions de personnes LBTQ+ hors du Bénin, (2) consentement explicite dans l'application, (3) budget mensuel plafonné. D'ici là, la base de connaissances suffit et ne partage aucune donnée. |

## E. Décisions du client du 29 octobre 2026 (application mobile unique)

Le client a tranché (détail et effets : `PLAN_MOBILE_APP_UNIQUE.md` §10, D-MOB-1 à D-MOB-14).

| # | Sujet | Décision | Statut |
|---|---|---|---|
| 1 | Une seule application, un seul login | Membre, pair et admin utilisent les mêmes écrans de connexion. L'inscription publique ne crée que des **membres**. Un membre peut recevoir le rôle **pair éducateur·rice** (il est alors membre ET pair : réseau social + espace pair). L'admin est par défaut aussi membre (réseau social + admin). L'admin crée un·e pair par **invitation** (même mécanisme d'inscription) et valide inscriptions et demandes depuis son tableau de bord web **ou** l'appli mobile | **Tranché 2026-10-29** |
| 2 | Espace pair | Dans l'application mobile **dès la v1** (corrige la recommandation de Q38) | **Tranché 2026-10-29** |
| 3 | Périmètre admin mobile **élargi** | Alertes, validation des inscriptions et des pairs, attribution de rôles et invitations, mode maintenance, notifications, modération essentielle (contenus signalés), recherche de membre, tableau d'astreinte | **Tranché 2026-10-29** |
| 4 | Conflit 409 de synchronisation | **Le pair tranche lui-même** : valeur du serveur ou valeur de son appareil | **Tranché 2026-10-29** |
| 5 | Position d'un SOS | **Arrondie** (environ 1 km) pour les admins | **Tranché 2026-10-29** |
| 6 | Push SOS et alertes | Envoyé aux **admins ET aux pairs éducateurs** | **Tranché 2026-10-29** |
| 7 | Mise en ligne Play | **Une seule version** Play | **Tranché 2026-10-29** |
| 8 | Fiche Play | Âge minimal **15 ans**, **pas de tarif**, **tous les pays**, tranches d'âge Play « 16-17 » et « 18+ » seulement, **français uniquement** | **Tranché 2026-10-29** |
| 9 | Validation | Les **maquettes** (`prototype_mobile/`) sont validées par le client **avant tout code mobile** | **Tranché 2026-10-29** |

Points que les maquettes proposent et que le client doit confirmer : voir `PLAN_MOBILE_APP_UNIQUE.md` §10 (D-MOB-15 à D-MOB-18) et `DECISIONS_A_VALIDER.md`.

## Ensuite
Ce que je peux faire dès que tu as répondu : appliquer les décisions qui changent le code (Q61, Q54, Q16, Q57, Q56), consigner le reste dans `design-system/DECISIONS.md`, et préparer le branchement SMS / push (Q7) dès que tu as choisi le fournisseur.
