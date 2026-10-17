---
name: xomiton-voice-and-tone
description: Règles éditoriales de Xomiton — voix, ton selon le contexte (accueil, communauté, crise, erreur, administration), tutoiement ou vouvoiement par page (Q36), écriture inclusive, mots à employer et à éviter, ponctuation et typographie française. À charger avant d'écrire ou relire tout texte visible.
---

# Voix et ton

## 1. La voix (constante)
Chaleureuse, claire, rassurante, respectueuse. Phrases courtes (≤ 20 mots en moyenne), un message par phrase, verbes d'action, vocabulaire simple.

## 2. Le ton (varie selon le contexte)
| Contexte | Ton | Exemple |
|---|---|---|
| Accueil public | Accueillant, engageant | « Rejoignez notre communauté bienveillante. » |
| Communauté (fil, messages) | Complice, encourageant | « Partage tes pensées, Awa… » |
| Crise (SOS, signalement, urgences) | **Calme, direct, sans adjectif alarmiste** | « Maintiens le bouton 2 secondes pour envoyer ton alerte. » |
| Erreur | Factuel, solution d'abord, sans excuse | « Ce code a expiré. Demande un nouveau code. » |
| Succès | Sobre, au passé | « Ton signalement est envoyé. » |
| Administration | Neutre, précis | « Campagne programmée le 02/11/2026 à 09:00 pour 31 abonné·es. » |
| E-mails transactionnels | Personnel, bref | « Bonjour Awa, voici ton code : 482913. » |

## 3. Tutoiement ou vouvoiement : la règle par page (Q36)

Le registre dépend de **la page** que la personne lit, jamais de la technologie qui la rend (Blade, Livewire, Vue ou Flutter). Les textes vivent dans `lang/fr/*.php` ; chaque fichier porte le registre de l'espace qu'il sert et le dit dans son commentaire d'en-tête.

| Espace | Pages | Registre | Exemple |
|---|---|---|---|
| Site public institutionnel | `/`, `/thematiques`, `/ressources`, `/do-glegbe`, `/actualites`, `/aide`, pages légales, pied de page, pages d'erreur 403 · 404 · 500 · 503 | **vous** | « Rejoignez notre communauté bienveillante. » |
| Newsletter publique | formulaire du pied de page, `/newsletter/confirmer`, `/newsletter/desinscription`, e-mail de confirmation (double opt-in) | **vous** | « Merci ! Un e-mail de confirmation vient de vous être envoyé. » |
| Tunnel d'accès | `/connexion`, `/inscription` (4 phases), code de vérification (OTP), mot de passe oublié | **tu** | « Connecte-toi pour accéder à ton espace. » |
| Parcours de crise | `/signaler`, `/sos`, `/urgences`, suivi par code — **avec ou sans compte** | **tu** | « Maintiens le bouton 2 secondes pour envoyer ton alerte. » |
| Espace connecté | fil, communauté, messagerie, profil, paramètres, notifications, assistant, espace pair, collecte, application mobile, erreurs rencontrées dans l'espace (419, 422, 429) | **tu** | « Ta publication est en ligne. » |
| Administration | `/admin/*`, cloche, toasts SweetAlert2, modales, exports, e-mails envoyés à l'équipe | **vous** (neutre, précis) | « Campagne programmée le 02/11/2026 à 09:00 pour 31 abonné·es. » |
| E-mails transactionnels | compte, codes, mot de passe, alertes routées à une personne membre | **tu** | « Bonjour Awa, voici ton code : 482913. » |
| Campagnes de newsletter | contenu envoyé aux abonné·es | **tu** | « Cette semaine, trois nouvelles fiches pour toi. » |

Règles d'application :
1. **Jamais les deux registres dans un même écran.** Les composants partagés (`x-*`, `Xm*`) sont écrits de manière neutre (infinitif, nom, forme impersonnelle) ou reçoivent leur texte de la page qui les appelle.
2. **Deux clés « Session expirée »** (`lang/fr/errors.php`) : `errors.419.public` vouvoie (« Votre session a expiré, veuillez réessayer. ») pour les formulaires publics ; `errors.419.member` tutoie (« Ta session a expiré. Reconnecte-toi puis réessaie : rien n'a été envoyé. ») pour l'espace connecté et le tunnel d'accès. Aucune autre variante.
3. **`/signaler`, `/sos` et `/urgences` tutoient même en accès public** : ce sont les écrans de crise, où la personne doit se sentir accompagnée, et ils sont atteints aussi bien depuis l'accueil que depuis l'espace connecté. Un seul jeu de textes, donc un seul registre. (Précision apportée à Q36, qui ne nommait pas `/urgences` : à consigner dans `DECISIONS.md`.)
4. Les **messages de validation** (`lang/fr/validation.php`) nomment le champ et tutoient ; ceux des formulaires publics institutionnels (newsletter, contact) sont écrits dans `newsletter.php` et vouvoient.
5. **Sigle unique : LBTQ+** dans tout le produit (choix du prototype, D6 à confirmer avec l'association). Pas de variante « LGBTQ+ », « LGBT », « LBT ».
6. **Aucune promesse « 24 h/24 », « 7 j/7 » ou « disponible à tout moment »** pour une réponse humaine. Formule de référence : « Une personne formée le lira sous 24 h. » L'assistant automatique peut être présenté comme « en ligne 24 h/24 » à condition d'ajouter « réponse humaine sous 24 h » ; la mention « Un·e conseiller·ère est disponible » est retirée tant qu'elle n'est pas appuyée par un service réel (Q36, `COPYWRITING_FRAMEWORK.md` §1).
7. Exception publique conservée du prototype : le bloc « Je me pose des questions » de l'accueil s'adresse directement aux jeunes en tutoiement.
8. Les CTA suivent le registre de la page : infinitif ou vouvoiement sur le site public (« Créer un compte », « Rejoignez-nous »), impératif tutoyé dans l'espace connecté et les écrans de crise (« Maintiens le bouton », « Publie ») — voir `CTA_LIBRARY.md`.

## 4. Écriture inclusive
- Formulations épicènes d'abord (« les personnes », « l'équipe », « chaque membre »).
- Sinon **point médian** : expert·es, pair·es éducateur·rices, abonné·es, connecté·e.
- Une seule graphie dans tout le produit (pas de « Expert.e.s » ni de « conseiller(ère)s » dans le code neuf ; les occurrences du prototype sont à harmoniser).
- Sigle : **LBTQ+** (choix du prototype) ; à confirmer avec l'association (`DECISIONS.md` D6).
- Pas de néologismes (« touxtes », « ielles ») dans les écrans de crise.

## 5. Mots
| Préférer | Éviter |
|---|---|
| personne, survivant·e (si la personne s'identifie ainsi) | victime (sauf vocabulaire juridique ou administratif) |
| signaler, alerter, signalement | dénoncer, dénonciation (jamais à l'écran, Q29 ; la valeur stockée `denonciation` de l'enum `AlertType` reste un détail de code) |
| ton espace, ta communauté | utilisateur, client |
| en sécurité | à l'abri du danger mortel, terrifiant… |
| confidentiel, anonyme | secret |
| Se connecter, Créer un compte | Login, Sign up, S'inscrire ici !!! |

## 6. Typographie française
- Espace insécable avant `: ; ! ?` et à l'intérieur des guillemets « ».
- Apostrophe typographique `’` dans les textes affichés.
- Points de suspension `…` (un caractère).
- Majuscule seulement au premier mot des titres et boutons (« Créer un compte », pas « Créer Un Compte »).
- Pas de capitales pour l'emphase, pas de points d'exclamation multiples, émojis rares et jamais comme icône.
- Dates : « 2 novembre 2026 » (texte) ou `02/11/2026` (tableaux) ; heures « 15 h » (texte) ou `15:00` (tableaux) ; nombres « 1 234 » (espace insécable fine). Formats produits par `App\Support\LocaleFormatter` (`fr-BJ`, repli `fr-FR`) et déclarés dans `lang/fr/dates.php` ; jamais de `->format()` brut dans une vue.
