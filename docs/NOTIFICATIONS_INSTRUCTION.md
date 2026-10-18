---
name: xomiton-notifications
description: Système de notifications de Xomiton — canaux (cloche en application, toasts SweetAlert2, e-mails par templates en base, push FCM, SMS de secours), catalogue des événements, destinataires, discrétion sur écran verrouillé, préférences et règles d'envoi. À charger pour toute fonctionnalité qui informe un·e utilisateur·rice.
---

# Notifications

## 1. Canaux

| Canal | Technologie | Usage |
|---|---|---|
| Cloche (en application) | Table `notifications` (Laravel), polling 30 s (10 s pour les agent·es de garde) | Toute notification persistante |
| Toast d'interface | **SweetAlert2** (admin, Livewire) · `useToast()` (Vue) · SnackBar (Flutter) | Retour immédiat d'une action |
| E-mail | Template en base (`email_templates`) via `EmailTemplateService::send()` | Comptes, alertes routées, rappels, newsletter |
| Push | FCM (Flutter), Web Push (navigateur) | Messages, alertes, rappels d'événement |
| SMS de secours | Fournisseur configurable | SOS uniquement, si le push échoue |

## 2. Catalogue des événements

| Événement | Destinataires | Canaux | Template e-mail |
|---|---|---|---|
| Inscription créée | Personne inscrite | e-mail | `bienvenue` |
| Code de vérification demandé | Personne | e-mail | `code-verification` |
| Mot de passe oublié | Personne (si le compte existe) | e-mail | `reinitialisation-mot-de-passe` |
| Compte pair validé | Pair | e-mail, cloche | `compte-valide` |
| Alerte routée | Contact de l'annuaire ciblé, admin de garde | e-mail, push, cloche | `alerte-routee` |
| **SOS déclenché** | Contacts de confiance, point focal de zone, admin de garde | push + SMS de secours **dans la requête**, cloche | `alerte-routee` |
| SOS non accusé (5 min) | Point focal national | push, SMS, e-mail | `alerte-routee` |
| Nouveau message | Participant·es | push (aperçu masqué si mode discret), cloche | — |
| Réaction ou commentaire | Auteur·e | cloche (regroupée) | — |
| Rappel d'événement (veille) | Participant·es | push, e-mail | `rappel-evenement` |
| Sondage terminé | Auteur·e | cloche | — |
| Invitation reçue (groupe, événement) | Personne invitée | cloche (préférence « Communauté ») | — |
| Invitation acceptée | Personne qui a invité | cloche (préférence « Communauté ») | — |
| Réponse dans un sujet de forum suivi | Auteur·e et abonné·es du sujet | cloche (préférence « Communauté ») | — |
| Demande d'adhésion à un groupe | Modération du groupe | cloche (préférence « Communauté ») | — |
| Adhésion à un groupe acceptée | Demandeur·se | cloche (préférence « Communauté ») | — |
| Événement créé dans un de mes groupes | Membres du groupe | cloche (préférence « Communauté ») | — |
| Inscription newsletter | Abonné·e | e-mail (double opt-in) | `newsletter-confirmation` |
| Contenu signalé | Modération | cloche admin | — |
| Conflit de synchronisation | Admin | cloche admin | — |

## 3. Cloche d'administration
Panneau à onglets **Non lus · Lues · Toutes les notifications**, « Tout marquer comme lu », lien vers le journal d'audit. Sources : alertes nouvelles, inscriptions à valider, contenus à modérer, conflits de synchronisation. *Les notifications de stock sont supprimées avec le module.*

## 4. Règles
1. **Discrétion** : sur écran verrouillé et si « masquer le contenu sensible » est actif, texte neutre (« Nouveau message ») ; aucune donnée sensible dans la charge utile push.
2. **Pas de doublon** : regrouper les réactions (« Estelle et 4 autres ont aimé… ») ; une notification par événement et par personne.
3. **Préférences** : chaque catégorie désactivable, sauf sécurité du compte et SOS.
4. **Son** : uniquement SOS et cas critiques pour les destinataires concernés ; alternative visuelle et vibration.
5. **Accessibilité** : `aria-live="polite"` (succès, info), `assertive` (erreurs, SOS) ; toasts ≥ 4,5 s, fermables, jamais seule source d'une information.
6. **Langue** : textes dans `lang/`, verbe d'action identique au bouton (« Publier » → « Publié »).
7. **Journalisation** : chaque e-mail est consigné (`mail_logs`) ; chaque échec SOS est rejoué par cron chaque minute.

## 5. Toasts SweetAlert2 (administration)
```js
const Toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 4500, timerProgressBar: true,
  didOpen: t => { t.addEventListener('mouseenter', Swal.stopTimer); t.addEventListener('mouseleave', Swal.resumeTimer); } });
window.addEventListener('toast', e => Toast.fire({ icon: e.detail.type, title: e.detail.message }));
```
Types : `success` (action réussie), `error` (échec, message précis), `warning` (action partielle, ex. import avec lignes rejetées), `info`. Les couleurs SweetAlert2 sont surchargées par les jetons (`--xm-primary`, `--xm-success`, `--xm-danger`).
