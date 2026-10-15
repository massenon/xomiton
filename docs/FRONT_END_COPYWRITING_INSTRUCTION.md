---
name: xomiton-front-end-copywriting
description: Règles d'écriture appliquées au front-end de Xomiton — où placer les textes (lang/), longueurs maximales par élément, titres, boutons, aides, alt d'images, textes d'accessibilité, pluriels et variables. À charger quand on code un écran contenant du texte.
---

# Écriture dans le front-end

## 1. Où vivent les textes
- Laravel : `lang/fr/<module>.php` (`__('newsletter.campaign.saved')`), pluriels avec `trans_choice`.
- Vue : fichiers `locales/fr/<module>.json` chargés par vue-i18n, mêmes clés que Laravel.
- Flutter : ARB (`lib/l10n/app_fr.arb`).
- **Aucun texte en dur** dans les composants, sauf contenu de démonstration explicitement marqué.

## 2. Longueurs maximales
| Élément | Max | Exemple |
|---|---|---|
| Titre `h1` | 70 car. | « Templates e-mails » |
| Titre de carte `h2.h6` | 40 car. | « Performance des campagnes envoyées » |
| Libellé de bouton | 4 mots | « Programmer l'envoi » |
| Libellé de champ | 5 mots | « E-mail de réponse » |
| Aide sous un champ | 1 phrase | « Visible par la communauté. » |
| Toast | 1 phrase | « Campagne envoyée à 13 abonné·es. » |
| Badge de statut | 2 mots | « Envoi en cours » |
| Description de carte | 25 mots | |

## 3. Règles
1. Les textes respectent [VOICE_AND_TONE.md](VOICE_AND_TONE.md) et [MICROCOPY.md](MICROCOPY.md).
2. Pluriels gérés (« 1 vote », « 9 votes » ; « 1 participant·e », « 3 participant·es »).
3. Variables nommées et échappées (`:count`, `:name`), jamais de concaténation de phrases.
4. Icônes : jamais seules — libellé visible ou `aria-label` (« Supprimer le template Bienvenue »).
5. `alt` des images : décrit l'information (« Couverture de la fiche : Tes droits au Bénin ») ; décoratives : `alt=""`.
6. Textes réservés aux lecteurs d'écran avec `.visually-hidden` (« notifications non lues »).
7. Nombres et dates au format `fr-BJ` (`Intl.NumberFormat('fr-FR')`, `->translatedFormat()`).
8. Majuscule au premier mot seulement ; pas de point final dans les boutons, titres et badges.
