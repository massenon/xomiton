# Surcharge — Inscription, connexion, mot de passe (`/inscription`, `/connexion`…)

> Prime sur MASTER.md. Inspiré du gabarit Social « sign-in » (image à gauche, formulaire à droite) avec `images/sign-in.jpg` (à recompresser) ; la logique métier est celle de `xomitonapp` (voir `xomiton_implementation/01 §4` et `04 M02`).

**Mise en page** : ≥ 992 px : 2 colonnes (illustration 5/12 · formulaire 7/12, formulaire centré, 440 px) ; < 992 px : formulaire seul, illustration supprimée, logo au-dessus. Pas de rouge (sauf erreurs).

**Réduire la complexité de l'inscription** (retour du client : l'inscription en plusieurs étapes est jugée trop complexe) :
1. **Étape 1 seule obligatoire** : pseudo, e-mail *ou* téléphone, mot de passe. Compte créé et connecté immédiatement.
2. Les champs Xomiton (pronoms, type de membre, identité de genre, orientation, statut allié·e/ressource, âge, ville) passent en **profil progressif** : bandeau « Complète ton profil, c'est facultatif » après la première connexion, un groupe de champs à la fois, chaque champ expliqué (« Pourquoi on demande ça »).
3. Consentements (CGU, confidentialité) sur l'écran 1, cases distinctes, texte clair.
4. Vérification e-mail/SMS en différé (accès limité tant qu'elle n'est pas faite, jamais bloquant pour le SOS).

**WCAG 2.2 (authentification accessible)** : `autocomplete="username"` / `current-password` / `new-password`, **collage autorisé**, bouton « Afficher le mot de passe », pas de CAPTCHA à image, alternative au code SMS saisi à la main (lien magique ou copier-coller du code), messages d'erreur précis sans révéler si un compte existe.
**Mot de passe** : jauge de force (texte + couleur), minimum 10 caractères, règles affichées avant la saisie.
**Erreurs** : résumé focalisable en haut + message sous le champ, ton bienveillant.
**Sécurité visuelle** : mention discrète « Aucune donnée n'est visible depuis l'écran de verrouillage ».
