---
name: xomiton-accessibilite
description: Exigences d'accessibilité de Xomiton — WCAG 2.2 niveau AA, ARIA, navigation au clavier, focus visible, contrastes calculés de la charte, mouvement réduit, formulaires, médias, tests manuels et automatisés. À charger pour tout composant, écran ou revue.
---

# Accessibilité (WCAG 2.2 AA)

## 1. Contrastes (valeurs de la charte, opposables)
| Combinaison | Rapport | Usage |
|---|---|---|
| Bleu `#0433C3` sur blanc | 9,26:1 | Texte, liens, boutons ✅ |
| Rouge `#E30E0E` sur blanc / blanc sur rouge | 4,84:1 | Texte ≥ 14 px gras ou ≥ 18 px ✅ (limite basse) |
| Bleu sur rouge | 1,91:1 | **Interdit** |
| Bleu `#0433C3` sur fond sombre | 2,04:1 | Interdit → utiliser `#7C97FF` |
| Rouge sur fond sombre | 3,89:1 | Insuffisant → utiliser `#FF6B6B` |
| Texte `#1B1630` sur `#FBF7FA` | > 15:1 | ✅ |
| Texte atténué `#5B5670` sur blanc | > 7:1 | ✅ |
Éléments non textuels (bordures de champs, icônes porteuses de sens) : ≥ 3:1. Texte sur image : voile garantissant 4,5:1.

## 2. Clavier
- Tout est atteignable et utilisable au clavier, dans l'ordre visuel.
- **Focus visible** : anneau 3 px `--xm-focus-ring`, décalage 2 px ; jamais `outline: none` sans remplacement.
- Lien d'évitement « Aller au contenu » en premier.
- Modales et offcanvas : focus piégé, `Échap` ferme, retour du focus au déclencheur.
- SOS : touche Espace maintenue 2 s, ou double activation puis confirmation.
- Raccourcis documentés (Ctrl K : recherche globale de l'administration) et désactivables.

## 3. ARIA (utiliser le HTML natif d'abord)
| Composant | Attributs |
|---|---|
| Modale | `role="dialog"`, `aria-modal="true"`, `aria-labelledby` |
| Onglets | `role="tablist"`, `tab`, `tabpanel`, `aria-selected`, flèches gauche/droite |
| Accordéon | bouton avec `aria-expanded` et `aria-controls` |
| Boutons bascule (chips, j'aime) | `aria-pressed` |
| Navigation courante | `aria-current="page"` (menus, onglets), `aria-current="step"` (stepper) |
| Toasts, résultats en direct | `role="status"` / `aria-live="polite"` ; erreurs et SOS `assertive` |
| Carrousel | `aria-roledescription="carrousel"`, diapositives étiquetées « 2 sur 4 », pas de défilement automatique |
| Tri de tableau | `aria-sort` sur l'en-tête |
| Champs en erreur | `aria-invalid="true"` + `aria-describedby` vers le message |

## 4. Contenu et formulaires
- Langue de la page et des passages (`lang`).
- Titres hiérarchisés, un seul `h1`.
- Libellés visibles et liés ; groupes en `fieldset`/`legend` ; `autocomplete` correct (1.3.5).
- Erreurs : texte + icône, jamais la couleur seule (1.4.1) ; résumé focalisable.
- Authentification accessible (3.3.8) : collage autorisé, pas de test cognitif.
- Saisie redondante (3.3.7) : ne pas redemander une information déjà fournie.
- Taille des cibles (2.5.8) : ≥ 24 px minimum, 44 px dans Xomiton.
- Focus non masqué (2.4.11) : en-tête sticky et barre d'onglets ne recouvrent pas l'élément focalisé (`scroll-padding-top: 96px`).
- Textes ≥ 14 px, zoom 200 % sans perte, reflow à 320 px (1.4.10).

## 5. Médias
Images : `alt` pertinent ou vide si décoratif. Vidéos : sous-titres, transcription. BD : `alt` par planche. Graphiques : tableau de données ou résumé textuel accessible.

## 6. Mouvement
`prefers-reduced-motion: reduce` → animations et transitions désactivées (pulsation SOS remplacée par une bordure fixe), aucun clignotement > 3 fois par seconde.

## 7. Tests
| Test | Outil | Fréquence |
|---|---|---|
| Automatisé | axe-core (Playwright), Lighthouse | Chaque demande de fusion |
| Clavier | Manuel, 4 parcours critiques | Chaque écran nouveau |
| Lecteur d'écran | NVDA + Firefox, VoiceOver iOS, TalkBack | Avant chaque version |
| Contraste | Outil de contraste, mode sombre | Chaque composant |
| Zoom 200 % et 320 px | Navigateur | Chaque écran nouveau |
