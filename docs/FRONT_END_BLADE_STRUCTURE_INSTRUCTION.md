---
name: xomiton-front-end-blade-structure
description: Arborescence des vues Laravel de Xomiton — layouts Blade, composants anonymes x-* du design system, partials, composants Livewire de l'administration, points de montage Vue de l'espace membre, conventions de nommage et de props. À charger avant de créer une vue, un composant ou un partial.
---

# Structure Blade, Livewire et Vue

```
resources/
├─ views/
│  ├─ layouts/
│  │  ├─ public.blade.php        en-tête public, pied de page (newsletter), SOS
│  │  ├─ auth.blade.php          deux colonnes illustration / formulaire
│  │  ├─ member.blade.php        coquille de l'application Vue (#app) + données initiales
│  │  └─ admin.blade.php         barre latérale, barre supérieure, @livewireScripts, SweetAlert2
│  ├─ components/                composants anonymes x-* (design-system/blade/components)
│  │  ├─ button, field, error-summary, card, img, urgency, status, alert, modal,
│  │  │  breadcrumb, accordion, tabs, stat, sos-button, toast, empty-state, stepper, avatar, logo
│  │  ├─ seo.blade.php           title, meta, OG, Twitter, JSON-LD (= partials/title-meta.php)
│  │  ├─ newsletter-form.blade.php  formulaire du pied de page (double opt-in)
│  │  └─ admin/  kpi, datatable-toolbar, pagebar, modal, confirm-delete
│  ├─ partials/                  public-header, public-footer, offline-bar
│  ├─ pages/                     pages publiques : home, thematiques/{index,show}, ressources/…, aide/…,
│  │                             signaler, sos, urgences, newsletter/{confirm,unsubscribe}, legal
│  ├─ auth/                      login, register, otp, forgot, reset
│  ├─ livewire/admin/<module>/   index.blade.php, form.blade.php, show.blade.php
│  └─ mail/                      layout.blade.php (mise en page e-mail), template.blade.php
├─ js/
│  ├─ member/                    application Vue 3 (espace membre et pair)
│  │  ├─ main.ts, router.ts, stores/, api/
│  │  └─ components/ Xm*.vue     même HTML que les composants x-*
│  ├─ admin/  sweetalert.js, charts.js, quill-editor.js
│  └─ xomiton-ui.js              Alpine : SOS (sortie rapide retirée, D8)
└─ css/  tokens.css, social-xomiton.css, components.css, xomiton.css
```

## Règles
1. **Composants anonymes** pour tout élément réutilisable (`<x-card>`), jamais `@include` pour du réutilisable.
2. Props typées et documentées en tête (`@props(['variant' => 'primary', 'size' => 'md'])`), attributs fusionnés avec `$attributes->class([...])`.
3. Un composant refuse un rendu inaccessible : `<x-img>` sans `alt` lève une exception en local.
4. Pas de logique métier dans les vues : uniquement de l'affichage, `@can` pour les autorisations.
5. Textes via `__('fichier.cle')`.
6. Livewire : une vue par composant, modales toujours présentes dans le DOM, `wire:key` sur les lignes.
7. Vue : le layout `member.blade.php` fournit `window.XM = { csrf, user, locale }` ; les pages publiques ne chargent pas Vue.
8. Nommage : kebab-case pour les fichiers Blade, PascalCase pour les composants Vue et Livewire.
