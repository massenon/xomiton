# Xomiton — MASTER (source de vérité du design system)

> Lecture : ce fichier d'abord, puis `pages/<page>.md` s'il existe (ses règles **priment** sur celles-ci). Détail : `../charte-graphique/CHARTE_GRAPHIQUE.md`. Tokens : `../tokens/`.
> Généré avec la méthode du skill *ui-ux-pro-max* (recherche `--design-system` : motif « Feature-Rich Showcase », style « Vibrant & Block-based »), **puis adapté** : la palette rose/bleu et les polices proposées par l'outil sont remplacées par la marque Xomiton (bleu `#0433C3`, rouge `#E30E0E`, Montserrat + Arial). Les règles retenues de l'outil : contrastes 4,5:1, focus visible, `prefers-reduced-motion`, cibles 44 px, 4 gabarits de test, pas d'émoji comme icône, `cursor: pointer` sur le cliquable.

## 1. Pile et règle de dépendance
Laravel 12 · Livewire 3 · Alpine 3 · **Bootstrap 5.3.3 (thème Social) comme unique framework CSS** · Bootstrap Icons. Pas de Tailwind (ADR-03). Espace membre et pair éducateur·rice : **Vue 3 + Inertia.js** (D9), composants `Xm*` au HTML identique aux `x-*`. Composants = **composants Blade anonymes `x-*`** (`resources/views/components/`), jamais d'`@include` pour du réutilisable ; état visuel local en Alpine, état serveur en Livewire.
Ordre de chargement CSS : `style.css` (Social) → `tokens.css` → `social-xomiton.css` → CSS de page éventuel.

## 2. Couleurs (variables)
| Token | Clair | Sombre | Rôle |
|---|---|---|---|
| `--xm-primary` | `#0433C3` | `#7C97FF` | Action, lien, navigation active, focus |
| `--xm-secondary` / `--xm-danger` | `#E30E0E` | `#FF6B6B` | **Attention** : SOS, signaler, critique, suppression |
| `--xm-bg` / `--xm-surface` | `#FBF7FA` / `#FFFFFF` | `#0E1020` / `#171A2E` | Fond, cartes |
| `--xm-text` / `--xm-text-muted` | `#1B1630` / `#5B5670` | `#EDEAF7` / `#A9A5C0` | Texte |
| `--xm-border` | `#E4DCE6` | `#2A2E48` | Filets |
| `--xm-success` · `--xm-warning` · `--xm-info` | `#0B7A4B` · `#F0A800` · `#1864AB` | variantes claires | États |
Règles : 60 % neutres / 30 % bleu / ≤ 10 % rouge ; rouge jamais décoratif ni seul ; **bleu sur rouge interdit (1,91:1)** ; hexadécimal interdit dans les composants.

## 3. Typographie
Titres **Montserrat** 800/700/600 · sous-titres et texte **Arial** 400/700 · échelle fluide `--xm-fs-*` · interligne 1,2 / 1,6 · mesure 68 ch · un seul `h1`. Montserrat auto-hébergée (WOFF2 latin) ; sur Android Arial → Roboto/Liberation (tester).

## 4. Espacement, formes, mouvement
Base 4 px (`--xm-space-1..9`) · rayons 8/16/pastille · cible ≥ 44 px · durées 120/200/300 ms, `--xm-ease-out`, uniquement `transform`/`opacity` · `prefers-reduced-motion` respecté.

## 5. Composants (résumé — détail dans `../components/COMPOSANTS.md`)
Boutons `btn-primary` (bleu), `btn-secondary` (rouge, **réservé** attention) · `x-card` (`.xm-card`, survol −2 px) · champs 44 px, libellé visible, erreur sous le champ **et** résumé focalisable · badge urgence `.xm-urgency--*` (couleur + icône + mot) · SOS 64 px maintien 2 s · modales à focus piégé, pied fixe, plein écran sur mobile · tableaux → cartes empilées < 768 px.

## 6. Layouts
Public : header sticky « verre » + burger/offcanvas < 992 px, hero fluide `min-height: clamp(520px, 78vh, 780px)`, footer 4 colonnes avec inscription à la newsletter, SOS en permanence. Connecté : 3 colonnes (nav · contenu · suggestions) ; mobile : barre d'onglets inférieure à 5 entrées max, SOS au centre. Admin : barre latérale repliable, fil d'Ariane, titre + actions de page.

## 7. Accessibilité (obligatoire)
Contraste AA · focus 3 px décalé 2 px · `scroll-padding-top` 96 px · lien d'évitement · ARIA des modales/onglets/accordéons · pas de test cognitif à la connexion · `aria-live` pour toasts et cloche · alternative visuelle et vibration au son.

## 8. Anti-patterns (à refuser en revue)
Hex en dur · deux frameworks CSS · `outline: none` sans remplacement · icône seule sans libellé · couleur seule pour l'état · hover seul pour une action · animation de largeur/hauteur · `@if` pour monter/démonter une modale Livewire · texte < 14 px · hauteur de hero fixe · Font Awesome · émoji icône · rouge décoratif.

## 9. Check-list avant livraison d'un écran
- [ ] Tokens uniquement, aucun hexadécimal
- [ ] 4 gabarits vérifiés (360, 768, 1024, 1440) sans défilement horizontal
- [ ] Clavier : ordre logique, focus visible, aucun piège
- [ ] Contraste AA (outil), mode sombre vérifié
- [ ] `alt`/`title` des images, `width`/`height`, `loading`
- [ ] Mouvement réduit testé
- [ ] Lecteur d'écran : titres, repères, états annoncés
- [ ] Textes depuis `lang/` (microcopy validée), ton bienveillant
- [ ] Performance : LCP < 2,5 s, CLS < 0,1
