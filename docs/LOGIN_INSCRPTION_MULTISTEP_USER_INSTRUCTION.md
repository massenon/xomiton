---
name: xomiton-login-signup-multistep
description: Instructions pour la connexion, l'inscription en 3 étapes obligatoires (décision du client du 11/11/2026), la vérification par code e-mail (OTP), le mot de passe oublié et le profil progressif de Xomiton, sur le web (Vue / Blade) et sur mobile (Flutter). À charger pour tout écran ou endpoint d'authentification.
---

# Connexion et inscription multi-étapes

Références : `design-system/xomiton/pages/authentification.md` (prime), `prototype_social/sign-in.php`, `sign-up.php`, `forgot-password.php`, simulateur `prototype_mobile/#/signup`, `#/otp`.

## 1. Parcours

```
splash ─► login ─► otp(purpose=login) ─► tableau de bord
   └────► signup (3 étapes, un seul envoi) ─► fil, compte en attente (bandeau « Ton compte attend la validation de l’équipe »)
                                              └─► validation par l’admin (e-mail confirmé par l’admin) ─► options complètes
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

## 4. Inscription en 3 étapes obligatoires (décision du client du 11/11/2026)
Remplace les 4 phases dont une seule obligatoire (D4, P2-03). **Un seul formulaire, un seul envoi à la fin ; le compte n'existe qu'à la fin.** Il est créé `en_attente` (réglage `reg_manual_validation = all` par défaut) et la session est ouverte aussitôt ; l'administration valide le compte et **confirme alors l'e-mail** (`email_verified_at`, journalisé). Aucun code OTP ni e-mail de bienvenue à l'inscription ; l'OTP reste pour la connexion et la réinitialisation. Âge minimal : `xomiton.accounts.min_age` (15), lu partout.

| Étape | Champs (tous obligatoires sauf mention) | Validation |
|---|---|---|
| 1. Informations de base | Prénom, nom, pseudonyme (règle `Pseudo`), e-mail (unique), âge (numérique ≥ `min_age`, tient lieu de confirmation d'âge) | `users.first_name`, `last_name` ; `name` = « Prénom Nom » ; consentement `age_confirmed` enregistré quand même |
| 2. Profil et identité | Pronoms (il / elle / iel), pays (ISO, français, Bénin d'abord), ville (facultative), téléphone (facultatif : `PhoneBj` si Bénin, sinon `+` et 8 à 15 chiffres), département → commune (facultatifs, Bénin seulement), « Tu es ? » : `membre` ou `allie` | `profiles.country_code`, `city` ; zone et commune servent au routage SOS |
| 3. Détails et sécurité | Membre : identité de genre et orientation (listes de `SensitiveProfile` ou `prefer_not` ; `autre` + précision), consentement `sensitive_data` obligatoire. Allié·e : statut (professionnel·le de santé, avocat·e, psychologue, leader religieux·se, activiste / féministe, autre + précision). Mot de passe + confirmation (jauge), CGU, confidentialité | Champs sensibles chiffrés (`profiles.gender_identity*`, `orientation*`, `ally_role*`) ; cases **distinctes, jamais pré-cochées**, horodatées |

Règles d'interface :
- Stepper compact « Étape n sur 3 » (`aria-current="step"`) ; formulaire entièrement rendu par le serveur : **sans JavaScript les trois étapes sont visibles à la suite et s'envoient d'un coup** ; avec Alpine (`xmRegister`), une étape à la fois, étape validée avant « Suivant » (mêmes règles que le serveur), « Précédent » toujours présent (à l'étape 1 il ramène à la connexion), focus sur le titre de l'étape, résumé d'erreurs focalisable + message sous chaque champ, données conservées ; à l'erreur serveur, l'étape fautive est rouverte.
- Jauge : 4 segments + **texte** (« Trop court, Faible, Moyen, Fort, Excellent ») + liste des règles cochées en direct ; couleur jamais seule.
- Compte en attente : bandeau « Ton compte attend la validation de l'équipe ». Garde « options limitées » (`verified`, 403 `account_pending`) : lecture, SOS, alertes, signalement et profil restent ouverts ; publier, commenter, écrire, rejoindre un groupe ou un événement s'ouvrent à la validation.
- Les anciennes URLs `/inscription/profil|securite|consentements` et `/inscription/{etape}/passer` redirigent vers le fil.
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
