{{-- Catalogue vivant (/admin/design-system), organisé comme le sommaire de la charte. Local et staging uniquement. --}}
<!doctype html>
<html lang="fr" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Catalogue du design system</title>
    @foreach ($css as $href)<link rel="stylesheet" href="{{ $href }}">@endforeach
</head>
<body>
<a class="xm-skip-link" href="#contenu">Aller au contenu</a>
<header class="sticky-top border-bottom" style="background:var(--xm-surface)">
    <div class="container d-flex align-items-center gap-3 py-2">
        <x-logo :width="120" />
        <span class="xm-hint">Catalogue du design system · v1.0</span>
        <x-button class="ms-auto" size="sm" variant="outline" icon="moon-stars" x-data @click="document.documentElement.dataset.bsTheme = document.documentElement.dataset.bsTheme === 'dark' ? 'light' : 'dark'">Thème</x-button>
        <x-quick-exit />
    </div>
</header>

<main id="contenu" class="container py-5" tabindex="-1">
    <h1 class="mb-5">Composants Xomiton</h1>

    <section aria-labelledby="s3" class="mb-6 mb-5">
        <h2 id="s3" class="h3">3 · Couleur</h2>
        <div class="row g-3">
            @foreach ([['Primaire', 'primary'], ['Secondaire (attention)', 'secondary'], ['Succès', 'success'], ['Info', 'info'], ['Alerte', 'warning'], ['Violet', 'accent-violet']] as [$n, $t])
                <div class="col-6 col-md-2"><div class="rounded-3 p-3 border" style="background:var(--xm-{{ $t }});min-height:80px"></div><p class="small mb-0 mt-1">{{ $n }}<br><code>--xm-{{ $t }}</code></p></div>
            @endforeach
        </div>
    </section>

    <section aria-labelledby="s4" class="mb-5">
        <h2 id="s4" class="h3">4 · Logo</h2>
        <div class="d-flex flex-wrap gap-4 align-items-center">
            <x-logo variant="couleur" :link="false" />
            <span class="p-3 rounded-3" style="background:#0E1020"><x-logo variant="sombre" :link="false" /></span>
            <x-logo variant="vertical-couleur" :width="120" :link="false" />
        </div>
    </section>

    <section aria-labelledby="s5" class="mb-5">
        <h2 id="s5" class="h3">5 · Typographie</h2>
        <p class="display-4 mb-0">Titre Montserrat</p>
        <p class="lead xm-subtitle">Sous-titre et texte courant en Arial.</p>
    </section>

    <section aria-labelledby="s9" class="mb-5">
        <h2 id="s9" class="h3">9 · Boutons, urgences, statuts</h2>
        <div class="d-flex flex-wrap gap-3 mb-3">
            <x-button>Primaire</x-button>
            <x-button variant="secondary" icon="exclamation-triangle">Signaler un abus</x-button>
            <x-button variant="outline">Contour</x-button>
            <x-button variant="ghost">Discret</x-button>
            <x-button variant="icon" icon="bell" label="Notifications" />
            <x-button :loading="true">Envoi</x-button>
            <x-button disabled>Désactivé</x-button>
        </div>
        <div class="d-flex flex-wrap gap-2 mb-3">
            @foreach (['low', 'medium', 'high', 'critical'] as $l)<x-urgency :level="$l" />@endforeach
        </div>
        <div class="d-flex flex-wrap gap-2">
            @foreach (['new', 'assigned', 'in_progress', 'pending_victim_response', 'resolved', 'closed', 'unresolved'] as $s)<x-status :status="$s" />@endforeach
        </div>
    </section>

    <section aria-labelledby="s10" class="mb-5">
        <h2 id="s10" class="h3">10 · Indicateurs</h2>
        <div class="row g-3">
            <div class="col-md-4"><x-stat label="Cas ouverts" value="42" trend="+12 %" direction="up" icon="folder2-open" href="#" /></div>
            <div class="col-md-4"><x-stat label="Délai moyen de prise en charge" value="3 h" trend="−8 %" direction="down" icon="clock" /></div>
            <div class="col-md-4"><x-stat label="Alertes SOS (7 j)" value="2" icon="broadcast" /></div>
        </div>
        <p class="xm-hint mt-2">Valeurs d'exemple.</p>
    </section>

    <section aria-labelledby="s11" class="mb-5">
        <h2 id="s11" class="h3">11 · Composition : cartes, formulaire, étapes</h2>
        <x-stepper :steps="['Identité', 'Contact', 'Confiance']" :current="2" />
        <div class="row g-4">
            <div class="col-md-6">
                <x-card variant="resource" title="Comprendre ses droits" badge="Droits" href="#" meta="5 min de lecture">Une fiche courte et sûre pour connaître ses recours.</x-card>
            </div>
            <div class="col-md-6">
                <x-error-summary />
                <x-field name="email" label="Adresse e-mail" type="email" autocomplete="email" hint="Jamais partagée." />
                <x-field name="password" label="Mot de passe" type="password" autocomplete="new-password" />
                <x-field name="city" label="Ville" optional />
                <x-field name="notif" label="Recevoir les actualités" type="switch" />
            </div>
        </div>
    </section>

    <section aria-labelledby="s12" class="mb-5">
        <h2 id="s12" class="h3">Navigation, retours et états</h2>
        <x-breadcrumb :items="[['label' => 'Accueil', 'url' => '/'], ['label' => 'Ressources', 'url' => '/ressources'], ['label' => 'Comprendre ses droits']]" />
        <x-tabs :tabs="['infos' => 'Informations', 'notes' => 'Notes']" label="Fiche de cas" class="mt-4">
            <x-slot:infos>Contenu de l'onglet Informations.</x-slot:infos>
            <x-slot:notes>Contenu de l'onglet Notes.</x-slot:notes>
        </x-tabs>
        <x-accordion class="my-4" :items="[['q' => 'Qui peut voir mon signalement ?', 'a' => '<p>Seulement l’équipe de prise en charge.</p>'], ['q' => 'Puis-je rester anonyme ?', 'a' => '<p>Oui.</p>']]" />
        <x-alert type="info" title="Information">Message d'information.</x-alert>
        <x-alert type="danger" title="Erreur">Quelque chose a échoué, réessaie.</x-alert>
        <x-empty-state title="Aucun cas pour le moment" icon="folder2-open">Les nouveaux cas apparaîtront ici.</x-empty-state>
        <div class="d-flex flex-wrap gap-3 align-items-center">
            <x-avatar name="Ada Lovelace" />
            <x-button variant="outline" icon="chat-square-text" x-data @click="$dispatch('toast', { type: 'success', message: 'Enregistré' })">Afficher un toast</x-button>
            <x-button variant="secondary" icon="trash" x-data @click="$dispatch('open-modal', 'demo-delete')">Supprimer…</x-button>
        </div>
    </section>
</main>

<x-modal id="demo-delete" title="Supprimer ce cas ?" type="delete" confirm-word="SUPPRIMER">
    <p>Le cas sera archivé, pas effacé. Cette action est tracée.</p>
    <x-slot:footer><button type="button" class="btn btn-secondary">Supprimer définitivement</button></x-slot:footer>
</x-modal>
<x-toast />
<x-sos-button />

@foreach ($js as $src)<script src="{{ $src }}" @if (str_contains($src, 'alpine')) defer @endif></script>@endforeach
</body>
</html>
