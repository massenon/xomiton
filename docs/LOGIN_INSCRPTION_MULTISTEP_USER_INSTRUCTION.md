---
name: xomiton-login-signup-multistep
description: Instructions pour la connexion, l'inscription en plusieurs étapes (4 phases dont une seule obligatoire), la vérification par code e-mail (OTP), le mot de passe oublié et le profil progressif de Xomiton, sur le web (Vue / Blade) et sur mobile (Flutter). À charger pour tout écran ou endpoint d'authentification.
---

# Connexion et inscription multi-étapes

Références : `design-system/xomiton/pages/authentification.md` (prime), `prototype_social/sign-in.php`, `sign-up.php`, `forgot-password.php`, simulateur `prototype_mobile/#/signup`, `#/otp`.

## 1. Parcours

```
splash ─► login ─► otp(purpose=login) ─► tableau de bord
   └────► signup (4 phases) ─► otp(purpose=signup) ─► tableau de bord ─► bandeau « Complète ton profil »
login ─► forgot ─► otp(purpose=reset) ─► reset ─► login
```

## 2. Mise en page
- ≥ 992 px : deux colonnes (illustration 5/12 avec logo blanc et promesse · formulaire 7/12 centré, 440 px max) ; < 992 px : formulaire seul, logo au-dessus.
- Mobile (app) : écran plein, carrousel de splash avant la première connexion (`onboardingProvider.seen`).
- Pas de rouge hors erreurs.

## 3. Connexion
| Champ | Règles |
|---|---|
| E-mail ou pseudonyme | `autocomplete="username"`, requis |
| Mot de passe | `autocomplete="current-password"`, bouton afficher / masquer, **collage autorisé** |
Erreur unique et indifférenciée : « Identifiants incorrects. Vérifie ton e-mail et ton mot de passe. » Compte en attente, suspendu ou maintenance : message spécifique **après** vérification du mot de passe. Limite : 5 essais / minute.

## 4. Inscription en 4 phases
| Phase | Obligatoire | Champs | Validation |
|---|---|---|---|
| 1. Compte | **Oui** | Pseudonyme (3–40, unique), e-mail (unique), mot de passe + **jauge de solidité** | Mot de passe ≥ « Fort » : 10 caractères, majuscule + minuscule, chiffre ; symbole pour « Excellent » |
| 2. Profil | Non (« Passer ») | Pronoms, département (→ commune), type : membre / **pair éducateur·rice** | Un compte pair est créé `en_attente` (RG-03) |
| 3. Sécurité | Non (« Passer ») | Contact de confiance (téléphone), mode discret | Téléphone au format béninois |
| 4. Consentements | **Oui** | CGU + charte (obligatoire), confidentialité (obligatoire), données sensibles (facultatif), newsletter (facultatif) | Cases **distinctes, jamais pré-cochées** ; chaque consentement est horodaté dans `consents` |

Règles d'interface :
- Stepper « Étape n sur 4 » (`aria-current="step"`), bouton Retour toujours présent, données conservées entre les étapes.
- Jauge : 4 segments + **texte** (« Trop court, Faible, Moyen, Fort, Excellent ») + liste des règles cochées en direct ; couleur jamais seule.
- Erreurs : résumé focalisable en haut + message sous le champ.
- Inscriptions fermées par l'administration : formulaire remplacé par un message et un lien vers la newsletter.

## 5. Code par e-mail (OTP)
- **Pourquoi l'e-mail** : couverture universelle, coût nul, aucune dépendance opérateur.
- Un seul écran pour `purpose = login | signup | reset`.
- 6 cases numériques, `inputmode="numeric"`, `autocomplete="one-time-code"` sur la première, **collage du code complet** réparti automatiquement, retour arrière qui recule.
- Code valable 10 min, 5 essais, stocké haché ; renvoi possible après 30 s (le nouveau code annule l'ancien), 5 envois / heure.
- Erreur : « Ce code est incorrect ou a expiré. Vérifie le dernier e-mail reçu ou demande un nouveau code. »
- Template : `code-verification` (`{{otp_code}}`, `{{otp_expiry}}`).

## 6. Mot de passe oublié
1. Saisie de l'e-mail → message **identique** que le compte existe ou non (« Si un compte correspond à cette adresse, un code vient d'être envoyé. »).
2. OTP `purpose=reset` → jeton de réinitialisation.
3. Nouveau mot de passe + confirmation (jauge) → révocation de toutes les sessions et jetons, e-mail de confirmation, retour à la connexion avec toast.

## 7. Profil progressif
Après la première connexion : bandeau « Complète ton profil, c'est facultatif » + barre de complétion. Un groupe de champs à la fois, chaque groupe avec « Pourquoi on demande ça ? ». Les données sensibles exigent le consentement `sensitive_data`.

## 8. Accessibilité (WCAG 2.2)
3.3.8 authentification accessible : pas de test cognitif, pas de CAPTCHA à image, collage autorisé, gestionnaires de mots de passe compatibles. 3.3.7 saisie redondante : les données des étapes précédentes ne sont jamais redemandées.

## 9. API
`POST /api/v1/auth/register` · `POST /api/v1/auth/login` · `POST /api/v1/auth/otp/send` · `POST /api/v1/auth/otp/verify` · `POST /api/v1/auth/password/forgot` · `POST /api/v1/auth/password/reset` · `POST /api/v1/auth/logout` · `PATCH /api/v1/me/profile`.
