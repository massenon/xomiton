---
name: xomiton-development-standards
description: Standards de développement Xomiton — conventions Laravel 12, Livewire 3 (administration, modales CRUD, DataTables, SweetAlert2), Vue 3 (espace membre et pair), JavaScript, validation, sécurité, notifications, performances, tests, Git et revue. À charger avant d'écrire ou de relire du code.
---

# Standards de développement

## 1. Généralités
- PHP 8.3, `declare(strict_types=1);`, PSR-12, Laravel Pint (preset `laravel`), Larastan niveau 6.
- Code en anglais (classes, méthodes, colonnes) ; textes d'interface en français dans `lang/fr/*.php` ; commentaires en français, courts, sur le **pourquoi**.
- Pas de code mort, pas de `dd()`, `dump()`, `console.log` commités.
- Une classe = une responsabilité ; méthodes < 30 lignes ; pas plus de 3 niveaux d'imbrication.

## 2. Laravel
| Sujet | Convention |
|---|---|
| Routes | `routes/web.php` (public, Vue), `routes/admin.php` (Livewire, préfixe `/admin`, middleware `auth`, `can:admin.access`), `routes/api.php` (préfixe `/api/v1`, `auth:sanctum`) ; noms `module.action` |
| Contrôleurs | Fins, invocables ou ressources ; délèguent à un cas d'usage `app/Application/<Module>/<Action>.php` |
| Validation | **Form Request** par action (`StorePollRequest`) ; messages dans `lang/fr/validation.php` ; règles métier personnalisées (`ValidCommuneForZone`, `CaseCode`) |
| Autorisation | Policies + permissions spatie ; `$this->authorize()` dans chaque action |
| Modèles | `$fillable` explicite, casts (`encrypted`, `array`, `datetime`, enums PHP), scopes nommés (`visibleTo`, `active`), pas de logique HTTP |
| Enums | Enums PHP natifs (`AlertStatus`, `CampaignStatus`, `SubscriberStatus`) avec méthode `label()` et `badge()` |
| Requêtes | Eager loading, `select` des colonnes utiles, pagination, `chunkById` pour les traitements de masse |
| Jobs | Idempotents, `tries`, `backoff`, compatibles `--stop-when-empty` (D7) ; le SOS ne passe **jamais** par la file |
| Événements | `AlertCreated`, `SosTriggered`, `CampaignSent`, `PollVoted` ; écouteurs pour notifications et journal |
| Config | Aucun secret en dur ; `config/xomiton.php` pour les réglages métier (délai d'escalade, taille des lots) |
| Journal d'audit | Service `Audit::log(action, subject, detail)` appelé dans chaque cas d'usage d'administration |

## 3. Livewire 3 (administration)
- Un composant par écran : `Admin\Newsletter\Index`, `Admin\Newsletter\CampaignForm`, `Admin\EmailTemplates\Index`…
- **DataTables réactives** : propriétés publiques `#[Url] $search, $status, $perPage`, `wire:model.live.debounce.300ms` sur la recherche, `WithPagination`, tri via `$sortField/$sortDirection`, requête dans une méthode `rows()` (`#[Computed]`).
- **Modales CRUD** : composant formulaire distinct, ouverture par événement (`$dispatch('open-campaign-form', id: 12)`), modale toujours présente dans le DOM (pas de `@if` autour de la modale), `wire:model.blur` pour la validation en direct, `$this->validate()` puis cas d'usage.
- **Toasts SweetAlert2** : `$this->dispatch('toast', type: 'success', message: __('newsletter.saved'))` ; un seul écouteur global :
  ```js
  window.addEventListener('toast', e => Swal.fire({ toast: true, position: 'top-end', timer: 4500, timerProgressBar: true,
      showConfirmButton: false, icon: e.detail.type, title: e.detail.message }));
  ```
- **Confirmations** : `wire:confirm` pour les actions simples ; SweetAlert2 modale (bouton rouge, focus sur Annuler) pour les suppressions ; saisie de confirmation pour les éléments critiques.
- `wire:loading` avec squelettes ; `wire:key` sur chaque ligne ; aucune requête dans la vue Blade.
- Polling seulement là où c'est utile : cloche (30 s), tableau d'alertes de garde (10 s).

## 4. Vue 3 (espace membre et pair)
- Composition API, `<script setup>`, TypeScript recommandé, Pinia pour l'état partagé (session, notifications, brouillons).
- Composants `Xm*` qui produisent **le même HTML** que le prototype (classes Bootstrap Social + `xm-*`).
- Appels API via un client unique (`useApi()`), gestion centralisée des 401 / 419 / 422 / 429.
- Mises à jour optimistes (j'aime, vote) avec retour arrière en cas d'échec.
- Formulaires : validation à la sortie du champ, messages sous le champ, désactivation du bouton pendant l'envoi.
- Brouillons (publication, signalement) persistés en IndexedDB ; signalement et SOS fonctionnent hors connexion.
- Pas de bibliothèque d'interface supplémentaire (pas de Vuetify, PrimeVue…).

## 5. JavaScript (général)
- ES2020+, modules, pas de jQuery dans le code neuf.
- Délégation d'événements pour les listes dynamiques ; `addEventListener`, pas d'attributs `onclick` dans le code neuf.
- Accessibilité : gérer `aria-expanded`, `aria-pressed`, `aria-live`, focus après action.
- Échapper toute donnée injectée dans le DOM (`textContent`, ou fonction d'échappement).

## 6. Validation
- Serveur d'abord (Form Request) ; client pour le confort.
- Règles du catalogue *Administration › Règles de validation* : `email`, `phone_bj`, `date_not_future`, `age_range`, `min_len_20`, `positive_int`, `commune_in_zone`, `case_code`, `password_strong`.
- Messages précis, bienveillants, qui disent comment corriger ([MICROCOPY.md](MICROCOPY.md)).

## 7. Sécurité
| Risque | Mesure |
|---|---|
| CSRF | Jeton sur tous les formulaires et requêtes `fetch` (en-tête `X-CSRF-TOKEN`) |
| XSS | Échappement par défaut (`{{ }}`), HTML riche nettoyé (HTMLPurifier) pour templates e-mails et campagnes |
| Injection SQL | Eloquent / requêtes préparées uniquement |
| Énumération de comptes | Messages identiques (connexion, mot de passe oublié, newsletter) |
| Force brute | `RateLimiter` : connexion 5/min, OTP 5/h, newsletter 1 / 20 s par session |
| Redirection ouverte | Liens de suivi signés HMAC ; `next` limité aux chemins internes |
| Téléversement | Types et poids contrôlés, ré-encodage, suppression EXIF, stockage hors racine web |
| Données sensibles | Casts `encrypted`, masquage dans les journaux et les aperçus de sauvegarde |
| En-têtes | CSP stricte, HSTS, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy` (géolocalisation sur la page SOS uniquement) |

## 8. Notifications
Voir [NOTIFICATIONS_INSTRUCTION.md](NOTIFICATIONS_INSTRUCTION.md). Toute notification passe par une classe `Notification` Laravel (canaux `database`, `mail`, `fcm`) ; les e-mails utilisent un template en base via `EmailTemplateService`.

## 9. Performances
Voir [PERFORMANCE.md](PERFORMANCE.md). En revue : pas de N+1 (Laravel Debugbar en local, `preventLazyLoading()` hors production), pagination, cache des référentiels (zones, catégories) 24 h.

## 10. Tests
| Type | Outil | Attendu |
|---|---|---|
| Unitaires (domaine) | Pest | Règles métier RG-01 à RG-16, routage des alertes, calcul des indicateurs, statistiques newsletter |
| Fonctionnels (HTTP, API) | Pest + `RefreshDatabase` | Cas nominal, 422, 403 par rôle, 429 |
| Livewire | `Livewire::test()` | Recherche, filtres, pagination, modale (création, erreur, succès), suppression |
| Vue | Vitest + Testing Library | Composants critiques (sondage, SOS, formulaire de signalement) |
| Navigateur | Playwright | Parcours : inscription → OTP → fil ; signalement anonyme ; SOS ; campagne newsletter |
| Accessibilité | axe-core dans Playwright | 0 violation sérieuse ou critique |
| Flutter | `flutter test`, tests de widgets et d'intégration | Parcours du simulateur |

Couverture ≥ 70 % sur `app/Domain` et `app/Application`. CI : Pint, Larastan, Pest, Vitest, build Vite, Playwright.

## 11. Git et revue
- Branches `feature/<module>-<sujet>`, `fix/…` ; commits en français à l'impératif (« Ajouter l'envoi de test des templates e-mails »).
- Demande de fusion : description, captures (4 largeurs), étapes de test, migrations, impacts de permissions.
- Revue obligatoire : sécurité, accessibilité, cohérence design system, textes.
