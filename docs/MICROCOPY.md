---
name: xomiton-microcopy
description: Microcopie de référence de Xomiton — libellés de menus, boutons, champs, aides, messages de validation et d'erreur, toasts, notifications, états vides, confirmations et modales. À charger pour tout libellé d'interface ; les textes vont dans lang/fr/*.php.
---

# Microcopie

Règle d'or : **le bouton dit ce qui va se passer, le toast confirme avec le même verbe.**

Le registre (tu / vous) de chaque libellé suit la page qui l'affiche : règle par page dans [VOICE_AND_TONE.md](VOICE_AND_TONE.md) §3. Les libellés ci-dessous sont implémentés dans `web/lang/fr/` : `menus.php` (§1), `buttons.php` et `common.php` (§2), `validation.php` et `auth.php` (§4), `microcopy.php` (§5 et §6, états et confirmations de l'espace connecté), `admin.php` (états, modales et toasts de l'administration), `alerts.php` (§7), `errors.php` (pages d'erreur), `notifications.php`, `feed.php`, `community.php`, `profile.php`, `newsletter.php`, `dates.php`. À l'écran, les alertes de type « dénonciation » s'appellent toujours **signalement** (Q29).

## 1. Menus
| Zone | Libellés |
|---|---|
| Public | Apprendre · Communauté · Aide & protection · Signaler · Se connecter · Créer un compte |
| Membre (sidenav) | Fil d'actualité · Mon profil · Messages · Groupes · Pages · Événements · Forums · Qui suivre et invitations · Thématiques · Ressources · Do Glegbe · Violences · Outils CCC · SOS · Signaler · Contacts d'urgence · Annuaire · Assistant · Espace pair · Collecte · Paramètres · Aide · Se déconnecter |
| Mobile (onglets) | Accueil · Fil · Alerte (SOS) · Messages · Profil |
| Administration | Tableau de bord · Alertes & signalements · Indicateurs · Rapports automatisés · Registres numériques · Fiches VBG / VDH · Suivi des victimes · Annuaire dynamique · Règles de validation · Utilisateurs & rôles · Inscriptions (on/off) · Contenu : thématiques & ressources · Newsletter · Outils CCC · Réseau social · Modération · Assistant LLM · Passerelles réseaux · Hors connexion & synchro · Mises à jour & optimisation · Templates e-mails · Paramètres · Journal d'audit |

## 2. Boutons
| Action | Libellé | Toast |
|---|---|---|
| Créer | « Nouveau template », « Nouvelle campagne », « Ajouter » | « Template créé. » |
| Enregistrer | « Enregistrer » | « … enregistré. » |
| Publier | « Publier », « Publier le sondage » | « Ta publication est en ligne. » / « Ton sondage est en ligne. » |
| Voter | « Voter » | « Merci, ton vote est enregistré. » |
| Envoyer un test | « Envoyer » / « M'envoyer » | « Test envoyé à … » |
| Programmer | « Programmer l'envoi » | « Campagne programmée le … pour … abonné·es. » |
| Envoyer maintenant | « Envoyer maintenant » | « Campagne envoyée à … abonné·es. » |
| Supprimer | « Supprimer définitivement » | « … supprimé. » |
| Restaurer | « Restaurer » | « Sauvegarde du … restaurée (… fichiers). L'état précédent a été sauvegardé automatiquement. » |
| Sauvegarder | « Sauvegarder maintenant » | « Sauvegarde de la base de données : … fichiers sauvegardés (… Ko). » |
| Annuler | « Annuler » | — |
| S'inscrire (newsletter) | « S'inscrire » | « Merci ! Un e-mail de confirmation vient de vous être envoyé. » |

## 3. Champs (libellé · aide)
- Pseudonyme · « Visible par la communauté. Ton vrai nom n'est jamais demandé. »
- Mot de passe · règles affichées avant la saisie, jauge « Trop court, Faible, Moyen, Fort, Excellent ».
- Slug unique · « Minuscules, chiffres et tirets. Identifiant utilisé par le code pour envoyer cet e-mail. »
- Texte d'aperçu · « Affiché après l'objet dans la boîte de réception. »
- Question du sondage · compteur « 0 / 200 ».
- Description (signalement) · « Ce qui s'est passé, quand, et si tu es toujours en danger. »

## 4. Erreurs de validation
| Cas | Message |
|---|---|
| Champ requis | « Indique ton e-mail ou ton pseudonyme. » (nommer le champ, jamais « Champ requis ») |
| E-mail invalide | « Indique une adresse e-mail valide, par exemple nom@exemple.bj. » |
| Mot de passe faible | « Ton mot de passe doit être au moins « Fort » : 10 caractères, majuscule, minuscule et chiffre. » |
| Identifiants | « Identifiants incorrects. Vérifie ton e-mail et ton mot de passe. » |
| OTP | « Ce code est incorrect ou a expiré. Vérifie le dernier e-mail reçu ou demande un nouveau code. » |
| Sondage | « Un sondage comporte entre 2 et 6 réponses. » · « Deux réponses du sondage sont identiques. » |
| Slug pris | « Le slug « … » est déjà utilisé par « … ». » |
| Segment vide | « Aucun·e abonné·e actif·ve ne correspond à ce segment : élargissez les critères. » |
| Consentement | « Cochez la case de consentement pour vous inscrire. » |
| Session expirée (formulaire public, vous) | « Votre session a expiré, veuillez réessayer. » — `errors.419.public` |
| Session expirée (espace connecté et tunnel d'accès, tu) | « Ta session a expiré. Reconnecte-toi puis réessaie : rien n'a été envoyé. » — `errors.419.member` (deux clés, jamais une troisième ; `VOICE_AND_TONE.md` §3, Q36) |
| Réseau | « Tu es hors connexion. Ton message partira dès que le réseau revient. » |

## 5. États vides
- Fil filtré : « Rien ici pour l'instant. Sois la première personne à publier dans cette catégorie. » + « Publier ».
- Liste admin : « Aucun·e abonné·e ne correspond aux filtres. » / « Aucune campagne. Créez la première avec « Nouvelle campagne ». »
- Sauvegardes : « Aucune sauvegarde. Lancez « Sauvegarder maintenant ». »
- Commentaires : « Aucun commentaire. Écris le premier, avec bienveillance. »

## 6. Confirmations (modales)
- Supprimer un template système : « Template système. La plateforme l'utilise pour des e-mails critiques (réinitialisation de mot de passe, codes, alertes…). Sans lui, ces envois échoueront. Pour confirmer, saisissez son slug. »
- Restaurer : « La restauration remplace les données actuelles. L'état actuel est sauvegardé automatiquement juste avant. Tapez RESTAURER. »
- Envoyer une campagne : « Envoyer maintenant cette campagne à 31 abonné·e(s) ? »
- Supprimer un·e abonné·e : « L'adresse … et son historique seront effacés (droit à l'effacement RGPD). Pour simplement arrêter les envois, préférez le statut « Désabonné·e ». »

## 7. SOS et signalement
- « Maintiens le bouton 2 secondes pour envoyer ton alerte. »
- « Envoi de ton alerte dans 5… Tu peux encore annuler. » · « Annuler l'alerte »
- « Ton alerte est partie. » · « Je suis en sécurité »
- « Brouillon enregistré sur ton téléphone. Tu peux reprendre plus tard. »
- « Ton signalement est envoyé. Une personne formée le lira sous 24 h. » · « Ton code de suivi »
