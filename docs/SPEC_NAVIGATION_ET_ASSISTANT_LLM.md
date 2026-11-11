# Spec — Navigation sans rechargement (Livewire) et assistant LLM (Gemini / OpenAI)

Statut : Partie 1 (navigation) implémentée le 27/10/2026 (P10-09) ; Partie 2 (assistant LLM) : proposition, rien n’est implémenté. Réf. : Q7 (fournisseur LLM), Q41, ADR-18, ADR-26, ADR-30, P8-11, P12-07.

## État actuel (vérifié dans le code)
- **Navigation** : aucun `wire:navigate` dans le projet. Admin = Livewire 3 (rechargement complet à chaque lien) ; membre = Vue + Inertia (déjà sans rechargement) ; public = Blade + Alpine, sans Livewire (budget JS < 60 Ko).
- **Assistant** : widget public (offcanvas, `POST /api/v1/assistant/question`) et page membre `/assistant`, branchés sur l'interface `AssistantProvider`. Seul le pilote `kb` (base de connaissances, sans LLM) existe. **Aucun pilote Gemini ou OpenAI n'est écrit.** Garde-fous de crise, escalade vers un humain, limiteurs : livrés.

---
# Partie 1 — Navigation sans rechargement

## Problème
Chaque clic dans l'administration recharge la page entière (layout, JS, polices), ce qui ralentit les agents de modération et coupe le sondage du bandeau SOS.

## Objectifs
1. Navigation admin sans rechargement : < 300 ms perçus entre deux écrans (cache + prefetch).
2. Le bandeau SOS, la cloche et la barre latérale ne se réinitialisent pas.
3. Zéro régression des écrans existants (Chart.js, Quill, Choices, flatpickr, SweetAlert2).

## Non-objectifs
- Pas de `wire:navigate` sur le site public (Livewire n'y est pas chargé ; budget 60 Ko, SEO, service worker).
- Pas de refonte de l'espace membre (Inertia fait déjà le travail).
- Pas de transition animée entre pages.

## Exigences
**P0**
- `wire:navigate` (avec `wire:navigate.hover` pour le prefetch) sur les liens de la barre latérale, des fils d'Ariane et des listes de l'admin ; `config/livewire.php` : `navigate.show_progress_bar = true`, couleur = jeton `--xm-primary`.
- Scripts : `admin.js` chargé une fois (`data-navigate-track`) ; scripts par écran (`@push('adminScripts')`, import dynamique de Chart.js, Quill…) ré-initialisés sur `livewire:navigated`, jamais dupliqués.
- `@persist` sur la barre latérale, la cloche et le bandeau SOS ; `aria-current` et titre de page mis à jour à chaque navigation ; focus déplacé sur le `h1` (WCAG 2.4.3) et annonce vocale du changement de page.
- CSP : tous les scripts injectés portent le nonce (`Vite::cspNonce()`).
- Liens vers le site public ou le membre, téléchargements signés, exports et `/admin/maintenance → aperçu` : **sans** `wire:navigate` (chargement complet).
- Réponses 401 / 419 / 503 pendant une navigation : retour au chargement complet (déconnexion, maintenance) sans écran blanc.
**P1** : cache de pages (`wire:navigate` + `@persist` des filtres de tableau), indicateur hors ligne.
**P2** : mêmes principes pour un futur espace Livewire côté membre (non prévu : D9 impose Vue).

## Critères d'acceptation
- Étant donné un admin connecté, quand il clique « Alertes » puis « Registres », alors l'URL change, le `<title>` change, le document n'est pas rechargé (marqueur `window.__nav` conservé) et le bandeau SOS garde son état.
- Quand l'écran suivant utilise Chart.js, alors le graphique s'affiche une seule fois (pas de doublon après aller-retour).
- Quand la session expire pendant une navigation, alors l'admin arrive sur `/connexion` avec le message d'inactivité.
- Quand le mode maintenance est activé, alors un admin continue de naviguer ; un visiteur reçoit la page 503.
- Test Playwright : 5 écrans enchaînés, 0 erreur console, 0 violation axe.

## Risques
Scripts dupliqués, mémoire (écouteurs non retirés), nonce CSP oublié, sondages Livewire qui continuent hors écran → tests e2e de navigation répétée (20 allers-retours, mémoire stable).

## Effort
Petit : 2 à 3 jours (layout admin, 24 écrans à vérifier, tests Playwright).

---
# Partie 2 — Assistant avec réponses automatiques par un LLM

## Problème
La base de connaissances répond seulement aux questions prévues. Les membres posent des questions libres (droits, santé, sécurité numérique) auxquelles une réponse rédigée, adaptée et sourcée aiderait, sans jamais remplacer une personne en cas de danger.

## Objectifs
1. Réponses libres de qualité, **ancrées dans les contenus Xomiton** (RAG sur `assistant_kb`, thématiques, FAQ, lois).
2. Aucune donnée personnelle envoyée au fournisseur ; aucun message de crise traité par le LLM.
3. Coût plafonné et repli automatique sur la base de connaissances.
4. Pilote interchangeable : `kb` (défaut), `gemini`, `openai`.

## Non-objectifs
- Pas de LLM pour les visiteurs non connectés (Q41 : base de connaissances seulement).
- Pas de conseil médical, juridique ou psychologique individualisé ; pas de mémoire des conversations côté fournisseur.
- Pas d'ajustement fin (fine-tuning) ni de stockage des échanges chez le fournisseur.

## Conception
- Nouveaux pilotes `App\Infrastructure\Assistant\{GeminiProvider, OpenAiProvider}` implémentant `AssistantProvider`, sélectionnés par `ASSISTANT_PROVIDER` ou par le réglage admin (`/admin/assistant`, P12-07).
- Clés d'API dans `settings` avec `is_secret` (chiffrées), jamais dans `.env` du dépôt ni dans les journaux.
- Chaîne de traitement : 1) mots-clés de crise → réponse fixe (numéros, SOS, humain), **aucun appel externe** ; 2) retrouver 3 à 5 passages de la base ; 3) retirer téléphones, e-mails, pseudos et noms (masquage) ; 4) appel HTTP avec délai 8 s ; 5) vérification de la réponse (longueur, absence de numéro inventé, avertissement « informations générales ») ; 6) échec, délai ou plafond de coût → repli `kb`.
- Prompt système versionné : ton « tu », jamais de diagnostic, renvoi vers `/urgences` et « Parler à un humain » ; si les passages ne couvrent pas la question, dire qu'on ne sait pas.
- Limites : 20 questions/min existante + plafond par compte (ex. 30/jour) + budget journalier global (coupe le LLM et bascule sur `kb`).
- Journal : métadonnées seulement (pilote, durée, jetons, coût, repli) ; jamais le texte de la question.

## Exigences
**P0** : pilote Gemini **ou** OpenAI (un seul au départ, l'autre derrière la même interface) ; masquage des données personnelles ; court-circuit de crise ; repli `kb` ; plafonds ; clé chiffrée ; consentement explicite avant la première utilisation du LLM (« tes questions sont envoyées à un service externe »), désactivable ; interrupteur général dans `/admin/assistant`.
**P1** : retour utile / pas utile par réponse ; citation des sources internes ; statistiques de coût dans l'admin.
**P2** : second fournisseur en secours, évaluation automatique de la qualité.

## Critères d'acceptation
- Étant donné un message contenant « j'en peux plus, je veux mourir », alors la réponse fixe de crise s'affiche et aucune requête sortante n'est émise (test avec `Http::fake` + `assertNothingSent`).
- Étant donné « mon numéro est +229 01 97 38 88 47, que faire ? », alors le corps envoyé au fournisseur ne contient ni le numéro ni le pseudo.
- Étant donné un fournisseur lent (> 8 s) ou en erreur 5xx, alors la réponse vient de `kb` en moins de 9 s et le repli est journalisé.
- Étant donné le budget journalier atteint, alors tous les appels suivants utilisent `kb`.
- Étant donné un visiteur, alors jamais d'appel LLM.
- La clé n'apparaît ni dans la réponse API, ni dans les journaux, ni dans l'export de sauvegarde en clair.

## Métriques
Avant : part de questions sans réponse de la base. Cibles à 30 jours : −40 % de « je ne sais pas » ; ≥ 80 % de réponses jugées utiles ; coût < budget fixé ; 0 incident de donnée personnelle envoyée ; repli < 5 %.

## Questions ouvertes
- **Juridique / client (bloquant)** : envoi de questions de personnes LBTQ+ à un fournisseur hors du Bénin (Google ou OpenAI, États-Unis) : base légale, consentement, durée de conservation côté fournisseur (offre « zéro rétention »), mention dans la page de confidentialité. Cela peut justifier un pilote auto-hébergé plus tard.
- **Client** : fournisseur retenu (Gemini ou OpenAI), budget mensuel, langues (français seul au départ ; fon et yoruba, qualité faible).
- **Technique** : récupération des passages par mots (aujourd'hui) ou par vecteurs (non disponible sur MySQL mutualisé).

## Effort
Moyen : 4 à 6 jours (pilote, masquage, plafonds, écran admin, tests). Nécessite une clé d'API et une décision juridique avant la production.

---
# Ordre conseillé
1. Navigation admin (petit, sans dépendance).
2. Décision Q7 et avis juridique → puis pilote LLM.
