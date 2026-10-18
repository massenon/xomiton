{{--
    Catalogue vivant du design system (/design-system, local et recette ; COMPOSANTS.md §Catalogue, P0-10) :
    chaque composant x-* dans chacun de ses états (défaut, survol décrit, focus simulé, actif, désactivé, chargement, erreur, vide),
    en thème clair et sombre (bascule data-bs-theme-value gérée par resources/js/shared/theme.js).
    Les données affichées sont des exemples.
--}}
@php
    $states = __('design_system.states');
    $sections = __('design_system.sections');
    $hero = asset('assets/images/xomiton/hero-03.jpg');
    $avatar = asset('assets/images/avatar/02.jpg');
    $notifs = [
        ['id' => 'al1', 'icon' => 'bell-fill', 'color' => 'danger', 'text' => 'Alerte SOS · Cotonou, Akpakpa', 'time' => 'il y a 5 min', 'href' => '#', 'read' => false],
        ['id' => 'us2', 'icon' => 'person-plus-fill', 'color' => 'primary', 'text' => 'Inscription à valider · Pair éducatrice, Parakou', 'time' => 'il y a 1 h', 'href' => '#', 'read' => false],
        ['id' => 'mo3', 'icon' => 'shield-exclamation', 'color' => 'warning', 'text' => 'Signalement à modérer · Propos haineux', 'time' => 'il y a 3 h', 'href' => '#', 'read' => false],
        ['id' => 'al4', 'icon' => 'check2-circle', 'color' => 'success', 'text' => 'Alerte traitée · Besoin juridique', 'time' => 'hier', 'href' => '#', 'read' => true],
    ];
    $rows = [
        ['VBG-0412', 'Dénonciation', 'critique', 'nouveau', 'Littoral · Cotonou', 'il y a 12 min'],
        ['VBG-0411', 'Besoin', 'haute', 'en_cours', 'Borgou · Parakou', 'il y a 2 h'],
        ['VBG-0409', 'SOS', 'critique', 'traite', 'Ouémé · Porto-Novo', 'hier'],
    ];
    $timeline = [
        ['title' => 'Entretien d’accueil', 'date' => '12/03/2026', 'datetime' => '2026-03-12', 'by' => 'Pair A.', 'kind' => 'shared', 'badge' => ['label' => 'Réalisé', 'color' => 'success'], 'text' => 'Première écoute, besoins identifiés : hébergement, appui juridique.'],
        ['title' => 'Orientation juridique', 'date' => '15/03/2026', 'datetime' => '2026-03-15', 'by' => 'Juriste M.', 'kind' => 'internal', 'badge' => ['label' => 'Planifié', 'color' => 'warning'], 'text' => 'Note interne : rendez-vous à confirmer avec le point focal.', 'next' => '20/03/2026', 'late' => true],
        ['title' => 'Statut : En cours → En attente de réponse', 'date' => '16/03/2026', 'datetime' => '2026-03-16', 'by' => 'Admin', 'kind' => 'status'],
    ];
    $columns = [
        ['key' => 'code', 'label' => 'Dossier', 'sort' => 'asc', 'href' => '?sort=code'],
        ['key' => 'type', 'label' => 'Type'],
        ['key' => 'priority', 'label' => 'Priorité', 'sort' => 'none', 'href' => '?sort=priority'],
        ['key' => 'status', 'label' => 'Statut'],
        ['key' => 'zone', 'label' => 'Zone'],
        ['key' => 'received', 'label' => 'Reçue', 'sort' => 'none', 'href' => '?sort=received'],
        ['key' => 'actions', 'sr' => 'Actions', 'class' => 'text-end'],
    ];
@endphp
<x-layouts.public :seo="['title' => __('design_system.title'), 'description' => __('design_system.intro'), 'index' => false]">
    <x-slot:head>
        <style>
            /* Styles propres au catalogue : simulation du focus, barre d'outils collante, nuanciers. Jetons uniquement. */
            .xm-demo-focus { outline: var(--xm-focus-width) solid var(--xm-focus-ring) !important; outline-offset: var(--xm-focus-offset); }
            .xm-ds-toolbar { position: sticky; top: 84px; z-index: 1020; background: var(--xm-surface); border: 1px solid var(--xm-border); border-radius: var(--xm-radius-lg); box-shadow: var(--xm-shadow-sm); }
            .xm-ds-swatch { height: 56px; border-radius: var(--xm-radius-md); border: 1px solid var(--xm-border); }
            .xm-ds-state { display: block; font-size: var(--xm-fs-caption); color: var(--xm-text-muted); text-transform: uppercase; letter-spacing: .06em; font-weight: 700; margin-bottom: var(--xm-space-2); }
            .xm-ds-section { scroll-margin-top: 160px; }
            .xm-ds-dark { background: var(--xm-neutral-d-0); }
        </style>
    </x-slot:head>

    <section class="py-4 py-sm-5">
        <div class="container">
            <h1 class="h2 mb-2">{{ __('design_system.heading') }}</h1>
            <p class="lead xm-subtitle xm-prose">{{ __('design_system.intro') }}</p>

            <div class="xm-ds-toolbar d-flex flex-wrap align-items-center gap-3 p-3 mb-5">
                <nav aria-label="{{ __('design_system.toc') }}" class="flex-grow-1">
                    <ul class="nav small flex-wrap gap-1 mb-0">
                        @foreach ($sections as $key => $label)
                            <li class="nav-item"><a class="nav-link py-1 px-2" href="#ds-{{ $key }}">{{ strip_tags(preg_replace('/<x-[^>]+>/', '', $label)) }}</a></li>
                        @endforeach
                    </ul>
                </nav>
                <div class="btn-group" role="group" aria-label="{{ __('design_system.theme') }}">
                    <button type="button" class="btn btn-sm btn-light" data-bs-theme-value="light" aria-pressed="false"><i class="bi bi-sun me-1" aria-hidden="true"></i>{{ __('design_system.theme_light') }}</button>
                    <button type="button" class="btn btn-sm btn-light" data-bs-theme-value="dark" aria-pressed="false"><i class="bi bi-moon-stars me-1" aria-hidden="true"></i>{{ __('design_system.theme_dark') }}</button>
                </div>
            </div>

            {{-- 1. Jetons --}}
            <section class="xm-ds-section mb-5" id="ds-tokens" aria-labelledby="ds-tokens-h">
                <h2 class="h3" id="ds-tokens-h">{{ $sections['tokens'] }}</h2>
                <div class="row g-3 mb-4">
                    @foreach ([['Primaire', 'primary'], ['Secondaire (attention)', 'secondary'], ['Succès', 'success'], ['Alerte', 'warning'], ['Info', 'info'], ['Violet', 'accent-violet'], ['Fond', 'bg'], ['Surface', 'surface'], ['Texte', 'text'], ['Texte atténué', 'text-muted'], ['Filet', 'border'], ['Focus', 'focus-ring']] as [$name, $token])
                        <div class="col-6 col-md-3 col-xl-2">
                            <div class="xm-ds-swatch" style="background:var(--xm-{{ $token }})"></div>
                            <p class="small mb-0 mt-1">{{ $name }}<br><code>--xm-{{ $token }}</code></p>
                        </div>
                    @endforeach
                </div>
                <p class="display-4 mb-1">Titre Montserrat 800</p>
                <p class="h2">Sous-titre h2 Montserrat 700</p>
                <p class="lead xm-subtitle mb-1">Sous-titre et texte courant en Arial (--xm-font-subtitle, --xm-font-body).</p>
                <p class="mb-0 xm-prose">Corps 16 px, interligne 1,6, mesure 68 caractères. <a href="#ds-tokens">Lien</a>, <strong>gras</strong>, <code>code</code>.</p>
            </section>

            {{-- 2. Boutons --}}
            <section class="xm-ds-section mb-5" id="ds-buttons" aria-labelledby="ds-buttons-h">
                <h2 class="h3" id="ds-buttons-h">{!! $sections['buttons'] !!}</h2>
                <span class="xm-ds-state">Variantes</span>
                <div class="d-flex flex-wrap align-items-center gap-2 mb-4">
                    <x-button>Primaire</x-button>
                    <x-button variant="secondary" icon="megaphone">Signaler un abus</x-button>
                    <x-button variant="outline">Contour</x-button>
                    <x-button variant="outline-secondary">Contour attention</x-button>
                    <x-button variant="light">Clair</x-button>
                    <x-button variant="primary-soft" icon="plus-lg">Doux</x-button>
                    <x-button variant="danger-soft" icon="flag">Doux attention</x-button>
                    <x-button variant="ghost">Discret</x-button>
                    <x-button variant="icon" icon="bell" label="Notifications" />
                    <x-button href="#ds-buttons" variant="outline" icon="box-arrow-up-right">Lien-bouton</x-button>
                </div>
                <span class="xm-ds-state">États</span>
                <div class="row g-3 mb-3">
                    <div class="col-6 col-md-4 col-lg-2"><span class="xm-ds-state">{{ $states['default'] }}</span><x-button>Enregistrer</x-button></div>
                    <div class="col-6 col-md-4 col-lg-2"><span class="xm-ds-state">{{ $states['focus'] }}</span><x-button class="xm-demo-focus">Enregistrer</x-button></div>
                    <div class="col-6 col-md-4 col-lg-2"><span class="xm-ds-state">{{ $states['active'] }}</span><x-button class="active" aria-pressed="true">Enregistrer</x-button></div>
                    <div class="col-6 col-md-4 col-lg-2"><span class="xm-ds-state">{{ $states['disabled'] }}</span><x-button disabled>Enregistrer</x-button></div>
                    <div class="col-6 col-md-4 col-lg-2"><span class="xm-ds-state">{{ $states['loading'] }}</span><x-button :loading="true">Enregistrer</x-button></div>
                    <div class="col-6 col-md-4 col-lg-2"><span class="xm-ds-state">Tailles</span><div class="d-flex flex-wrap align-items-center gap-2"><x-button size="sm">Petit</x-button><x-button size="lg">Grand</x-button></div></div>
                </div>
                <p class="small text-secondary mb-1">{{ __('design_system.hover_note', ['text' => 'fond --xm-primary-hover (bleu) ou --xm-secondary-hover (rouge), transition 200 ms']) }}</p>
                <p class="small text-secondary mb-0">{{ __('design_system.focus_note') }}</p>
            </section>

            {{-- 3. Badges, statuts, urgences --}}
            <section class="xm-ds-section mb-5" id="ds-badges" aria-labelledby="ds-badges-h">
                <h2 class="h3" id="ds-badges-h">{{ $sections['badges'] }}</h2>
                <span class="xm-ds-state">&lt;x-badge&gt; couleurs pleines, douces, pastilles</span>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    @foreach (['primary', 'secondary', 'success', 'warning', 'info', 'violet', 'neutral'] as $c)<x-badge :color="$c">{{ ucfirst($c) }}</x-badge>@endforeach
                    @foreach (['primary', 'success', 'warning', 'info', 'danger'] as $c)<x-badge :color="$c" soft>{{ ucfirst($c) }} doux</x-badge>@endforeach
                    <x-badge color="danger" pill icon="bell-fill">3 nouvelles</x-badge>
                </div>
                <span class="xm-ds-state">&lt;x-status&gt; statuts de cas</span>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    @foreach (['new', 'assigned', 'in_progress', 'pending_victim_response', 'resolved', 'closed', 'unresolved'] as $s)<x-status :status="$s" />@endforeach
                </div>
                <span class="xm-ds-state">&lt;x-badge scope&gt; statuts d'alerte, priorités, types</span>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    @foreach (['nouveau', 'en_cours', 'traite'] as $s)<x-badge scope="alert" :status="$s" />@endforeach
                    @foreach (['critique', 'haute', 'normale'] as $s)<x-badge scope="priority" :status="$s" />@endforeach
                    @foreach (['denonciation', 'besoin', 'sos'] as $s)<x-badge scope="alert_type" :status="$s" />@endforeach
                </div>
                <span class="xm-ds-state">&lt;x-urgency&gt;</span>
                <div class="d-flex flex-wrap gap-2">
                    @foreach (['low', 'medium', 'high', 'critical'] as $l)<x-urgency :level="$l" />@endforeach
                </div>
            </section>

            {{-- 4. Champs --}}
            <section class="xm-ds-section mb-5" id="ds-fields" aria-labelledby="ds-fields-h">
                <h2 class="h3" id="ds-fields-h">{!! $sections['fields'] !!}</h2>
                <div class="row g-4">
                    <div class="col-lg-6">
                        <span class="xm-ds-state">{{ $states['error'] }} : résumé + champs</span>
                        <x-error-summary :messages="['email' => 'Saisis une adresse e-mail valide.', 'password' => 'Le mot de passe doit contenir au moins 8 caractères.']" :autofocus="false" />
                        <x-field name="email" label="Adresse e-mail" type="email" autocomplete="email" value="aya@exemple" error="Saisis une adresse e-mail valide." />
                        <x-field name="password" label="Mot de passe" type="password" autocomplete="new-password" hint="8 caractères minimum, le collage est autorisé." error="Le mot de passe doit contenir au moins 8 caractères." />
                        <span class="xm-ds-state">{{ $states['default'] }}, aide, facultatif</span>
                        <x-field name="pseudo" label="Pseudonyme" hint="Visible par la communauté. Évite ton vrai nom si tu préfères rester discrète." autocomplete="nickname" />
                        <x-field name="phone" label="Téléphone" type="tel" inputmode="tel" autocomplete="tel" placeholder="+229 01 …" optional />
                        <x-field name="zone" label="Département" type="select" :options="['' => 'Choisir…', 'littoral' => 'Littoral', 'oueme' => 'Ouémé', 'borgou' => 'Borgou']" />
                        <x-field name="story" label="Ce que tu veux partager" type="textarea" :maxlength="500" optional hint="Ne mets aucun nom de personne." />
                    </div>
                    <div class="col-lg-6">
                        <span class="xm-ds-state">{{ $states['focus'] }}</span>
                        <x-field name="focus_demo" label="Ville" class="xm-demo-focus" value="Cotonou" />
                        <span class="xm-ds-state">{{ $states['disabled'] }} et lecture seule</span>
                        <x-field name="disabled_demo" label="Identifiant du dossier" value="VBG-0412" disabled />
                        <x-field name="readonly_demo" label="Date de création" type="date" value="2026-03-12" readonly />
                        <span class="xm-ds-state">Cases, interrupteur, groupes</span>
                        <x-field name="newsletter" label="Recevoir la newsletter" type="checkbox" optional />
                        <x-field name="discret" label="Mode discret (titre d'onglet neutre)" type="switch" value="1" />
                        <x-field name="contact_pref" label="Comment te contacter ?" type="radio-group" :options="['tel' => 'Par téléphone', 'email' => 'Par e-mail', 'none' => 'Ne pas me contacter']" value="email" />
                        <x-field name="needs" label="Besoins" type="checkbox-group" :options="['securite' => 'Sécurité', 'juridique' => 'Appui juridique', 'psy' => 'Soutien psychologique']" :value="['juridique']" hint="Plusieurs choix possibles." />
                        <span class="xm-ds-state">{{ $states['loading'] }}</span>
                        <x-skeleton type="lines" :lines="2" class="mb-3" />
                    </div>
                </div>
            </section>

            {{-- 5. Cartes --}}
            <section class="xm-ds-section mb-5" id="ds-cards" aria-labelledby="ds-cards-h">
                <h2 class="h3" id="ds-cards-h">{!! $sections['cards'] !!}</h2>
                <div class="row g-4 mb-3">
                    <div class="col-md-6 col-xl-3">
                        <x-card variant="resource" title="Comprendre ses droits" badge="Droits" href="#ds-cards" meta="5 min de lecture" :image="$hero" image-alt="Deux femmes discutent sur un banc">Une fiche courte et sûre pour connaître ses recours.</x-card>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <x-card variant="news" title="Atelier « Dire non sans culpabiliser »" badge="Événement" meta="Samedi 15 h · Cotonou">Inscription gratuite, places limitées.<x-slot:actions><x-button size="sm" variant="primary-soft">S’inscrire</x-button><x-button size="sm" variant="light">Partager</x-button></x-slot:actions></x-card>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <x-card variant="contact" title="Ligne d’écoute Xomiton" meta="24 h / 24, 7 j / 7">Juristes et psychologues joignables.<x-slot:actions><x-button size="sm" variant="secondary" icon="telephone-fill">Appeler</x-button></x-slot:actions></x-card>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <x-card variant="event" title="Carte au survol" href="#ds-cards" meta="Carte entièrement cliquable">{{ __('design_system.hover_note', ['text' => 'élévation −3 px et ombre, désactivée en mouvement réduit']) }}</x-card>
                    </div>
                </div>
                <span class="xm-ds-state">Carte « liste » d'administration (en-tête h2.h6 + actions)</span>
                <x-card :header="true" title="Dernières alertes" class="mb-3">
                    <x-slot:actions><x-button size="sm" variant="light" icon="upload">Importer CSV</x-button><x-button size="sm" variant="light" icon="download">Exporter CSV</x-button></x-slot:actions>
                    <p class="mb-0 text-secondary small">Le corps reçoit la barre de filtres puis le tableau (voir Données).</p>
                </x-card>
                <span class="xm-ds-state">{{ $states['loading'] }}</span>
                <div class="row g-4"><div class="col-md-6 col-xl-3"><x-skeleton type="card" /></div></div>
            </section>

            {{-- 6. KPI --}}
            <section class="xm-ds-section mb-5" id="ds-stats" aria-labelledby="ds-stats-h">
                <h2 class="h3" id="ds-stats-h">{!! $sections['stats'] !!}</h2>
                <div class="row g-3 mb-3">
                    <div class="col-6 col-xl-3"><x-stat label="Nouvelles alertes" value="7" variant="danger" icon="bell" href="#ds-stats" trend="+2" direction="up" /></div>
                    <div class="col-6 col-xl-3"><x-stat label="Cas en cours" value="24" variant="warning" icon="hourglass-split" trend="−8 %" direction="down" sub="sur 30 jours" /></div>
                    <div class="col-6 col-xl-3"><x-stat label="Cas clos" value="112" variant="success" icon="check2-circle" trend="stable" direction="flat" /></div>
                    <div class="col-6 col-xl-3"><x-stat label="Utilisateur·rices" value="1 482" variant="violet" icon="people" href="#ds-stats" /></div>
                    <div class="col-6 col-xl-3"><x-stat label="Taux de synchro" value="96 %" icon="cloud-check" /></div>
                    <div class="col-6 col-xl-3"><span class="xm-ds-state">{{ $states['loading'] }}</span><x-skeleton type="kpi" /></div>
                </div>
                <p class="small text-secondary mb-0">{{ __('design_system.hover_note', ['text' => 'un KPI cliquable s’élève de 3 px (xm-card-hover) et mène à la liste filtrée']) }} Sous 768 px, la rangée de KPI d'un écran de gestion devient un accordéon « Indicateurs clés » (UI_UX_DESIGN_SYSTEM §4.1, assemblé par l'écran).</p>
            </section>

            {{-- 7. Alertes, toasts, états --}}
            <section class="xm-ds-section mb-5" id="ds-feedback" aria-labelledby="ds-feedback-h">
                <h2 class="h3" id="ds-feedback-h">{{ $sections['feedback'] }}</h2>
                <x-alert type="info" title="Information">Tes données restent confidentielles.</x-alert>
                <x-alert type="success" title="Enregistré" dismissible>Ton signalement a bien été transmis.</x-alert>
                <x-alert type="warning">Besoin d’aide ? Utilise plutôt le signalement anonyme ou le SOS.</x-alert>
                <x-alert type="danger" title="Erreur">L’envoi a échoué. Vérifie ta connexion puis réessaie.</x-alert>
                <span class="xm-ds-state">Toasts (Bootstrap Toast, 4,5 s, coin supérieur droit)</span>
                <div class="d-flex flex-wrap gap-2 mb-4">
                    <x-button variant="outline" icon="chat-square-text" onclick="XM.toast('Publié.', 'success')">Toast succès</x-button>
                    <x-button variant="outline" icon="info-circle" onclick="XM.toast('Connexion rétablie — synchronisation…', 'primary')">Toast info</x-button>
                    <x-button variant="outline-secondary" icon="exclamation-octagon" onclick="XM.toast('Veuillez corriger les champs signalés.', 'danger')">Toast erreur</x-button>
                </div>
                <div class="row g-4">
                    <div class="col-md-6">
                        <span class="xm-ds-state">{{ $states['empty'] }}</span>
                        <div class="card"><x-empty-state title="Aucune ressource ne correspond à ta recherche." icon="search">Essaie un autre mot ou retire un filtre.<x-slot:action><x-button variant="primary-soft">Réinitialiser les filtres</x-button></x-slot:action></x-empty-state></div>
                    </div>
                    <div class="col-md-6">
                        <span class="xm-ds-state">{{ $states['loading'] }} : squelettes</span>
                        <div class="card card-body"><x-skeleton type="avatar" class="mb-3" /><x-skeleton type="lines" :lines="3" /></div>
                    </div>
                </div>
            </section>

            {{-- 8. Modales --}}
            <section class="xm-ds-section mb-5" id="ds-modals" aria-labelledby="ds-modals-h">
                <h2 class="h3" id="ds-modals-h">{!! $sections['modals'] !!}</h2>
                <div class="d-flex flex-wrap gap-2 mb-2">
                    <x-button icon="plus-lg" data-bs-toggle="modal" data-bs-target="#dsModalForm">Nouveau contact (formulaire, modal-lg)</x-button>
                    <x-button variant="outline" data-bs-toggle="modal" data-bs-target="#dsModalConfirm">Confirmation</x-button>
                    <x-button variant="secondary" icon="trash" data-bs-toggle="modal" data-bs-target="#dsModalDelete">Supprimer… (mot de confirmation)</x-button>
                    <x-button variant="light" icon="arrows-fullscreen" data-bs-toggle="modal" data-bs-target="#dsModalXl">Éditeur (modal-xl, plein écran mobile)</x-button>
                </div>
                <p class="small text-secondary mb-0">Pied fixe : « Annuler » (btn-light) à gauche, action à droite. Focus piégé, Échap ferme, retour du focus au déclencheur. Le formulaire modifié demande confirmation avant fermeture.</p>
            </section>

            {{-- 9. Navigation --}}
            <section class="xm-ds-section mb-5" id="ds-navigation" aria-labelledby="ds-navigation-h">
                <h2 class="h3" id="ds-navigation-h">{{ $sections['navigation'] }}</h2>
                <span class="xm-ds-state">&lt;x-breadcrumb&gt;</span>
                <x-breadcrumb class="mb-4" :items="[['label' => 'Accueil', 'url' => '/'], ['label' => 'Ressources', 'url' => '/ressources'], ['label' => 'Comprendre ses droits']]" />
                <span class="xm-ds-state">&lt;x-tabs&gt; (onglet actif dans ?tab=)</span>
                <x-tabs :tabs="['infos' => 'Informations', 'notes' => 'Notes', 'historique' => 'Historique']" label="Fiche de cas" class="mb-4">
                    <x-slot:infos><p class="mb-0">Contenu de l’onglet Informations.</p></x-slot:infos>
                    <x-slot:notes><p class="mb-0">Contenu de l’onglet Notes.</p></x-slot:notes>
                    <x-slot:historique><p class="mb-0">Contenu de l’onglet Historique.</p></x-slot:historique>
                </x-tabs>
                <span class="xm-ds-state">&lt;x-accordion&gt; (un seul panneau ouvert)</span>
                <x-accordion class="mb-4" id="dsFaq" :items="[['q' => 'Qui peut voir mon signalement ?', 'a' => '<p class=&quot;mb-0&quot;>Seulement l’équipe de prise en charge, jamais la communauté.</p>'], ['q' => 'Puis-je rester anonyme ?', 'a' => '<p class=&quot;mb-0&quot;>Oui. Aucun compte n’est nécessaire pour signaler.</p>'], ['q' => 'Que se passe-t-il après un SOS ?', 'a' => '<p class=&quot;mb-0&quot;>La personne de l’annuaire la plus proche est prévenue et te rappelle.</p>']]" />
                <span class="xm-ds-state">&lt;x-stepper&gt; (terminée · en cours · à venir)</span>
                <x-stepper :steps="['Identité', 'Contact et lieu', 'Profil', 'Sécurité']" :current="2" />
            </section>

            {{-- 10. Données --}}
            <section class="xm-ds-section mb-5" id="ds-data" aria-labelledby="ds-data-h">
                <h2 class="h3" id="ds-data-h">{{ $sections['data'] }}</h2>
                <span class="xm-ds-state">&lt;x-datatable&gt; (cartes empilées sous 768 px, tri annoncé)</span>
                <div class="card mb-4">
                    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2"><h3 class="h6 mb-0">3 alertes</h3><x-button size="sm" variant="light" icon="download">Exporter CSV</x-button></div>
                    <x-datatable caption="Dernières alertes reçues, triées par dossier" :columns="$columns" id="dsTable">
                        @foreach ($rows as [$code, $type, $prio, $status, $zone, $when])
                            <tr>
                                <td data-label="Dossier"><a class="fw-semibold" href="#ds-data">{{ $code }}</a></td>
                                <td data-label="Type"><x-badge scope="alert_type" :status="strtolower($type) === 'sos' ? 'sos' : (strtolower($type) === 'besoin' ? 'besoin' : 'denonciation')" /></td>
                                <td data-label="Priorité"><x-badge scope="priority" :status="$prio" /></td>
                                <td data-label="Statut"><x-badge scope="alert" :status="$status" /></td>
                                <td data-label="Zone">{{ $zone }}</td>
                                <td data-label="Reçue" class="small text-nowrap">{{ $when }}</td>
                                <td class="text-end text-nowrap xm-table__actions">
                                    @if ($status === 'nouveau')<x-button size="sm">Prendre en charge</x-button>@else<x-button size="sm" variant="light">Voir</x-button>@endif
                                    <x-button size="sm" variant="icon" icon="trash" label="Supprimer {{ $code }}" class="text-danger" data-bs-toggle="modal" data-bs-target="#dsModalDelete" />
                                </td>
                            </tr>
                        @endforeach
                        <x-slot:footer>
                            <span class="small text-secondary" role="status">Affichage de 1 à 3 sur 57 entrées</span>
                            <nav aria-label="Pagination du tableau"><ul class="pagination pagination-sm mb-0 flex-wrap xm-dt-pager">
                                <li class="page-item disabled"><a class="page-link" href="#" tabindex="-1" aria-disabled="true">Précédent</a></li>
                                <li class="page-item active"><a class="page-link" href="#" aria-current="page">1</a></li>
                                <li class="page-item"><a class="page-link" href="#">2</a></li>
                                <li class="page-item disabled"><span class="page-link">…</span></li>
                                <li class="page-item"><a class="page-link" href="#">19</a></li>
                                <li class="page-item"><a class="page-link" href="#">Suivant</a></li>
                            </ul></nav>
                        </x-slot:footer>
                    </x-datatable>
                </div>
                <div class="row g-4">
                    <div class="col-lg-6">
                        <span class="xm-ds-state">{{ $states['empty'] }}</span>
                        <div class="card mb-4"><x-datatable caption="Contacts de l’annuaire" :columns="[['key' => 'name', 'label' => 'Nom'], ['key' => 'role', 'label' => 'Rôle'], ['key' => 'zone', 'label' => 'Zone']]" empty="Aucun contact ne correspond aux filtres." /></div>
                        <span class="xm-ds-state">{{ $states['loading'] }}</span>
                        <div class="card card-body"><x-skeleton type="table" :rows="3" /></div>
                    </div>
                    <div class="col-lg-6">
                        <span class="xm-ds-state">&lt;x-timeline&gt; chronologie d'un dossier</span>
                        <div class="card"><div class="card-header"><h3 class="h6 mb-0">Frise de suivi — VBG-0412</h3></div><div class="card-body"><x-timeline :items="$timeline" /></div></div>
                        <div class="card mt-3"><div class="card-body"><x-timeline :items="[]" /></div></div>
                    </div>
                </div>
            </section>

            {{-- 11. Cloche --}}
            <section class="xm-ds-section mb-5" id="ds-bell" aria-labelledby="ds-bell-h">
                <h2 class="h3" id="ds-bell-h">{!! $sections['bell'] !!}</h2>
                <div class="d-flex flex-wrap align-items-center gap-4">
                    <div><span class="xm-ds-state">Administration (3 non lues)</span><x-bell :items="$notifs" href="#ds-bell" href-label="Voir le journal d’audit" id="dsBellAdmin" /></div>
                    <div><span class="xm-ds-state">Membre</span><x-bell variant="member" :items="array_slice($notifs, 1, 2)" href="#ds-bell" id="dsBellMember" /></div>
                    <div><span class="xm-ds-state">{{ $states['empty'] }}</span><x-bell :items="[]" id="dsBellEmpty" /></div>
                </div>
                <p class="small text-secondary mt-3 mb-0">Panneau Non lus / Lues / Toutes, « Tout marquer comme lu », état vide. Mise à jour réelle par polling Livewire (D7) ; son et alternative visuelle pour les SOS (P10-03).</p>
            </section>

            {{-- 12. Médias --}}
            <section class="xm-ds-section mb-5" id="ds-media" aria-labelledby="ds-media-h">
                <h2 class="h3" id="ds-media-h">{{ $sections['media'] }}</h2>
                <span class="xm-ds-state">&lt;x-avatar&gt; tailles, image et initiales</span>
                <div class="d-flex flex-wrap align-items-end gap-3 mb-4">
                    <x-avatar name="Mariam Adjovi" :src="$avatar" size="xs" />
                    <x-avatar name="Mariam Adjovi" :src="$avatar" size="sm" />
                    <x-avatar name="Mariam Adjovi" :src="$avatar" />
                    <x-avatar name="Mariam Adjovi" :src="$avatar" size="lg" />
                    <x-avatar name="Ada Lovelace" size="lg" />
                    <x-avatar name="Ada Lovelace" />
                    <x-avatar name="Membre anonyme" size="sm" rounded="2" />
                </div>
                <span class="xm-ds-state">&lt;x-img&gt; (lazy par défaut, priority pour l'image LCP, alt="" décorative ; prop media → &lt;picture&gt; WebP → repli + srcset, largeur max 1600 px, sans AVIF fictif)</span>
                <div class="row g-3 mb-4">
                    <div class="col-6 col-md-3"><div class="xm-ratio-4x3 rounded-3"><x-img :src="$hero" alt="Deux femmes discutent sur un banc" class="xm-img-cover" width="400" height="300" /></div><p class="small text-secondary mb-0 mt-1">loading="lazy"</p></div>
                    <div class="col-6 col-md-3"><div class="xm-ratio-4x3 rounded-3"><x-img :src="$hero" alt="Deux femmes discutent sur un banc" class="xm-img-cover" width="400" height="300" :priority="true" /></div><p class="small text-secondary mb-0 mt-1">fetchpriority="high"</p></div>
                    <div class="col-6 col-md-3"><div class="xm-ratio-4x3 rounded-3"><x-img :src="$hero" alt="" class="xm-img-cover" width="400" height="300" /></div><p class="small text-secondary mb-0 mt-1">décorative : alt="" + aria-hidden</p></div>
                    <div class="col-6 col-md-3"><div class="xm-ratio-4x3 rounded-3 d-flex align-items-center justify-content-center border"><code class="small">&lt;x-img&gt; sans alt → exception</code></div><p class="small text-secondary mb-0 mt-1">{{ $states['error'] }} : rendu refusé</p></div>
                </div>
                <span class="xm-ds-state">&lt;x-logo&gt; (variantes couleur / sombre / mono-* / vertical-* / icone-* ; adaptive pour les en-têtes ; alt « Xomiton »)</span>
                <div class="d-flex flex-wrap gap-4 align-items-center">
                    <x-logo variant="couleur" :link="false" />
                    <span class="p-3 rounded-3 xm-ds-dark"><x-logo variant="sombre" :link="false" /></span>
                    <span class="p-3 rounded-3 xm-ds-dark"><x-logo variant="mono-blanc" :width="120" :link="false" /></span>
                    <x-logo variant="mono-bleu" :width="120" :link="false" />
                    <x-logo variant="vertical-couleur" :width="110" :link="false" />
                    <x-logo variant="icone-bleu" :width="48" :link="false" />
                    <span class="p-2 rounded-3 xm-ds-dark"><x-logo variant="icone-blanc" :width="48" :link="false" /></span>
                </div>
            </section>

            {{-- 13. SOS --}}
            <section class="xm-ds-section mb-5" id="ds-sos" aria-labelledby="ds-sos-h">
                <h2 class="h3" id="ds-sos-h">{!! $sections['sos'] !!}</h2>
                <div class="d-flex flex-wrap align-items-center gap-4">
                    <div><span class="xm-ds-state">{{ $states['default'] }}</span><x-sos-button :fixed="false" id="dsSos" /></div>
                    <div><span class="xm-ds-state">{{ $states['focus'] }}</span><span class="d-inline-block"><button type="button" class="xm-sos xm-demo-focus" aria-hidden="true" tabindex="-1">SOS</button></span></div>
                    <div><span class="xm-ds-state">{{ $states['active'] }} (maintien)</span><span class="d-inline-block"><button type="button" class="xm-sos" data-holding="true" aria-hidden="true" tabindex="-1">SOS</button></span></div>
                    <p class="small text-secondary mb-0 xm-prose">64 px, rouge, Montserrat 800. Maintien 2 s (anneau de progression) puis compte à rebours 5 s annulable ; au clavier : Espace maintenue, ou Entrée puis confirmation. « Ton alerte est partie. » en aria-live="assertive". Position fixe en bas à droite dans les pages ; ici en ligne.</p>
                </div>
            </section>
        </div>
    </section>

    {{-- Modales montées en permanence --}}
    <x-modal id="dsModalForm" title="Nouveau contact" type="form" size="lg" form="#ds-modals" action="Enregistrer" :dirty-guard="true">
        <div class="row g-3">
            <div class="col-sm-6"><x-field name="contact_name" label="Nom" autocomplete="off" class="mb-0" /></div>
            <div class="col-sm-6"><x-field name="contact_role" label="Rôle" type="select" :options="['juriste' => 'Juriste', 'psy' => 'Psychologue', 'focal' => 'Point focal']" /></div>
            <div class="col-sm-6"><x-field name="contact_phone" label="Téléphone" type="tel" inputmode="tel" /></div>
            <div class="col-sm-6"><x-field name="contact_zone" label="Département" type="select" :options="['littoral' => 'Littoral', 'oueme' => 'Ouémé']" /></div>
            <div class="col-12"><x-field name="contact_active" label="Contact actif (reçoit les alertes routées)" type="switch" value="1" /></div>
        </div>
    </x-modal>
    <x-modal id="dsModalConfirm" title="Publier cette ressource ?" action="Publier">
        <p class="mb-0">La ressource sera visible par toute la communauté. Tu pourras la dépublier à tout moment.</p>
    </x-modal>
    <x-modal id="dsModalDelete" title="Supprimer ce template ?" type="delete" confirm-word="SUPPRIMER">
        <p>Vous allez supprimer <strong>« Réinitialisation du mot de passe »</strong>. Cette action est définitive ; les cas, alertes et preuves s’archivent et ne se suppriment pas.</p>
    </x-modal>
    <x-modal id="dsModalXl" title="Modifier : Newsletter d’avril" type="form" size="xl" action="Programmer l’envoi">
        <p class="text-secondary small">Éditeur riche (Quill) et guide des variables à droite dans l’administration ; ici un aperçu de la mise en page modal-xl + modal-fullscreen-lg-down.</p>
        <x-field name="nl_subject" label="Objet" value="Les nouveautés d’avril" />
        <x-field name="nl_body" label="Contenu" type="textarea" :rows="8" />
    </x-modal>
    <x-toast />
</x-layouts.public>
