---
name: xomiton-ux-guidelines
description: Principes d'expérience utilisateur de Xomiton — sécurité d'abord, charge cognitive faible, parcours courts, états d'écran, ergonomie mobile, hors connexion, discrétion, cohérence des cartes, modales, tableaux, filtres et KPI. À charger pour concevoir ou critiquer un parcours ou un écran.
---

# Lignes directrices UX

## 1. Principes
1. **La sécurité de la personne passe avant l'esthétique.** SOS en 2 gestes maximum depuis n'importe quel écran connecté ; signalement sans compte.
2. **Une action principale par écran**, visible sans défilement sur mobile.
3. **Réassurer avant de demander** : dire ce qui sera fait des données avant le formulaire.
4. **Charge cognitive faible** : une question par écran dans les parcours sensibles, textes courts, vocabulaire simple.
5. **Pardonner** : annulation possible (compte à rebours du SOS, retrait du vote, brouillons), confirmation pour l'irréversible.
6. **Discrétion** : rien de sensible à l'écran de verrouillage ni dans les aperçus, mode discret disponible.
7. **Cohérence** : mêmes composants et mêmes emplacements partout ([UI_UX_DESIGN_SYSTEM.md](UI_UX_DESIGN_SYSTEM.md) §4).

## 2. Ergonomie mobile
- Zone du pouce : actions principales en bas (barre d'onglets, pied de modale fixe, bouton d'envoi du chat).
- Cibles ≥ 44 px, espacées ; champs de 48 px dans l'application.
- Claviers adaptés (`inputmode` numérique pour OTP et téléphone, `type=email`).
- Pas de survol nécessaire ; gestes doublés d'un bouton (balayage du carrousel + points + « Suivant »).
- Réseau lent : squelettes plutôt que spinners, images différées, texte avant image.

## 3. États d'écran (tous obligatoires)
| État | Traitement |
|---|---|
| Chargement | Squelettes à la forme du contenu |
| Vide | Illustration + phrase + action |
| Erreur | Ce qui s'est passé + comment corriger + bouton « Réessayer » |
| Hors connexion | Bandeau + file d'attente visible + promesse d'envoi automatique |
| Succès | Toast, mise à jour en place, focus déplacé logiquement |
| Lecture seule | Bandeau explicatif (ex. campagne envoyée), actions masquées |
| Permission refusée | Message clair + qui contacter |

## 4. Formulaires
- Libellés au-dessus, aides sous le champ, validation à la sortie du champ.
- Ne jamais effacer la saisie après une erreur.
- Étapes : stepper visible, retour possible, sauvegarde à chaque étape.
- Données sensibles toujours facultatives et expliquées (« Pourquoi on demande ça ? »).

## 5. Cartes, tableaux, filtres, KPI
- **Cartes** : même structure (étiquette → titre → description → action), hauteur égale par ligne, une seule action si la carte est cliquable.
- **Tableaux** : colonne d'identification en premier, statuts en badge + texte, actions à droite, cartes empilées sur mobile.
- **Filtres** : recherche en premier, filtres dans l'URL, « Réinitialiser » visible quand un filtre est actif, compteur de résultats.
- **KPI** : 4 à 5 maximum, le plus important en premier, cliquables vers la liste filtrée, en accordéon sur mobile.
- **Modales** : pour créer, modifier, confirmer ; jamais pour afficher une page entière ; plein écran sur mobile.
- **Widgets** : un graphique = une question (« combien de cas par mois ? »), titre explicite, légende lisible.

## 6. Parcours critiques (à tester avec des utilisatrices)
1. Signaler une violence anonymement depuis l'accueil en moins de 3 minutes.
2. Déclencher puis annuler un SOS.
3. Créer un compte, valider le code, publier un premier message.
4. Créer un sondage et voir les résultats.
5. (Admin) Créer une campagne ciblée, s'envoyer un test, la programmer.
6. (Admin) Modifier un template e-mail, prévisualiser, envoyer un test, enregistrer.
7. (Admin) Prévisualiser une sauvegarde et la restaurer.

## 7. Mesure
Taux de complétion des formulaires, temps jusqu'à la première action, erreurs de validation par champ, recherches sans résultat, abandons d'étape ; tests utilisateurs trimestriels avec des pair·es éducateur·rices.
